<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\SousTypeDocument;
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
 * Gestion des PV de réunion — structure dédiée (demande explicite du
 * client, "avançons sur le PV des réunions"), MÊME PRINCIPE que
 * PvSoutenanceController/SessionDeliberationController/SessionReleveController
 * avant elle : un vrai enregistrement dédié, et chaque fichier réellement
 * déposé reste un vrai App\Models\Document rattaché à une "case" (voir
 * App\Models\ReunionFichier).
 *
 * Contrairement aux trois précédents, AUCUNE pré-création en nombre fixe de
 * cases numérotées : une seule case fixe ("rapport", voir creerCasesVides())
 * plus une liste de pièces jointes EN NOMBRE LIBRE, ajoutées une à une
 * (ajouterPieceJointe()) et retirées entièrement plutôt que simplement
 * vidées (supprimerPieceJointe()) — demande explicite du client ("le
 * fichier du rapport... avec la possibilité d'ajouter d'autres fichiers").
 *
 * Tous les champs restent modifiables après création (voir update()) :
 * contrairement aux PV de délibération/Relevés de notes, rien ici ne
 * détermine un nombre de cases à figer, donc aucune raison de limiter
 * l'édition à la description seule.
 */
class ReunionController extends Controller
{
    public function __construct()
    {
        abort_unless(ResourceAccess::can('reunions', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $requete = Reunion::query()
            ->with(['anneeAcademique', 'sousType', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('annee_academique_id'), fn ($q) => $q->where('annee_academique_id', $request->integer('annee_academique_id')))
            ->when($request->filled('sous_type_document_id'), fn ($q) => $q->where('sous_type_document_id', $request->integer('sous_type_document_id')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where('description', 'like', "%{$terme}%");
            })
            ->latest('date_reunion');

        return view('gestion.reunions.index', [
            'reunions' => $requete->paginate(20)->withQueryString(),
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && Reunion::onlyTrashed()->exists(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'typesReunion' => $this->sousTypesReunion(),
        ]);
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('reunions', 'create'), 403);

        return view('gestion.reunions.form', [
            'reunion' => null,
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'typesReunion' => $this->sousTypesReunion(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'create'), 403);

        $donnees = $this->valider($request);
        $donnees['created_by'] = Auth::id();

        $reunion = DB::transaction(function () use ($donnees) {
            $reunion = Reunion::create($donnees);
            $this->creerCasesVides($reunion);

            return $reunion;
        });

        return redirect()
            ->route('gestion.reunions.show', $reunion)
            ->with('succes', 'Réunion créée. Vous pouvez maintenant y déposer le rapport et d\'éventuelles pièces jointes.');
    }

    public function show(Reunion $reunion): View
    {
        $reunion->load(['anneeAcademique', 'sousType', 'creePar', 'fichiers.document']);

        $rapport = $reunion->fichiers->firstWhere('role', ReunionFichier::ROLE_RAPPORT);

        $piecesJointes = $reunion->fichiers
            ->where('role', ReunionFichier::ROLE_PIECE_JOINTE)
            ->sortBy('numero');

        $parametres = Parametre::actuel();

        return view('gestion.reunions.show', [
            'reunion' => $reunion,
            'rapport' => $rapport,
            'piecesJointes' => $piecesJointes,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(Reunion $reunion): View
    {
        abort_unless(ResourceAccess::can('reunions', 'edit'), 403);

        return view('gestion.reunions.form', [
            'reunion' => $reunion,
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'typesReunion' => $this->sousTypesReunion(),
        ]);
    }

    public function update(Request $request, Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'edit'), 403);

        $donnees = $this->valider($request, $reunion);

        $reunion->update($donnees);

        return redirect()
            ->route('gestion.reunions.show', $reunion)
            ->with('succes', 'Réunion modifiée.');
    }

    public function destroy(Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $reunion->delete();

        return redirect()->route('gestion.reunions.index')->with('succes', 'Réunion mise à la corbeille.');
    }

    public function restore(int $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        Reunion::onlyTrashed()->findOrFail($reunion)->restore();

        return back()->with('succes', 'Réunion restaurée.');
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une case de cette réunion (rapport ou pièce
     * jointe) — même raisonnement que les autres écrans dédiés.
     */
    public function forceDestroy(int $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $reunionTrouvee = Reunion::onlyTrashed()->with('fichiers')->findOrFail($reunion);

        foreach ($reunionTrouvee->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $reunionTrouvee->forceDelete();

        return redirect()->route('gestion.reunions.index', ['corbeille' => 1])->with('succes', 'Réunion supprimée définitivement.');
    }

    /** Dépose (ou remplace) LE rapport de la réunion — case fixe unique, voir le docblock de la classe. */
    public function uploaderRapport(Request $request, Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'edit'), 403);

        $fichier = $reunion->fichiers()->where('role', ReunionFichier::ROLE_RAPPORT)->firstOrFail();

        $document = $this->creerDocumentDepose($request, $reunion, 'Rapport de la réunion');

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Rapport déposé.');
    }

    /** Retire le rapport déposé — la case reste disponible pour un nouveau dépôt (comme les autres écrans dédiés). */
    public function supprimerRapport(Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $fichier = $reunion->fichiers()->where('role', ReunionFichier::ROLE_RAPPORT)->firstOrFail();

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Rapport retiré. La case reste disponible pour un nouveau dépôt.');
    }

    /**
     * Ajoute UNE NOUVELLE pièce jointe (liste en nombre libre, voir le
     * docblock de la classe) : contrairement à `rapport`, aucune case vide
     * n'est pré-créée — la ligne App\Models\ReunionFichier est créée ICI,
     * déjà remplie, avec le numéro suivant (simple compteur d'affichage).
     */
    public function ajouterPieceJointe(Request $request, Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'edit'), 403);

        $libelle = $request->input('libelle');
        $request->validate(['libelle' => ['nullable', 'string', 'max:150']]);

        $document = $this->creerDocumentDepose($request, $reunion, $libelle ?: 'Pièce jointe');

        $numeroSuivant = (int) $reunion->fichiers()->where('role', ReunionFichier::ROLE_PIECE_JOINTE)->max('numero') + 1;

        ReunionFichier::create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_PIECE_JOINTE,
            'numero' => $numeroSuivant,
            'libelle' => $libelle,
            'document_id' => $document->id,
        ]);

        return back()->with('succes', 'Pièce jointe ajoutée.');
    }

    /**
     * Retire ENTIÈREMENT une pièce jointe (document + ligne) — contrairement
     * à supprimerRapport(), il n'y a pas de "case" à garder disponible pour
     * une liste en nombre libre : voir le docblock de la classe.
     */
    public function supprimerPieceJointe(Reunion $reunion, ReunionFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);
        abort_if($fichier->reunion_id !== $reunion->id, 404);
        abort_if($fichier->role !== ReunionFichier::ROLE_PIECE_JOINTE, 404);

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
        }

        $fichier->delete();

        return back()->with('succes', 'Pièce jointe retirée.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function valider(Request $request, ?Reunion $reunion = null): array
    {
        return $request->validate([
            'annee_academique_id' => ['required', Rule::exists('annees_academiques', 'id')],
            'date_reunion' => ['required', 'date'],
            'sous_type_document_id' => ['required', Rule::exists('sous_types_documents', 'id')->where('type_document_id', $this->typeDocumentReunion()?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Crée la case fixe "rapport", vide, à la création de la réunion. */
    protected function creerCasesVides(Reunion $reunion): void
    {
        ReunionFichier::create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
            'numero' => null,
            'libelle' => null,
            'document_id' => null,
        ]);
    }

    /**
     * Dépose le fichier envoyé et crée le App\Models\Document derrière (voir
     * le docblock de la classe) — factorisé entre uploaderRapport() et
     * ajouterPieceJointe(), qui ne diffèrent que par la case/ligne à
     * renseigner après coup.
     */
    protected function creerDocumentDepose(Request $request, Reunion $reunion, string $libelleDescription): Document
    {
        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type PV_REUNION (voir TypeDocumentSeeder) casserait sinon ce
        // dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $type = $this->typeDocumentReunion();
        abort_if(! $type, 500, "Le type de document \"PV_REUNION\" est introuvable — contactez l'administrateur.");

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
            'description' => $reunion->typeLibelle().' du '.$reunion->date_reunion->format('d/m/Y').' — '.$libelleDescription,
            'type_document_id' => $type->id,
            'sous_type_document_id' => $reunion->sous_type_document_id,
            'annee_academique_id' => $reunion->annee_academique_id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document PV_REUNION déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentReunion(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'PV_REUNION')->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SousTypeDocument> */
    protected function sousTypesReunion()
    {
        $type = $this->typeDocumentReunion();

        if (! $type) {
            return collect();
        }

        return SousTypeDocument::query()->where('type_document_id', $type->id)->where('actif', true)->orderBy('ordre')->get();
    }
}
