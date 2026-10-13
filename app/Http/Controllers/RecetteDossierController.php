<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\RecetteDossier;
use App\Models\RecetteDossierFichier;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Gestion des Recettes > Retrait diplôme / Frais de soutenance — demande
 * explicite du client ("le retrait diplome (un fichier exels à intégré...)",
 * "Frais de soutenance (également un fichier exels; pdf etc...)"), UN SEUL
 * contrôleur pour les deux volets (voir le docblock de la migration
 * 2026_10_17_000004_create_recette_dossiers_table.php) : structure
 * strictement identique, seul le libellé/le type change — `$type` arrive
 * comme segment d'URL ("retrait-diplome"|"frais-soutenance", voir
 * routes/web.php) et est résolu vers App\Models\RecetteDossier::TYPE_* par
 * resoudreType() ci-dessous, 404 pour toute autre valeur.
 *
 * IMPORTANT (demande explicite du client) : ces deux volets n'ont AUCUNE
 * relation avec les mécanismes déjà existants par ailleurs (statut_retrait
 * sur App\Models\Document, App\Models\PvSoutenance) — voir le docblock de la
 * migration citée ci-dessus. Simple dépôt de fichiers (typiquement Excel)
 * donnant une vue d'ensemble, sans aucun lien en base.
 *
 * Liste EN NOMBRE LIBRE (comme le rôle `piece_jointe` d'App\Models\
 * ReunionFichier) : aucune case fixe, chaque fichier ajouté crée directement
 * sa ligne.
 */
class RecetteDossierController extends Controller
{
    /** Segment d'URL => constante App\Models\RecetteDossier::TYPE_*. */
    use ListeTriable, VerifieVerrouillage;

    protected const TYPES_URL = [
        'retrait-diplome' => RecetteDossier::TYPE_RETRAIT_DIPLOME,
        'frais-soutenance' => RecetteDossier::TYPE_FRAIS_SOUTENANCE,
    ];

    public function __construct()
    {
        abort_unless(ResourceAccess::can('recettes', 'view'), 403);
    }

    public function index(Request $request, string $type): View
    {
        $typeInterne = $this->resoudreType($type);
        $corbeille = $request->boolean('corbeille');

        $dossiers = RecetteDossier::query()
            ->with(['anneeAcademique', 'fichiers'])
            ->where('type', $typeInterne)
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->get();

        // Tri cliquable (demande explicite du client, "intègre le tri sur
        // tous les autres tableaux nouvellement créés") — voir le docblock
        // de App\Http\Controllers\Concerns\ListeTriable::trierCollection(),
        // même principe que DepenseController::index(). "desc" par défaut :
        // garde le même ordre qu'avant cette fonctionnalité.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'annee_academique', 'desc');
        $dossiers = $this->trierCollection($dossiers, $this->colonnesTriables(), [$triActuel, $directionActuelle]);

        return view('gestion.recettes.dossiers.index', [
            'dossiers' => $dossiers,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && RecetteDossier::onlyTrashed()->where('type', $typeInterne)->exists(),
            'typeUrl' => $type,
            'typeLibelle' => RecetteDossier::TYPES[$typeInterne],
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** @return array<string, \Closure(RecetteDossier): mixed> */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn (RecetteDossier $d) => $d->anneeAcademique?->libelle,
            'fichiers_deposes' => fn (RecetteDossier $d) => $d->fichiers->count(),
        ];
    }

    public function create(string $type): View
    {
        abort_unless(ResourceAccess::can('recettes', 'create'), 403);

        $typeInterne = $this->resoudreType($type);

        $anneesDejaUtilisees = RecetteDossier::query()->where('type', $typeInterne)->pluck('annee_academique_id');

        return view('gestion.recettes.dossiers.form', [
            'typeUrl' => $type,
            'typeLibelle' => RecetteDossier::TYPES[$typeInterne],
            'anneesAcademiques' => AnneeAcademique::query()
                ->whereNotIn('id', $anneesDejaUtilisees)
                ->orderByDesc('libelle')
                ->get(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'create'), 403);

        $typeInterne = $this->resoudreType($type);

        $donnees = $request->validate([
            'annee_academique_id' => [
                'required',
                Rule::exists('annees_academiques', 'id'),
                Rule::unique('recette_dossiers', 'annee_academique_id')->where('type', $typeInterne),
            ],
        ], [
            'annee_academique_id.unique' => 'Un dossier existe déjà pour cette année académique.',
        ]);
        $donnees['type'] = $typeInterne;
        $donnees['created_by'] = Auth::id();

        $dossier = RecetteDossier::create($donnees);

        return redirect()
            ->route('gestion.recettes.dossiers.show', ['type' => $type, 'dossier' => $dossier])
            ->with('succes', 'Dossier créé. Vous pouvez maintenant y déposer des fichiers.');
    }

    public function show(string $type, RecetteDossier $dossier): View
    {
        $this->resoudreType($type);
        abort_if($dossier->type !== self::TYPES_URL[$type], 404);

        $dossier->load(['anneeAcademique', 'fichiers.document']);

        $parametres = Parametre::actuel();

        return view('gestion.recettes.dossiers.show', [
            'dossier' => $dossier,
            'typeUrl' => $type,
            'typeLibelle' => $dossier->typeLibelle(),
            'fichiers' => $dossier->fichiers->sortBy('numero'),
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function destroy(string $type, RecetteDossier $dossier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        $this->resoudreType($type);
        abort_if($dossier->type !== self::TYPES_URL[$type], 404);

        $dossier->delete();

        return redirect()->route('gestion.recettes.dossiers.index', ['type' => $type])->with('succes', 'Dossier mis à la corbeille.');
    }

    public function restore(string $type, int $dossier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        $typeInterne = $this->resoudreType($type);

        $trouve = RecetteDossier::onlyTrashed()->findOrFail($dossier);
        abort_if($trouve->type !== $typeInterne, 404);

        $trouve->restore();

        return back()->with('succes', 'Dossier restauré.');
    }

    /** Suppression définitive : met aussi à la corbeille chaque document encore attaché, même raisonnement que les autres écrans dédiés. */
    public function forceDestroy(string $type, int $dossier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        $typeInterne = $this->resoudreType($type);

        $trouve = RecetteDossier::onlyTrashed()->with('fichiers')->findOrFail($dossier);
        abort_if($trouve->type !== $typeInterne, 404);

        foreach ($trouve->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $trouve->forceDelete();

        return redirect()->route('gestion.recettes.dossiers.index', ['type' => $type, 'corbeille' => 1])->with('succes', 'Dossier supprimé définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les dossiers
     * corbeillés de CE volet (retrait diplôme OU frais de soutenance) en un
     * clic — même principe que DocumentController::viderCorbeille(). Scopé
     * à `$type`, comme le reste de cet écran : ne vide jamais l'AUTRE volet.
     */
    public function viderCorbeille(string $type): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        $typeInterne = $this->resoudreType($type);

        $dossiers = RecetteDossier::onlyTrashed()->with('fichiers')->where('type', $typeInterne)->get();

        foreach ($dossiers as $dossier) {
            foreach ($dossier->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $dossier->forceDelete();
        }

        return redirect()
            ->route('gestion.recettes.dossiers.index', ['type' => $type, 'corbeille' => 1])
            ->with('succes', $dossiers->count().' dossier(s) supprimé(s) définitivement.');
    }

    /** Ajoute UN NOUVEAU fichier à la liste (en nombre libre, voir le docblock de la classe). */
    public function ajouterFichier(Request $request, string $type, RecetteDossier $dossier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'edit'), 403);
        $this->resoudreType($type);
        abort_if($dossier->type !== self::TYPES_URL[$type], 404);

        $libelle = $request->input('libelle');
        $request->validate(['libelle' => ['nullable', 'string', 'max:150']]);

        $document = $this->creerDocumentDepose($request, $dossier, $libelle ?: $dossier->typeLibelle());

        $numeroSuivant = (int) $dossier->fichiers()->max('numero') + 1;

        RecetteDossierFichier::create([
            'recette_dossier_id' => $dossier->id,
            'numero' => $numeroSuivant,
            'libelle' => $libelle,
            'document_id' => $document->id,
        ]);

        return back()->with('succes', 'Fichier ajouté.');
    }

    /** Retire ENTIÈREMENT un fichier de la liste (document + ligne). */
    public function supprimerFichier(string $type, RecetteDossier $dossier, RecetteDossierFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        $this->resoudreType($type);
        abort_if($dossier->type !== self::TYPES_URL[$type], 404);
        abort_if($fichier->recette_dossier_id !== $dossier->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
        }

        $fichier->delete();

        return back()->with('succes', 'Fichier retiré.');
    }

    /** Résout le segment d'URL ("retrait-diplome"|"frais-soutenance") vers la constante interne — 404 pour toute autre valeur (défense en profondeur, en plus de la contrainte de route). */
    protected function resoudreType(string $type): string
    {
        abort_unless(array_key_exists($type, self::TYPES_URL), 404);

        return self::TYPES_URL[$type];
    }

    /** Dépose le fichier envoyé et crée le App\Models\Document derrière — même principe que ReunionController::creerDocumentDepose(). */
    protected function creerDocumentDepose(Request $request, RecetteDossier $dossier, string $libelleDescription): Document
    {
        $typeDocument = $this->typeDocumentRecette();
        abort_if(! $typeDocument, 500, "Le type de document \"RECETTE\" est introuvable — contactez l'administrateur.");

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
            'description' => $dossier->typeLibelle().' '.$dossier->anneeAcademique?->libelle.' — '.$libelleDescription,
            'type_document_id' => $typeDocument->id,
            'annee_academique_id' => $dossier->annee_academique_id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document RECETTE déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentRecette(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'RECETTE')->first();
    }
}
