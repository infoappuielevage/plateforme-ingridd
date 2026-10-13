<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\ChampSupplementaire;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use App\Support\Semestres;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion des Relevés de notes — MIROIR volontaire de
 * App\Http\Controllers\SessionDeliberationController ("même chose que pour
 * le PV de délibération", demande explicite du client une fois celle-ci
 * validée). Voir le docblock de cette classe pour le détail complet des
 * choix (pourquoi un vrai App\Models\Document derrière chaque case, pourquoi
 * pas de classe de base commune, etc.) — repris ici à l'identique, à deux
 * différences près : le type de document (RELEVE_NOTES au lieu de
 * PV_DELIBERATION) et l'absence de cases "Note d'information" (demande
 * explicite du client, voir creerCasesVides() ci-dessous) — un relevé de
 * notes n'a besoin que des cases numérotées (semestre ou année de thèse).
 */
class SessionReleveController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $sessions = $this->requeteListe($request, $corbeille)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête — même principe que
        // SessionDeliberationController::index().
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc');

        return view('gestion.sessions-releves.index', [
            'sessions' => $sessions,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && SessionReleve::onlyTrashed()->exists(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'onglets' => ['tous' => 'Tous'] + SessionReleve::CYCLES,
            'cycleActif' => $this->ongletCycleValide($request),
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /**
     * Export Excel (.xlsx) de la liste — demandé par le client, respecte
     * EXACTEMENT les mêmes filtres que l'écran (voir requeteListe()
     * ci-dessous, partagée avec index()), simplement sans pagination.
     */
    public function export(Request $request): StreamedResponse
    {
        $corbeille = $request->boolean('corbeille');
        $sessions = $this->requeteListe($request, $corbeille)->get();

        $lignes = $sessions->map(fn (SessionReleve $session) => [
            $session->anneeAcademique?->libelle,
            $session->sessionLibelle(),
            $session->cycleLibelle(),
            $session->filiere?->libelle,
            $session->description,
            $session->created_at?->format('d/m/Y H:i'),
        ]);

        return FichiersExcel::exporter(
            'sessions-releves-'.now()->format('Y-m-d').'.xlsx',
            ['Année académique', 'Session', 'Cycle', 'Filière', 'Description', 'Créée le'],
            $lignes
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<SessionReleve> */
    protected function requeteListe(Request $request, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        $cycle = $this->ongletCycleValide($request);

        $query = SessionReleve::query()
            ->with(['anneeAcademique', 'filiere', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('annee_academique_id'), fn ($q) => $q->where('annee_academique_id', $request->integer('annee_academique_id')))
            // Onglet "cycle" — voir SessionDeliberationController::requeteListe()
            // (identique, miroir volontaire de ce contrôleur).
            ->when($cycle !== 'tous', fn ($q) => $q->where('cycle', $cycle))
            ->when($request->filled('filiere_id'), fn ($q) => $q->where('filiere_id', $request->integer('filiere_id')));

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — même structure que SessionDeliberationController::colonnesTriables(),
     * ce contrôleur étant un miroir volontaire de celui-ci (voir le docblock
     * de la classe).
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn ($query, string $direction) => $query->orderBy(
                AnneeAcademique::select('libelle')->whereColumn('annees_academiques.id', 'sessions_releves.annee_academique_id'),
                $direction
            ),
            'session' => 'session',
            'cycle' => 'cycle',
            'filiere' => fn ($query, string $direction) => $query->orderBy(
                Filiere::select('libelle')->whereColumn('filieres.id', 'sessions_releves.filiere_id'),
                $direction
            ),
            'fichiers_deposes' => function ($query, string $direction) {
                $query->withCount(['fichiers as fichiers_deposes_count' => fn ($q) => $q->whereNotNull('document_id')])
                    ->orderBy('fichiers_deposes_count', $direction);
            },
            'created_at' => 'created_at',
        ];
    }

    /**
     * Onglet "cycle" valide pour la liste — voir
     * SessionDeliberationController::ongletCycleValide() (identique, miroir
     * volontaire de ce contrôleur).
     */
    protected function ongletCycleValide(Request $request): string
    {
        $valeur = $request->string('cycle')->toString();

        return array_key_exists($valeur, SessionReleve::CYCLES) ? $valeur : 'tous';
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'create'), 403);

        return view('gestion.sessions-releves.form', [
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'sessions' => SessionReleve::SESSIONS,
            'cycles' => SessionReleve::CYCLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'create'), 403);

        $donnees = $request->validate([
            'annee_academique_id' => ['required', Rule::exists('annees_academiques', 'id')],
            'session' => ['required', Rule::in(array_keys(SessionReleve::SESSIONS))],
            'cycle' => ['required', Rule::in(array_keys(SessionReleve::CYCLES))],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $existeDeja = SessionReleve::query()
            ->where('annee_academique_id', $donnees['annee_academique_id'])
            ->where('session', $donnees['session'])
            ->where('cycle', $donnees['cycle'])
            ->where('filiere_id', $donnees['filiere_id'])
            ->exists();

        if ($existeDeja) {
            return back()->withInput()->withErrors([
                'filiere_id' => 'Une session de relevé existe déjà pour cette combinaison année académique / session / cycle / filière.',
            ]);
        }

        $donnees['created_by'] = Auth::id();

        $session = DB::transaction(function () use ($donnees) {
            $session = SessionReleve::create($donnees);
            $this->creerCasesVides($session);

            return $session;
        });

        return redirect()
            ->route('gestion.sessions-releves.show', $session)
            ->with('succes', 'Session de relevé créée. Vous pouvez maintenant y déposer les fichiers, progressivement.');
    }

    public function show(SessionReleve $sessionReleve): View
    {
        $sessionReleve->load(['anneeAcademique', 'filiere', 'creePar', 'fichiers.document', 'fichiers.champSupplementaire']);

        $numerotees = $sessionReleve->fichiers
            ->where('role', $sessionReleve->roleNumerote())
            ->sortBy('numero');

        // Champs supplémentaires ajoutés depuis l'administration (voir
        // App\Models\ChampSupplementaire) — triés par leur "ordre" plutôt que
        // par id, pour que l'administration garde la main sur l'affichage.
        $supplementaires = $sessionReleve->fichiers
            ->where('role', SessionReleveFichier::ROLE_SUPPLEMENTAIRE)
            ->sortBy(fn (SessionReleveFichier $f) => $f->champSupplementaire?->ordre ?? 0);

        $parametres = Parametre::actuel();

        return view('gestion.sessions-releves.show', [
            'session' => $sessionReleve,
            'numerotees' => $numerotees,
            'supplementaires' => $supplementaires,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function update(Request $request, SessionReleve $sessionReleve): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'edit'), 403);

        $donnees = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $sessionReleve->update($donnees);

        return back()->with('succes', 'Description mise à jour.');
    }

    public function destroy(SessionReleve $sessionReleve): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        $sessionReleve->delete();

        return redirect()->route('gestion.sessions-releves.index')->with('succes', 'Session de relevé mise à la corbeille.');
    }

    public function restore(int $sessionReleve): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        SessionReleve::onlyTrashed()->findOrFail($sessionReleve)->restore();

        return back()->with('succes', 'Session de relevé restaurée.');
    }

    public function forceDestroy(int $sessionReleve): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        $session = SessionReleve::onlyTrashed()->with('fichiers')->findOrFail($sessionReleve);

        foreach ($session->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $session->forceDelete();

        return redirect()->route('gestion.sessions-releves.index', ['corbeille' => 1])->with('succes', 'Session de relevé supprimée définitivement.');
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de sessions (cases à
     * cocher, voir resources/views/gestion/sessions-releves/index.blade.php)
     * mises à la corbeille en une seule action — MÊME PRINCIPE que
     * SessionDeliberationController::mettreCorbeilleGroupee(), sur demande
     * explicite du client ("permettre de faire des sélections pour des
     * actions sur les tableaux différents").
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionReleve::query()->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            $session->delete();
        }

        return back()->with('succes', $sessions->count().' session(s) de relevé mise(s) à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionReleve::onlyTrashed()->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            $session->restore();
        }

        return back()->with('succes', $sessions->count().' session(s) de relevé restaurée(s).');
    }

    /**
     * Suppression définitive groupée — MÊME RAISONNEMENT que forceDestroy()
     * ci-dessus : chaque document encore attaché à une case est mis à la
     * corbeille AVANT de supprimer la session elle-même.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionReleve::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            foreach ($session->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $session->forceDelete();
        }

        return back()->with('succes', $sessions->count().' session(s) de relevé supprimée(s) définitivement.');
    }

    public function uploaderFichier(Request $request, SessionReleve $sessionReleve, SessionReleveFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'edit'), 403);
        abort_if($fichier->session_releve_id !== $sessionReleve->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        $typeReleve = $this->typeDocumentReleve();
        abort_if(! $typeReleve, 500, "Le type de document \"RELEVE_NOTES\" est introuvable — contactez l'administrateur.");

        $parametres = Parametre::actuel();
        $tailleMaxKo = $parametres->taille_max_document_mo * 1024;
        $mimesAutorises = TypesFichiers::mimesPour($parametres->types_fichiers_autorises);

        $donnees = $request->validate([
            'fichier' => [
                'required',
                'file',
                'max:'.$tailleMaxKo,
                Rule::when(! empty($mimesAutorises), ['mimetypes:'.implode(',', $mimesAutorises)]),
            ],
        ]);

        $fichierUploade = $donnees['fichier'];
        $chemin = $fichierUploade->store('uploads', 'documents');

        $document = Document::create([
            'description' => $sessionReleve->cycleLibelle().' — '.$sessionReleve->filiere->libelle.' — '.$fichier->libelle(),
            'type_document_id' => $typeReleve->id,
            'sous_type_document_id' => $this->sousTypePour($sessionReleve)?->id,
            'filiere_id' => $sessionReleve->filiere_id,
            'niveau_id' => $this->niveauPour($sessionReleve, $fichier)?->id,
            'annee_academique_id' => $sessionReleve->annee_academique_id,
            'semestre' => $fichier->role === SessionReleveFichier::ROLE_SEMESTRE ? $fichier->numero : null,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Fichier déposé.');
    }

    public function supprimerFichier(SessionReleve $sessionReleve, SessionReleveFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_releves', 'delete'), 403);
        abort_if($fichier->session_releve_id !== $sessionReleve->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Fichier retiré. La case reste disponible pour un nouveau dépôt.');
    }

    protected function creerCasesVides(SessionReleve $session): void
    {
        // Contrairement aux PV de délibération, pas de cases "Note
        // d'information" pour les relevés de notes — demande explicite du
        // client, uniquement les cases numérotées (semestre/année de thèse)
        // ci-dessous, plus une case par champ supplémentaire actif
        // applicable à ce cycle (voir creerCasesChampsSupplementaires()).
        $role = $session->roleNumerote();

        foreach ($session->numerosAttendus() as $numero) {
            SessionReleveFichier::create([
                'session_releve_id' => $session->id,
                'role' => $role,
                'numero' => $numero,
                'document_id' => null,
            ]);
        }

        $this->creerCasesChampsSupplementaires($session);
    }

    /**
     * Cases pour les champs supplémentaires actifs applicables à CE cycle
     * (voir App\Models\ChampSupplementaire) — demande explicite du client,
     * ajoutables depuis Référentiels > Champs supplémentaires sans toucher
     * au code. Les mêmes cases sont ajoutées rétroactivement sur les
     * sessions déjà existantes par
     * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()
     * quand un champ est créé/modifié après coup.
     */
    protected function creerCasesChampsSupplementaires(SessionReleve $session): void
    {
        ChampSupplementaire::query()
            ->where('actif', true)
            ->where('pour_releve', true)
            ->get()
            ->filter(fn (ChampSupplementaire $champ) => in_array($session->cycle, $champ->cycles ?? [], true))
            ->each(function (ChampSupplementaire $champ) use ($session) {
                SessionReleveFichier::create([
                    'session_releve_id' => $session->id,
                    'role' => SessionReleveFichier::ROLE_SUPPLEMENTAIRE,
                    'numero' => null,
                    'champ_supplementaire_id' => $champ->id,
                    'document_id' => null,
                ]);
            });
    }

    /** Type de document RELEVE_NOTES déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentReleve(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'RELEVE_NOTES')->first();
    }

    /** Sous-type "Licence"/"Master" déjà existant (voir SousTypeDocumentSeeder) — le Doctorat n'en a pas. */
    protected function sousTypePour(SessionReleve $session): ?SousTypeDocument
    {
        $type = $this->typeDocumentReleve();

        if (! $type || $session->cycle === SessionReleve::CYCLE_DOCTORAT) {
            return null;
        }

        $libelle = $session->cycle === SessionReleve::CYCLE_MASTER ? 'Master' : 'Licence';

        return SousTypeDocument::query()->where('type_document_id', $type->id)->where('libelle', $libelle)->first();
    }

    /** Niveau (L1...M2) déduit du numéro de semestre — voir Semestres::codeNiveauPourSemestre(). */
    protected function niveauPour(SessionReleve $session, SessionReleveFichier $fichier): ?Niveau
    {
        if ($fichier->role !== SessionReleveFichier::ROLE_SEMESTRE || ! $fichier->numero) {
            return null;
        }

        $code = Semestres::codeNiveauPourSemestre($fichier->numero);

        return $code ? Niveau::query()->where('code', $code)->first() : null;
    }
}
