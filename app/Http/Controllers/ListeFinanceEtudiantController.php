<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\ListeFinanceEtudiant;
use App\Models\ListeFinanceEtudiantFichier;
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
 * "Liste des étudiants (finances)" — demande explicite du client ("intègre
 * également la GESTION de la listes des etudiant afin qu'on puissent avoir
 * la listes des etudiants avec leurs etat de finances par année académique
 * en fichier exel, pdf, etc... dans la partie Finance"). Écran séparé de
 * Gestion des recettes (confirmé via question de clarification), mais
 * STRUCTURE ET CONTRÔLEUR calqués à l'identique sur
 * App\Http\Controllers\RecetteDossierController (un seul type ici, donc pas
 * de segment `{type}`/resoudreType() : voir le docblock de la migration
 * 2026_10_20_000003_create_listes_finances_etudiants_table.php).
 *
 * AUCUNE relation avec App\Models\Etudiant/App\Models\EtudiantFichier (le
 * "vrai" dossier étudiant) — simple dépôt de fichiers (Excel, PDF...) par
 * année académique, donnant une vue d'ensemble.
 */
class ListeFinanceEtudiantController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $listes = ListeFinanceEtudiant::query()
            ->with(['anneeAcademique', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->get();

        // Tri cliquable (demande explicite du client, "intègre le tri sur
        // tous les autres tableaux nouvellement créés") — voir le docblock
        // de App\Http\Controllers\Concerns\ListeTriable::trierCollection(),
        // même principe que DepenseController::index(). "desc" par défaut :
        // garde le même ordre qu'avant cette fonctionnalité.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'annee_academique', 'desc');
        $listes = $this->trierCollection($listes, $this->colonnesTriables(), [$triActuel, $directionActuelle]);

        return view('gestion.listes-finances-etudiants.index', [
            'listes' => $listes,
            'corbeille' => $corbeille,
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** @return array<string, \Closure(ListeFinanceEtudiant): mixed> */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn (ListeFinanceEtudiant $l) => $l->anneeAcademique?->libelle,
            'fichiers_deposes' => fn (ListeFinanceEtudiant $l) => $l->fichiers->count(),
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'create'), 403);

        $anneesDejaUtilisees = ListeFinanceEtudiant::query()->pluck('annee_academique_id');

        return view('gestion.listes-finances-etudiants.form', [
            'anneesAcademiques' => AnneeAcademique::query()
                ->whereNotIn('id', $anneesDejaUtilisees)
                ->orderByDesc('libelle')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'create'), 403);

        $donnees = $request->validate([
            'annee_academique_id' => [
                'required',
                Rule::exists('annees_academiques', 'id'),
                Rule::unique('listes_finances_etudiants', 'annee_academique_id'),
            ],
        ], [
            'annee_academique_id.unique' => 'Une liste existe déjà pour cette année académique.',
        ]);
        $donnees['created_by'] = Auth::id();

        $liste = ListeFinanceEtudiant::create($donnees);

        return redirect()
            ->route('gestion.listes-finances-etudiants.show', $liste)
            ->with('succes', 'Liste créée. Vous pouvez maintenant y déposer des fichiers.');
    }

    public function show(ListeFinanceEtudiant $listeFinanceEtudiant): View
    {
        $listeFinanceEtudiant->load(['anneeAcademique', 'fichiers.document']);

        $parametres = Parametre::actuel();

        return view('gestion.listes-finances-etudiants.show', [
            'liste' => $listeFinanceEtudiant,
            'fichiers' => $listeFinanceEtudiant->fichiers->sortBy('numero'),
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function destroy(ListeFinanceEtudiant $listeFinanceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'delete'), 403);

        $listeFinanceEtudiant->delete(); // $listeFinanceEtudiant lié par liaison de modèle implicite (non corbeillé à ce stade)

        return redirect()->route('gestion.listes-finances-etudiants.index')->with('succes', 'Liste mise à la corbeille.');
    }

    public function restore(int $listeFinanceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'delete'), 403);

        ListeFinanceEtudiant::onlyTrashed()->findOrFail($listeFinanceEtudiant)->restore();

        return back()->with('succes', 'Liste restaurée.');
    }

    /** Suppression définitive : met aussi à la corbeille chaque document encore attaché, même raisonnement que les autres écrans dédiés. */
    public function forceDestroy(int $listeFinanceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'delete'), 403);

        $trouve = ListeFinanceEtudiant::onlyTrashed()->with('fichiers')->findOrFail($listeFinanceEtudiant);

        foreach ($trouve->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $trouve->forceDelete();

        return redirect()->route('gestion.listes-finances-etudiants.index', ['corbeille' => 1])->with('succes', 'Liste supprimée définitivement.');
    }

    /** Ajoute UN NOUVEAU fichier à la liste (en nombre libre, voir le docblock de la classe). */
    public function ajouterFichier(Request $request, ListeFinanceEtudiant $listeFinanceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'edit'), 403);

        $libelle = $request->input('libelle');
        $request->validate(['libelle' => ['nullable', 'string', 'max:150']]);

        $document = $this->creerDocumentDepose($request, $listeFinanceEtudiant, $libelle ?: 'Liste des étudiants (finances)');

        $numeroSuivant = (int) $listeFinanceEtudiant->fichiers()->max('numero') + 1;

        ListeFinanceEtudiantFichier::create([
            'liste_finance_etudiant_id' => $listeFinanceEtudiant->id,
            'numero' => $numeroSuivant,
            'libelle' => $libelle,
            'document_id' => $document->id,
        ]);

        return back()->with('succes', 'Fichier ajouté.');
    }

    /** Retire ENTIÈREMENT un fichier de la liste (document + ligne). */
    public function supprimerFichier(ListeFinanceEtudiant $listeFinanceEtudiant, ListeFinanceEtudiantFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('listes_finances_etudiants', 'delete'), 403);
        abort_if($fichier->liste_finance_etudiant_id !== $listeFinanceEtudiant->id, 404);

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

    /** Dépose le fichier envoyé et crée le App\Models\Document derrière — même principe que RecetteDossierController::creerDocumentDepose(). */
    protected function creerDocumentDepose(Request $request, ListeFinanceEtudiant $liste, string $libelleDescription): Document
    {
        $typeDocument = $this->typeDocumentListeFinance();
        abort_if(! $typeDocument, 500, "Le type de document \"LISTE_ETUDIANTS_FINANCES\" est introuvable — contactez l'administrateur.");

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
            'description' => 'Liste des étudiants (finances) '.$liste->anneeAcademique?->libelle.' — '.$libelleDescription,
            'type_document_id' => $typeDocument->id,
            'annee_academique_id' => $liste->annee_academique_id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document LISTE_ETUDIANTS_FINANCES déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentListeFinance(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'LISTE_ETUDIANTS_FINANCES')->first();
    }
}
