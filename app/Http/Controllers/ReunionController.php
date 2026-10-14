<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('reunions', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $anneesAcademiques = AnneeAcademique::query()->orderByDesc('libelle')->get();
        $anneeActifId = $this->ongletAnneeValide($request, $anneesAcademiques);

        $reunions = $this->requeteListe($request, $corbeille, $anneeActifId)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'date_reunion', 'desc');

        return view('gestion.reunions.index', [
            'reunions' => $reunions,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && Reunion::onlyTrashed()->exists(),
            'anneesAcademiques' => $anneesAcademiques,
            'anneeActifId' => $anneeActifId,
            'typesReunion' => $this->sousTypesReunion(),
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
        $anneeActifId = $this->ongletAnneeValide($request, AnneeAcademique::query()->orderByDesc('libelle')->get());
        $reunions = $this->requeteListe($request, $corbeille, $anneeActifId)->get();

        $lignes = $reunions->map(fn (Reunion $reunion) => [
            $reunion->date_reunion?->format('d/m/Y'),
            $reunion->anneeAcademique?->libelle,
            $reunion->typeLibelle(),
            $reunion->description,
        ]);

        return FichiersExcel::exporter(
            'reunions-'.now()->format('Y-m-d').'.xlsx',
            ['Date', 'Année académique', 'Type', 'Description'],
            $lignes
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Reunion> */
    protected function requeteListe(Request $request, bool $corbeille, ?int $anneeActifId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Reunion::query()
            ->with(['anneeAcademique', 'sousType', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            // Onglet "année académique" (voir index()/resources/views/gestion/reunions/index.blade.php)
            // — remplace l'ancien filtre "Année académique" à liste déroulante,
            // même principe que PvSoutenanceController::requeteListe() pour
            // "niveau" (demande explicite du client : "fait de même pour les
            // PV de réunion pour les couleurs et pour le tableau fait par
            // année académique"). null = aucun filtre ("Toutes").
            ->when($anneeActifId !== null, fn ($q) => $q->where('annee_academique_id', $anneeActifId))
            ->when($request->filled('sous_type_document_id'), fn ($q) => $q->where('sous_type_document_id', $request->integer('sous_type_document_id')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where('description', 'like', "%{$terme}%");
            });

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'date_reunion', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Type" affiche typeLibelle(), dérivé de la relation sousType (voir
     * App\Models\SousTypeDocument), d'où la sous-requête corrélée plutôt
     * qu'un orderBy direct sur sous_type_document_id (qui trierait sur l'id,
     * pas sur le libellé affiché). "Fichiers déposés" est un compteur
     * calculé (withCount), pas une colonne réelle.
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'date_reunion' => 'date_reunion',
            'annee_academique' => fn ($query, string $direction) => $query->orderBy(
                AnneeAcademique::select('libelle')->whereColumn('annees_academiques.id', 'reunions.annee_academique_id'),
                $direction
            ),
            'type' => fn ($query, string $direction) => $query->orderBy(
                SousTypeDocument::select('libelle')->whereColumn('sous_types_documents.id', 'reunions.sous_type_document_id'),
                $direction
            ),
            'description' => 'description',
            'fichiers_deposes' => function ($query, string $direction) {
                $query->withCount(['fichiers as fichiers_deposes_count' => fn ($q) => $q->whereNotNull('document_id')])
                    ->orderBy('fichiers_deposes_count', $direction);
            },
        ];
    }

    /**
     * Onglet "année académique" valide pour la liste — null ("Toutes",
     * aucun filtre) ou l'id d'une AnneeAcademique RÉELLEMENT existante
     * (contrairement à PvSoutenanceController::ongletNiveauValide(), qui
     * compare à une poignée de valeurs PHP fixes, ce référentiel est ouvert
     * et stocké en base : on vérifie donc contre $anneesAcademiques plutôt
     * qu'un tableau de constantes). Un lien externe/favori obsolète pointant
     * vers une année supprimée retombe silencieusement sur "Toutes" plutôt
     * qu'une erreur SQL.
     *
     * @param \Illuminate\Support\Collection<int, AnneeAcademique> $anneesAcademiques
     */
    protected function ongletAnneeValide(Request $request, $anneesAcademiques): ?int
    {
        $id = $request->integer('annee_academique_id');

        return $id !== 0 && $anneesAcademiques->contains('id', $id) ? $id : null;
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
        $this->mettreFichiersALaCorbeille($reunion);

        return redirect()->route('gestion.reunions.index')->with('succes', 'Réunion mise à la corbeille.');
    }

    public function restore(int $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $reunionTrouvee = Reunion::onlyTrashed()->findOrFail($reunion);
        $reunionTrouvee->restore();
        $this->restaurerFichiers($reunionTrouvee);

        return back()->with('succes', 'Réunion restaurée.');
    }

    /**
     * Cascade MISE À LA CORBEILLE — demande explicite du client ("cascade
     * incohérente" : signalé parce qu'auparavant, seule la suppression
     * DÉFINITIVE (voir forceDestroy()/viderCorbeille()) mettait les
     * documents attachés à la corbeille ; les mettre simplement à la
     * corbeille (cette méthode) laissait leurs documents actifs, visibles
     * dans resources/views/gestion/documents/index.blade.php, alors que la
     * réunion qui les référence avait déjà disparu de la sienne. Désormais
     * les deux étapes (corbeille ET suppression définitive) sont
     * cohérentes : un document suit son parent dès sa mise à la corbeille.
     *
     * Ignore un document déjà à la corbeille (`! trashed()`) : le
     * resoumettre à delete() avancerait inutilement sa date de mise à la
     * corbeille — pertinent s'il a été corbeillé indépendamment avant la
     * réunion elle-même.
     */
    protected function mettreFichiersALaCorbeille(Reunion $reunion): void
    {
        $reunion->loadMissing('fichiers');

        foreach ($reunion->fichiers as $fichier) {
            if (! $fichier->document_id) {
                continue;
            }

            $document = Document::query()->find($fichier->document_id);
            if ($document && ! $document->trashed()) {
                $document->delete();
            }
        }
    }

    /**
     * Cascade RESTAURATION, symétrique à mettreFichiersALaCorbeille()
     * ci-dessus — pour que "Restaurer" une réunion restaure aussi ses
     * documents, cohérent avec le fait qu'ils l'avaient suivie à la
     * corbeille. Cas limite accepté (même simplicité que le reste de cette
     * cascade) : un document corbeillé INDÉPENDAMMENT avant la réunion
     * serait, lui aussi, restauré avec elle.
     */
    protected function restaurerFichiers(Reunion $reunion): void
    {
        $reunion->loadMissing('fichiers');

        foreach ($reunion->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::onlyTrashed()->find($fichier->document_id)?->restore();
            }
        }
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une case de cette réunion (rapport ou pièce
     * jointe) — même raisonnement que les autres écrans dédiés.
     */
    public function forceDestroy(int $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete_definitif'), 403);

        $reunionTrouvee = Reunion::onlyTrashed()->with('fichiers')->findOrFail($reunion);

        foreach ($reunionTrouvee->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $reunionTrouvee->forceDelete();

        return redirect()->route('gestion.reunions.index', ['corbeille' => 1])->with('succes', 'Réunion supprimée définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUTES les réunions
     * corbeillées en un clic — même principe que DocumentController::
     * viderCorbeille(). Porte sur TOUTE la corbeille (voir
     * `corbeillePeutEtreVidee` dans index()), jamais seulement la page
     * affichée.
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete_definitif'), 403);

        $reunions = Reunion::onlyTrashed()->with('fichiers')->get();

        foreach ($reunions as $reunion) {
            foreach ($reunion->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $reunion->forceDelete();
        }

        return redirect()
            ->route('gestion.reunions.index', ['corbeille' => 1])
            ->with('succes', $reunions->count().' réunion(s) supprimée(s) définitivement.');
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique, voir Administration > Paramètres
     * > Corbeilles) : MÊME cascade (documents attachés soft-supprimés),
     * limitée aux réunions mises à la corbeille depuis plus de $jours jours.
     * Appelée depuis la console (aucun utilisateur authentifié) — sans
     * abort_unless(ResourceAccess::...) ni redirection, juste un compteur.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $reunions = Reunion::onlyTrashed()->with('fichiers')->where('deleted_at', '<', now()->subDays($jours))->get();

        foreach ($reunions as $reunion) {
            foreach ($reunion->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $reunion->forceDelete();
        }

        return ['supprimes' => $reunions->count(), 'ignores' => 0];
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de réunions (cases à
     * cocher, voir resources/views/gestion/reunions/index.blade.php) mises à
     * la corbeille en une seule action — MÊME PRINCIPE que
     * DocumentController::mettreCorbeilleGroupee(), sur demande explicite du
     * client ("permettre de faire des sélections pour des actions sur les
     * tableaux différents").
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $donnees = $request->validate([
            'reunions' => ['required', 'array', 'min:1'],
            'reunions.*' => ['integer'],
        ]);

        $reunions = Reunion::query()->with('fichiers')->whereIn('id', $donnees['reunions'])->get();

        foreach ($reunions as $reunion) {
            $reunion->delete();
            $this->mettreFichiersALaCorbeille($reunion);
        }

        return back()->with('succes', $reunions->count().' réunion(s) mise(s) à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete'), 403);

        $donnees = $request->validate([
            'reunions' => ['required', 'array', 'min:1'],
            'reunions.*' => ['integer'],
        ]);

        $reunions = Reunion::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['reunions'])->get();

        foreach ($reunions as $reunion) {
            $reunion->restore();
            $this->restaurerFichiers($reunion);
        }

        return back()->with('succes', $reunions->count().' réunion(s) restaurée(s).');
    }

    /**
     * Suppression définitive groupée — MÊME RAISONNEMENT que forceDestroy()
     * ci-dessus : chaque document encore attaché (rapport ou pièce jointe)
     * est mis à la corbeille AVANT de supprimer la réunion elle-même.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'delete_definitif'), 403);

        $donnees = $request->validate([
            'reunions' => ['required', 'array', 'min:1'],
            'reunions.*' => ['integer'],
        ]);

        $reunions = Reunion::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['reunions'])->get();

        foreach ($reunions as $reunion) {
            foreach ($reunion->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $reunion->forceDelete();
        }

        return back()->with('succes', $reunions->count().' réunion(s) supprimée(s) définitivement.');
    }

    /** Dépose (ou remplace) LE rapport de la réunion — case fixe unique, voir le docblock de la classe. */
    public function uploaderRapport(Request $request, Reunion $reunion): RedirectResponse
    {
        abort_unless(ResourceAccess::can('reunions', 'edit'), 403);

        $fichier = $reunion->fichiers()->where('role', ReunionFichier::ROLE_RAPPORT)->firstOrFail();

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

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

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

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

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

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
