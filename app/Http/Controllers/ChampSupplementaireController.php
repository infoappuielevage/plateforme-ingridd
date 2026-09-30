<?php

namespace App\Http\Controllers;

use App\Models\ChampSupplementaire;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel "Champs supplémentaires" — permet à l'administration d'ajouter
 * un document propre à déposer sur les PV de délibération et/ou les Relevés
 * de notes, SANS toucher au code (demande explicite du client : "pouvoir
 * ajouter un champ ... si on veut ajouter un autre document dans le PV ou
 * dans un relevé de notes"). Contrôleur dédié plutôt que rattaché à
 * App\Http\Controllers\ReferentielController (voir son docblock) : ce
 * référentiel a des réglages propres (pour_pv/pour_releve/cycles) qui ne
 * rentrent pas dans la forme commune code/libellé/ordre/actif, et surtout une
 * synchronisation rétroactive sur les sessions déjà existantes (voir
 * synchroniserCasesRetroactives() ci-dessous) qu'aucun autre référentiel ne
 * fait.
 */
class ChampSupplementaireController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $champs = ChampSupplementaire::query()
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->orderBy('ordre')
            ->paginate(20)
            ->withQueryString();

        return view('gestion.champs-supplementaires.index', [
            'champs' => $champs,
            'corbeille' => $corbeille,
        ]);
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'create'), 403);

        return view('gestion.champs-supplementaires.form', [
            'champ' => null,
            'cycles' => ChampSupplementaire::cyclesDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'create'), 403);

        $donnees = $this->valider($request);

        if ($erreur = $this->erreurAuMoinsUnDocument($donnees)) {
            return back()->withInput()->withErrors($erreur);
        }

        $champ = ChampSupplementaire::create($donnees);

        // Demande explicite du client : un champ ajouté doit aussi profiter
        // aux sessions déjà créées, pas seulement aux nouvelles — voir le
        // docblock de synchroniserCasesRetroactives() ci-dessous.
        $this->synchroniserCasesRetroactives($champ);

        return redirect()->route('gestion.champs-supplementaires.index')->with('succes', 'Champ supplémentaire ajouté.');
    }

    public function edit(ChampSupplementaire $champSupplementaire): View
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'edit'), 403);

        return view('gestion.champs-supplementaires.form', [
            'champ' => $champSupplementaire,
            'cycles' => ChampSupplementaire::cyclesDisponibles(),
        ]);
    }

    public function update(Request $request, ChampSupplementaire $champSupplementaire): RedirectResponse
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'edit'), 403);

        $donnees = $this->valider($request);

        if ($erreur = $this->erreurAuMoinsUnDocument($donnees)) {
            return back()->withInput()->withErrors($erreur);
        }

        $champSupplementaire->update($donnees);

        // Même synchro qu'à la création : un cycle ou un document (PV/Relevé)
        // ajouté après coup à un champ existant doit lui aussi profiter aux
        // sessions déjà créées.
        $this->synchroniserCasesRetroactives($champSupplementaire);

        return redirect()->route('gestion.champs-supplementaires.index')->with('succes', 'Champ supplémentaire modifié.');
    }

    /** Mise à la corbeille — jamais si ce champ est encore utilisé par au moins une case ayant un document déposé. */
    public function destroy(ChampSupplementaire $champSupplementaire): RedirectResponse
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'delete'), 403);

        $encoreUtilise = $champSupplementaire->fichiersPv()->whereNotNull('document_id')->exists()
            || $champSupplementaire->fichiersReleve()->whereNotNull('document_id')->exists()
            || $champSupplementaire->fichiersSoutenance()->whereNotNull('document_id')->exists();

        if ($encoreUtilise) {
            return back()->withErrors([
                'suppression' => 'Ce champ est encore utilisé par au moins un document déposé : impossible de le mettre à la corbeille.',
            ]);
        }

        $champSupplementaire->delete();

        return back()->with('succes', 'Champ supplémentaire mis à la corbeille.');
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'delete'), 403);

        $champ = ChampSupplementaire::onlyTrashed()->findOrFail($id);
        $champ->restore();

        // Un champ restauré redevient actif s'il l'était avant sa mise à la
        // corbeille (actif n'est pas modifié par delete()/restore()) : on
        // resynchronise donc comme à la création, au cas où de nouvelles
        // sessions auraient été créées entre temps.
        $this->synchroniserCasesRetroactives($champ);

        return back()->with('succes', 'Champ supplémentaire restauré.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('champs_supplementaires', 'delete'), 403);

        ChampSupplementaire::onlyTrashed()->findOrFail($id)->forceDelete();

        return back()->with('succes', 'Champ supplémentaire supprimé définitivement.');
    }

    protected function valider(Request $request): array
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'cycles' => ['required', 'array', 'min:1'],
            'cycles.*' => ['string', Rule::in(array_keys(ChampSupplementaire::cyclesDisponibles()))],
            'ordre' => ['required', 'integer', 'min:0'],
        ]);

        // Cases à cocher : jamais envoyées par le navigateur quand elles sont
        // décochées (voir gestion/champs-supplementaires/form.blade.php, même
        // pattern que TypeDocumentController::valider()) — ->boolean() gère
        // les deux cas (présent ou totalement absent).
        $donnees['pour_pv'] = $request->boolean('pour_pv');
        $donnees['pour_releve'] = $request->boolean('pour_releve');
        $donnees['pour_soutenance'] = $request->boolean('pour_soutenance');
        $donnees['actif'] = $request->boolean('actif');

        return $donnees;
    }

    /** @return array<string, string>|null message d'erreur si aucun document n'est coché, sinon null (validation ok). */
    protected function erreurAuMoinsUnDocument(array $donnees): ?array
    {
        if ($donnees['pour_pv'] || $donnees['pour_releve'] || $donnees['pour_soutenance']) {
            return null;
        }

        return ['pour_pv' => 'Choisissez au moins un document concerné (PV de délibération, Relevé de notes et/ou PV de soutenance).'];
    }

    /**
     * Ajoute une case vide pour ce champ sur CHAQUE session déjà existante
     * (PV et/ou Relevé, selon pour_pv/pour_releve) dont le cycle correspond —
     * demande explicite du client : un champ ajouté (ou modifié) après coup
     * doit aussi profiter aux sessions déjà créées, pas seulement aux
     * nouvelles (voir aussi SessionDeliberationController::creerCasesVides()/
     * SessionReleveController::creerCasesVides(), qui couvrent le cas des
     * NOUVELLES sessions). firstOrCreate() évite tout doublon si cette
     * méthode est appelée plusieurs fois (store, update, restore) pour le
     * même champ.
     *
     * Ne retire JAMAIS une case existante, même si le champ est désactivé ou
     * si sa portée (cycles/pour_pv/pour_releve) se réduit ensuite : un champ
     * déjà déposé ne doit jamais disparaître automatiquement — voir le
     * docblock de App\Models\ChampSupplementaire.
     */
    protected function synchroniserCasesRetroactives(ChampSupplementaire $champ): void
    {
        if (! $champ->actif) {
            return;
        }

        if ($champ->pour_pv) {
            SessionDeliberation::query()
                ->whereIn('cycle', $champ->cycles ?? [])
                ->each(function (SessionDeliberation $session) use ($champ) {
                    SessionDeliberationFichier::firstOrCreate([
                        'session_deliberation_id' => $session->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
                        'numero' => null,
                        'document_id' => null,
                    ]);
                });
        }

        if ($champ->pour_releve) {
            SessionReleve::query()
                ->whereIn('cycle', $champ->cycles ?? [])
                ->each(function (SessionReleve $session) use ($champ) {
                    SessionReleveFichier::firstOrCreate([
                        'session_releve_id' => $session->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => SessionReleveFichier::ROLE_SUPPLEMENTAIRE,
                        'numero' => null,
                        'document_id' => null,
                    ]);
                });
        }

        if ($champ->pour_soutenance) {
            PvSoutenance::query()
                ->whereIn('niveau', $champ->cycles ?? [])
                ->each(function (PvSoutenance $pvSoutenance) use ($champ) {
                    PvSoutenanceFichier::firstOrCreate([
                        'pv_soutenance_id' => $pvSoutenance->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
                        'document_id' => null,
                    ]);
                });
        }
    }
}
