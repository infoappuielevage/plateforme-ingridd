<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\Document;
use App\Models\EtatFinancier;
use App\Models\Parametre;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Gestion des États financiers — structure dédiée (demande explicite du
 * client, "intègre la gestion des États financières... composé de année
 * ... fichier a uploader, Observation"), MÊME PRINCIPE que
 * ReunionController/PvSoutenanceController avant elle : un vrai
 * enregistrement dédié, et le fichier réellement déposé reste un vrai
 * App\Models\Document.
 *
 * Structure volontairement minimale : contrairement aux autres écrans
 * dédiés (une table de "cases" séparée, un document pouvant en contenir
 * plusieurs), UN SEUL fichier par état financier — `document_id` vit
 * directement sur App\Models\EtatFinancier, pas de table enfant.
 *
 * `annee` est une ANNÉE CIVILE SIMPLE (ex. 2012, 2023 — confirmé
 * explicitement par le client, contrairement au référentiel
 * App\Models\AnneeAcademique utilisé par les autres écrans dédiés) : un
 * entier libre, pas de liste déroulante ni de référentiel à gérer.
 *
 * Le fichier se dépose/se remplace depuis l'écran de détail (voir show()),
 * EXACTEMENT comme la case fixe "rapport" d'App\Http\Controllers\
 * ReunionController — le formulaire de création/modification (voir
 * valider()) ne porte que sur année/observation.
 */
