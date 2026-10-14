<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\RecetteScolarite;
use App\Models\RecetteScolariteFichier;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestion des Recettes > Scolarité — structure dédiée (demande explicite du
 * client, "intègre la Gestion des Recette qui est composer de : la
 * Scolarité (composer de : Bordereau de versement... Relevé Orange Money ;
 * Relevé Moov Money ; Relevé Telecel Money ; autres documents)"), MÊME
 * PRINCIPE que ReunionController avant elle : un vrai enregistrement dédié,
 * un par ANNÉE ACADÉMIQUE, et chaque fichier réellement déposé reste un vrai
 * App\Models\Document rattaché à une "case" (voir
 * App\Models\RecetteScolariteFichier).
 *
 * Partage sa clé ResourceAccess ("recettes") et son type de document
 * ("RECETTE") avec App\Http\Controllers\RecetteDossierController — voir le
 * docblock d'App\Support\TypesEcranDedie : les trois volets de Gestion des
 * Recettes forment UN SEUL module aux yeux des droits d'accès, l'admin
 * choisit "2 écrans, avec onglets internes" (voir
 * resources/views/gestion/recettes/_onglets.blade.php).
 *
 * Aucun champ modifiable après création (contrairement à ReunionController) :
 * un dossier Scolarité n'a qu'un seul champ structurant (l'année académique,
 * unique), rien à corriger après coup — pas de edit()/update() ici.
 */
class RecetteScolariteController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('recettes', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $recettesScolarite = RecetteScolarite::query()
            ->with(['anneeAcademique', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->get();

        // Tri cliquable (demande explicite du client, "intègre le tri sur
        // tous les autres tableaux nouvellement créés") — voir le docblock
        // de App\Http\Controllers\Concerns\ListeTriable::trierCollection(),
        // même principe que DepenseController::index(). "fichiers_deposes"
        // trie sur le nombre RÉELLEMENT déposé (le numérateur affiché, voir
        // resources/views/gestion/recettes/scolarite/index.blade.php), pas
        // le nombre total de cases. "desc" par défaut : garde le même ordre
        // qu'avant cette fonctionnalité.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'annee_academique', 'desc');
        $recettesScolarite = $this->trierCollection($recettesScolarite, $this->colonnesTriables(), [$triActuel, $directionActuelle]);

        return view('gestion.recettes.scolarite.index', [
            'recettesScolarite' => $recettesScolarite,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && RecetteScolarite::onlyTrashed()->exists(),
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** @return array<string, \Closure(RecetteScolarite): mixed> */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn (RecetteScolarite $r) => $r->anneeAcademique?->libelle,
            'fichiers_deposes' => fn (RecetteScolarite $r) => $r->fichiers->whereNotNull('document_id')->count(),
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('recettes', 'create'), 403);

        $anneesDejaUtilisees = RecetteScolarite::query()->pluck('annee_academique_id');

        return view('gestion.recettes.scolarite.form', [
            'anneesAcademiques' => AnneeAcademique::query()
                ->whereNotIn('id', $anneesDejaUtilisees)
                ->orderByDesc('libelle')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'create'), 403);

        $donnees = $request->validate([
            'annee_academique_id' => ['required', Rule::exists('annees_academiques', 'id'), Rule::unique('recette_scolarites', 'annee_academique_id')],
        ], [
            'annee_academique_id.unique' => 'Un dossier Scolarité existe déjà pour cette année académique.',
        ]);
        $donnees['created_by'] = Auth::id();

        $recetteScolarite = DB::transaction(function () use ($donnees) {
            $recetteScolarite = RecetteScolarite::create($donnees);
            $this->creerCasesFixes($recetteScolarite);

            return $recetteScolarite;
        });

        return redirect()
            ->route('gestion.recettes.scolarite.show', $recetteScolarite)
            ->with('succes', 'Dossier Scolarité créé. Vous pouvez maintenant y déposer les bordereaux et relevés.');
    }

    public function show(RecetteScolarite $recetteScolarite): View
    {
        $recetteScolarite->load(['anneeAcademique', 'fichiers.document']);

        $orangeMoney = $recetteScolarite->fichiers->firstWhere('role', RecetteScolariteFichier::ROLE_ORANGE_MONEY);
        $moovMoney = $recetteScolarite->fichiers->firstWhere('role', RecetteScolariteFichier::ROLE_MOOV_MONEY);
        $telecelMoney = $recetteScolarite->fichiers->firstWhere('role', RecetteScolariteFichier::ROLE_TELECEL_MONEY);
        $bordereaux = $recetteScolarite->fichiers->where('role', RecetteScolariteFichier::ROLE_BORDEREAU)->sortBy('numero');
        $autres = $recetteScolarite->fichiers->where('role', RecetteScolariteFichier::ROLE_AUTRE)->sortBy('numero');

        $parametres = Parametre::actuel();

        return view('gestion.recettes.scolarite.show', [
            'recetteScolarite' => $recetteScolarite,
            'orangeMoney' => $orangeMoney,
            'moovMoney' => $moovMoney,
            'telecelMoney' => $telecelMoney,
            'bordereaux' => $bordereaux,
            'autres' => $autres,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function destroy(RecetteScolarite $recetteScolarite): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);

        $recetteScolarite->delete();
        $this->mettreFichiersALaCorbeille($recetteScolarite);

        return redirect()->route('gestion.recettes.scolarite.index')->with('succes', 'Dossier Scolarité mis à la corbeille.');
    }

    public function restore(int $recetteScolarite): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);

        $dossierTrouve = RecetteScolarite::onlyTrashed()->findOrFail($recetteScolarite);
        $dossierTrouve->restore();
        $this->restaurerFichiers($dossierTrouve);

        return back()->with('succes', 'Dossier Scolarité restauré.');
    }

    /**
     * Cascade MISE À LA CORBEILLE — demande explicite du client ("cascade
     * incohérente" : voir le docblock équivalent de ReunionController pour
     * le détail du problème signalé). Même garde-fou (document déjà à la
     * corbeille ignoré).
     */
    protected function mettreFichiersALaCorbeille(RecetteScolarite $recetteScolarite): void
    {
        $recetteScolarite->loadMissing('fichiers');

        foreach ($recetteScolarite->fichiers as $fichier) {
            if (! $fichier->document_id) {
                continue;
            }

            $document = Document::query()->find($fichier->document_id);
            if ($document && ! $document->trashed()) {
                $document->delete();
            }
        }
    }

    /** Cascade RESTAURATION, symétrique à mettreFichiersALaCorbeille() ci-dessus. */
    protected function restaurerFichiers(RecetteScolarite $recetteScolarite): void
    {
        $recetteScolarite->loadMissing('fichiers');

        foreach ($recetteScolarite->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::onlyTrashed()->find($fichier->document_id)?->restore();
            }
        }
    }

    /** Suppression définitive : met aussi à la corbeille chaque document encore attaché à une case, même raisonnement que les autres écrans dédiés. */
    public function forceDestroy(int $recetteScolarite): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);

        $trouve = RecetteScolarite::onlyTrashed()->with('fichiers')->findOrFail($recetteScolarite);

        foreach ($trouve->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $trouve->forceDelete();

        return redirect()->route('gestion.recettes.scolarite.index', ['corbeille' => 1])->with('succes', 'Dossier Scolarité supprimé définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les dossiers
     * Scolarité corbeillés en un clic — même principe que
     * DocumentController::viderCorbeille().
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);

        $dossiers = RecetteScolarite::onlyTrashed()->with('fichiers')->get();

        foreach ($dossiers as $dossier) {
            foreach ($dossier->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $dossier->forceDelete();
        }

        return redirect()
            ->route('gestion.recettes.scolarite.index', ['corbeille' => 1])
            ->with('succes', $dossiers->count().' dossier(s) Scolarité supprimé(s) définitivement.');
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique) : MÊME cascade, limitée aux
     * dossiers mis à la corbeille depuis plus de $jours jours. Appelée
     * depuis la console (aucun utilisateur authentifié) — sans
     * abort_unless ni redirection.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $dossiers = RecetteScolarite::onlyTrashed()->with('fichiers')->where('deleted_at', '<', now()->subDays($jours))->get();

        foreach ($dossiers as $dossier) {
            foreach ($dossier->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $dossier->forceDelete();
        }

        return ['supprimes' => $dossiers->count(), 'ignores' => 0];
    }

    /**
     * Dépose (ou remplace) une des trois cases FIXES (Orange/Moov/Telecel
     * Money) — $role validé contre RecetteScolariteFichier::ROLES_FIXES,
     * 404 sinon (lien trafiqué).
     */
    public function uploaderCaseFixe(Request $request, RecetteScolarite $recetteScolarite, string $role): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'edit'), 403);
        abort_unless(in_array($role, RecetteScolariteFichier::ROLES_FIXES, true), 404);

        $fichier = $recetteScolarite->fichiers()->where('role', $role)->firstOrFail();

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $recetteScolarite, $fichier->libelle());

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Fichier déposé.');
    }

    /** Retire une case fixe déposée — la case reste disponible pour un nouveau dépôt. */
    public function supprimerCaseFixe(RecetteScolarite $recetteScolarite, string $role): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        abort_unless(in_array($role, RecetteScolariteFichier::ROLES_FIXES, true), 404);

        $fichier = $recetteScolarite->fichiers()->where('role', $role)->firstOrFail();

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

    /**
     * Ajoute UN NOUVEL élément à une des deux listes EN NOMBRE LIBRE
     * (bordereau/autre) — $role validé contre les deux seuls rôles de liste
     * libre, 404 sinon.
     */
    public function ajouterDocumentListe(Request $request, RecetteScolarite $recetteScolarite, string $role): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'edit'), 403);
        abort_unless(in_array($role, [RecetteScolariteFichier::ROLE_BORDEREAU, RecetteScolariteFichier::ROLE_AUTRE], true), 404);

        $libelle = $request->input('libelle');
        $request->validate(['libelle' => ['nullable', 'string', 'max:150']]);

        $numeroSuivant = (int) $recetteScolarite->fichiers()->where('role', $role)->max('numero') + 1;

        $libelleDescription = $role === RecetteScolariteFichier::ROLE_BORDEREAU
            ? 'Bordereau '.$numeroSuivant
            : ($libelle ?: 'Document scolarité');

        $document = $this->creerDocumentDepose($request, $recetteScolarite, $libelleDescription);

        RecetteScolariteFichier::create([
            'recette_scolarite_id' => $recetteScolarite->id,
            'role' => $role,
            'numero' => $numeroSuivant,
            'libelle' => $role === RecetteScolariteFichier::ROLE_AUTRE ? $libelle : null,
            'document_id' => $document->id,
        ]);

        return back()->with('succes', 'Document ajouté.');
    }

    /** Retire ENTIÈREMENT un élément d'une liste libre (document + ligne). */
    public function supprimerDocumentListe(RecetteScolarite $recetteScolarite, RecetteScolariteFichier $fichier, string $role): RedirectResponse
    {
        abort_unless(ResourceAccess::can('recettes', 'delete'), 403);
        abort_if($fichier->recette_scolarite_id !== $recetteScolarite->id, 404);
        abort_if($fichier->role !== $role, 404);
        abort_unless(in_array($role, [RecetteScolariteFichier::ROLE_BORDEREAU, RecetteScolariteFichier::ROLE_AUTRE], true), 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
        }

        $fichier->delete();

        return back()->with('succes', 'Document retiré.');
    }

    /** Pré-crée les trois cases fixes (Orange/Moov/Telecel Money), vides, à la création du dossier. */
    protected function creerCasesFixes(RecetteScolarite $recetteScolarite): void
    {
        foreach (RecetteScolariteFichier::ROLES_FIXES as $role) {
            RecetteScolariteFichier::create([
                'recette_scolarite_id' => $recetteScolarite->id,
                'role' => $role,
                'numero' => null,
                'libelle' => null,
                'document_id' => null,
            ]);
        }
    }

    /** Dépose le fichier envoyé et crée le App\Models\Document derrière — même principe que ReunionController::creerDocumentDepose(). */
    protected function creerDocumentDepose(Request $request, RecetteScolarite $recetteScolarite, string $libelleDescription): Document
    {
        $type = $this->typeDocumentRecette();
        abort_if(! $type, 500, "Le type de document \"RECETTE\" est introuvable — contactez l'administrateur.");

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
            'description' => 'Scolarité '.$recetteScolarite->anneeAcademique?->libelle.' — '.$libelleDescription,
            'type_document_id' => $type->id,
            'annee_academique_id' => $recetteScolarite->annee_academique_id,
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