class EtatFinancierController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        // Tri cliquable (demande explicite du client, "intègre le tri sur
        // tous les autres tableaux nouvellement créés") — remplace le
        // orderByDesc('annee') fixe. "fichier" trie sur la présence d'un
        // fichier déposé (document_id), pas une valeur métier à proprement
        // parler, mais c'est bien une colonne affichée dans le tableau (voir
        // resources/views/gestion/etats-financiers/index.blade.php).
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'annee', 'desc');

        $etatsFinanciers = EtatFinancier::query()
            ->with('document')
            ->when($corbeille, fn ($q) => $q->onlyTrashed());

        $this->appliquerTri($etatsFinanciers, $this->colonnesTriables(), [$triActuel, $directionActuelle]);

        $etatsFinanciers = $etatsFinanciers->paginate(10)->withQueryString();

        return view('gestion.etats-financiers.index', [
            'etatsFinanciers' => $etatsFinanciers,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && EtatFinancier::onlyTrashed()->exists(),
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** @return array<string, string> */
    protected function colonnesTriables(): array
    {
        return [
            'annee' => 'annee',
            'fichier' => 'document_id',
            'observation' => 'observation',
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'create'), 403);

        return view('gestion.etats-financiers.form', ['etatFinancier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'create'), 403);

        $donnees = $this->valider($request);
        $donnees['created_by'] = Auth::id();

        $etatFinancier = EtatFinancier::create($donnees);

        return redirect()
            ->route('gestion.etats-financiers.show', $etatFinancier)
            ->with('succes', 'État financier créé. Vous pouvez maintenant y déposer le fichier.');
    }

    public function show(EtatFinancier $etatFinancier): View
    {
        $etatFinancier->load(['document', 'creePar']);

        $parametres = Parametre::actuel();

        return view('gestion.etats-financiers.show', [
            'etatFinancier' => $etatFinancier,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(EtatFinancier $etatFinancier): View
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'edit'), 403);

        return view('gestion.etats-financiers.form', ['etatFinancier' => $etatFinancier]);
    }

    public function update(Request $request, EtatFinancier $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'edit'), 403);

        $etatFinancier->update($this->valider($request, $etatFinancier));

        return redirect()
            ->route('gestion.etats-financiers.show', $etatFinancier)
            ->with('succes', 'État financier modifié.');
    }

    public function destroy(EtatFinancier $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $etatFinancier->delete();

        return redirect()->route('gestion.etats-financiers.index')->with('succes', 'État financier mis à la corbeille.');
    }

    public function restore(int $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        EtatFinancier::onlyTrashed()->findOrFail($etatFinancier)->restore();

        return back()->with('succes', 'État financier restauré.');
    }

    /** Suppression définitive : met aussi le document attaché à la corbeille, même raisonnement que les autres écrans dédiés. */
    public function forceDestroy(int $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $trouve = EtatFinancier::onlyTrashed()->findOrFail($etatFinancier);

        if ($trouve->document_id) {
            Document::query()->find($trouve->document_id)?->delete();
        }

        $trouve->forceDelete();

        return redirect()->route('gestion.etats-financiers.index', ['corbeille' => 1])->with('succes', 'État financier supprimé définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les états
     * financiers corbeillés en un clic — même principe que
     * DocumentController::viderCorbeille().
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $etatsFinanciers = EtatFinancier::onlyTrashed()->get();

        foreach ($etatsFinanciers as $etatFinancier) {
            if ($etatFinancier->document_id) {
                Document::query()->find($etatFinancier->document_id)?->delete();
            }

            $etatFinancier->forceDelete();
        }

        return redirect()
            ->route('gestion.etats-financiers.index', ['corbeille' => 1])
            ->with('succes', $etatsFinanciers->count().' état(s) financier(s) supprimé(s) définitivement.');
    }

    /** Mise à la corbeille groupée — même principe que les autres écrans dédiés (sélection multiple, voir index.blade.php). */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $donnees = $request->validate([
            'etats_financiers' => ['required', 'array', 'min:1'],
            'etats_financiers.*' => ['integer'],
        ]);

        $etatsFinanciers = EtatFinancier::query()->whereIn('id', $donnees['etats_financiers'])->get();

        foreach ($etatsFinanciers as $etatFinancier) {
            $etatFinancier->delete();
        }

        return back()->with('succes', $etatsFinanciers->count().' état(s) financier(s) mis à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $donnees = $request->validate([
            'etats_financiers' => ['required', 'array', 'min:1'],
            'etats_financiers.*' => ['integer'],
        ]);

        $etatsFinanciers = EtatFinancier::onlyTrashed()->whereIn('id', $donnees['etats_financiers'])->get();

        foreach ($etatsFinanciers as $etatFinancier) {
            $etatFinancier->restore();
        }

        return back()->with('succes', $etatsFinanciers->count().' état(s) financier(s) restauré(s).');
    }

    /** Suppression définitive groupée — même raisonnement que forceDestroy() ci-dessus. */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $donnees = $request->validate([
            'etats_financiers' => ['required', 'array', 'min:1'],
            'etats_financiers.*' => ['integer'],
        ]);

        $etatsFinanciers = EtatFinancier::onlyTrashed()->whereIn('id', $donnees['etats_financiers'])->get();

        foreach ($etatsFinanciers as $etatFinancier) {
            if ($etatFinancier->document_id) {
                Document::query()->find($etatFinancier->document_id)?->delete();
            }

            $etatFinancier->forceDelete();
        }

        return back()->with('succes', $etatsFinanciers->count().' état(s) financier(s) supprimé(s) définitivement.');
    }

    /** Dépose (ou remplace) LE fichier de l'état financier — case fixe unique, voir le docblock de la classe. */
    public function uploaderFichier(Request $request, EtatFinancier $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'edit'), 403);

        $reponse = $this->refuserSiVerrouille($etatFinancier->document);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $etatFinancier);

        $ancienDocumentId = $etatFinancier->document_id;
        $etatFinancier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Fichier déposé.');
    }

    /** Retire le fichier déposé — l'état financier reste disponible pour un nouveau dépôt. */
    public function supprimerFichier(EtatFinancier $etatFinancier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etats_financiers', 'delete'), 403);

        $reponse = $this->refuserSiVerrouille($etatFinancier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($etatFinancier->document_id) {
            Document::query()->find($etatFinancier->document_id)?->delete();
            $etatFinancier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Fichier retiré.');
    }

    /** @return array<string, mixed> */
    protected function valider(Request $request, ?EtatFinancier $etatFinancier = null): array
    {
        return $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('etats_financiers', 'annee')->ignore($etatFinancier?->id)],
            'observation' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Dépose le fichier envoyé et crée le App\Models\Document derrière — même
     * principe que ReunionController::creerDocumentDepose().
     */
    protected function creerDocumentDepose(Request $request, EtatFinancier $etatFinancier): Document
    {
        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type ETAT_FINANCIER (voir TypeDocumentSeeder) casserait sinon
        // ce dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $type = $this->typeDocumentEtatFinancier();
        abort_if(! $type, 500, "Le type de document \"ETAT_FINANCIER\" est introuvable — contactez l'administrateur.");

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

        return Document::create([
            'description' => 'État financier '.$etatFinancier->annee,
            'type_document_id' => $type->id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document ETAT_FINANCIER déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentEtatFinancier(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'ETAT_FINANCIER')->first();
    }
}
