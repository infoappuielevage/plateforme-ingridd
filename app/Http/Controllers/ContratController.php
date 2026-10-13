<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\Contrat;
use App\Models\ContratFichier;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\TypeContrat;
use App\Models\TypeDocument;
use App\Models\User;
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
 * Gestion des contrats — demande explicite du client ("intègre à présent la
 * gestion des contrat, on aurait les partenariat... les contrat de
 * prestation de Service, et tout autre contrat dont tu pense que c'est
 * necessaire"), confirmée via la question de clarification posée avant les
 * migrations : un SEUL écran avec un onglet par catégorie (voir
 * App\Models\TypeContrat, route `{typeContrat:code}` — EXACTEMENT le même
 * principe que App\Http\Controllers\DepenseController, liaison de modèle
 * directement sur `code` plutôt qu'une regex fixe : les catégories sont une
 * liste OUVERTE, gérée depuis l'administration, voir types-contrats
 * ci-dessous).
 *
 * Contrairement à App\Models\Depense (une ligne par année académique ×
 * catégorie, créée automatiquement), un contrat est créé À LA DEMANDE —
 * MÊME PRINCIPE que App\Http\Controllers\ReunionController : un vrai
 * enregistrement dédié, une case fixe pour le contrat signé
 * (`contrat_signe`, voir creerCasesVides()) plus une liste d'avenants EN
 * NOMBRE LIBRE (ajouterAvenant()/supprimerAvenant()).
 *
 * Tous les champs restent modifiables après création (voir update()) :
 * rien ici ne détermine un nombre de cases à figer.
 */
class ContratController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('contrats', 'view'), 403);
    }

    public function index(Request $request, TypeContrat $typeContrat): View
    {
        abort_if(! $typeContrat->actif, 404);

        $corbeille = $request->boolean('corbeille');

        $contrats = $this->requeteListe($request, $typeContrat, $corbeille)->paginate(8)->withQueryString();

        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc');

        return view('gestion.contrats.index', [
            'typeContrat' => $typeContrat,
            'categories' => $this->categoriesActives(),
            'contrats' => $contrats,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && Contrat::onlyTrashed()->where('type_contrat_id', $typeContrat->id)->exists(),
            'statuts' => Contrat::STATUTS,
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** Export Excel (.xlsx) de la liste — mêmes filtres que l'écran (voir requeteListe()), sans pagination. */
    public function export(Request $request, TypeContrat $typeContrat): StreamedResponse
    {
        $corbeille = $request->boolean('corbeille');
        $contrats = $this->requeteListe($request, $typeContrat, $corbeille)->get();

        $lignes = $contrats->map(fn (Contrat $contrat) => [
            $contrat->partenaire_prestataire,
            $contrat->objet,
            $contrat->montantFormate(),
            $contrat->date_signature?->format('d/m/Y'),
            $contrat->date_debut?->format('d/m/Y'),
            $contrat->date_fin?->format('d/m/Y'),
            $contrat->statutLibelle(),
            $contrat->responsable?->name,
        ]);

        return FichiersExcel::exporter(
            'contrats-'.$typeContrat->code.'-'.now()->format('Y-m-d').'.xlsx',
            ['Partenaire / Prestataire', 'Objet', 'Montant', 'Date de signature', 'Date de début', 'Date de fin', 'Statut', 'Responsable'],
            $lignes
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Contrat> */
    protected function requeteListe(Request $request, TypeContrat $typeContrat, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contrat::query()
            ->with(['typeContrat', 'responsable', 'fichiers'])
            ->where('type_contrat_id', $typeContrat->id)
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where(fn ($qq) => $qq->where('partenaire_prestataire', 'like', "%{$terme}%")
                    ->orWhere('objet', 'like', "%{$terme}%"));
            });

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Fichiers déposés" est un compteur calculé (withCount), pas une
     * colonne réelle, même principe que ReunionController::colonnesTriables().
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'partenaire_prestataire' => 'partenaire_prestataire',
            'objet' => 'objet',
            'montant' => 'montant',
            'date_signature' => 'date_signature',
            'date_debut' => 'date_debut',
            'date_fin' => 'date_fin',
            'statut' => 'statut',
            'created_at' => 'created_at',
            'fichiers_deposes' => function ($query, string $direction) {
                $query->withCount(['fichiers as fichiers_deposes_count' => fn ($q) => $q->whereNotNull('document_id')])
                    ->orderBy('fichiers_deposes_count', $direction);
            },
        ];
    }

    public function create(TypeContrat $typeContrat): View
    {
        abort_unless(ResourceAccess::can('contrats', 'create'), 403);
        abort_if(! $typeContrat->actif, 404);

        return view('gestion.contrats.form', [
            'typeContrat' => $typeContrat,
            'contrat' => null,
            'statuts' => Contrat::STATUTS,
            'responsablesPossibles' => $this->responsablesPossibles(),
        ]);
    }

    public function store(Request $request, TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'create'), 403);
        abort_if(! $typeContrat->actif, 404);

        $donnees = $this->valider($request);
        $donnees['type_contrat_id'] = $typeContrat->id;
        $donnees['created_by'] = Auth::id();

        $contrat = DB::transaction(function () use ($donnees) {
            $contrat = Contrat::create($donnees);
            $this->creerCasesVides($contrat);

            return $contrat;
        });

        return redirect()
            ->route('gestion.contrats.show', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat])
            ->with('succes', 'Contrat créé. Vous pouvez maintenant y déposer le contrat signé et d\'éventuels avenants.');
    }

    public function show(TypeContrat $typeContrat, Contrat $contrat): View
    {
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $contrat->load(['typeContrat', 'responsable', 'creePar', 'fichiers.document']);

        $contratSigne = $contrat->fichiers->firstWhere('role', ContratFichier::ROLE_CONTRAT_SIGNE);

        $avenants = $contrat->fichiers
            ->where('role', ContratFichier::ROLE_AVENANT)
            ->sortBy('numero');

        $parametres = Parametre::actuel();

        return view('gestion.contrats.show', [
            'typeContrat' => $typeContrat,
            'contrat' => $contrat,
            'contratSigne' => $contratSigne,
            'avenants' => $avenants,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(TypeContrat $typeContrat, Contrat $contrat): View
    {
        abort_unless(ResourceAccess::can('contrats', 'edit'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        return view('gestion.contrats.form', [
            'typeContrat' => $typeContrat,
            'contrat' => $contrat,
            'statuts' => Contrat::STATUTS,
            'responsablesPossibles' => $this->responsablesPossibles(),
        ]);
    }

    public function update(Request $request, TypeContrat $typeContrat, Contrat $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'edit'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $contrat->update($this->valider($request));

        return redirect()
            ->route('gestion.contrats.show', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat])
            ->with('succes', 'Contrat modifié.');
    }

    public function destroy(TypeContrat $typeContrat, Contrat $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $contrat->delete();

        return redirect()->route('gestion.contrats.index', ['typeContrat' => $typeContrat->code])->with('succes', 'Contrat mis à la corbeille.');
    }

    public function restore(TypeContrat $typeContrat, int $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $trouve = Contrat::onlyTrashed()->findOrFail($contrat);
        abort_if($trouve->type_contrat_id !== $typeContrat->id, 404);

        $trouve->restore();

        return back()->with('succes', 'Contrat restauré.');
    }

    /** Suppression définitive : supprime aussi (mise à la corbeille) chaque Document encore attaché, même raisonnement que les autres écrans dédiés. */
    public function forceDestroy(TypeContrat $typeContrat, int $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $trouve = Contrat::onlyTrashed()->with('fichiers')->findOrFail($contrat);
        abort_if($trouve->type_contrat_id !== $typeContrat->id, 404);

        foreach ($trouve->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $trouve->forceDelete();

        return redirect()->route('gestion.contrats.index', ['typeContrat' => $typeContrat->code, 'corbeille' => 1])->with('succes', 'Contrat supprimé définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les contrats
     * corbeillés de CETTE catégorie en un clic — même principe que
     * DocumentController::viderCorbeille(). Scopé à `$typeContrat`, comme le
     * reste de cet écran (onglets par catégorie) : ne vide jamais la
     * corbeille d'une AUTRE catégorie.
     */
    public function viderCorbeille(TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $contrats = Contrat::onlyTrashed()->with('fichiers')->where('type_contrat_id', $typeContrat->id)->get();

        foreach ($contrats as $contrat) {
            foreach ($contrat->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $contrat->forceDelete();
        }

        return redirect()
            ->route('gestion.contrats.index', ['typeContrat' => $typeContrat->code, 'corbeille' => 1])
            ->with('succes', $contrats->count().' contrat(s) supprimé(s) définitivement.');
    }

    /** Mise à la corbeille groupée — même principe que ReunionController::mettreCorbeilleGroupee(). */
    public function mettreCorbeilleGroupee(Request $request, TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $donnees = $request->validate([
            'contrats' => ['required', 'array', 'min:1'],
            'contrats.*' => ['integer'],
        ]);

        $contrats = Contrat::query()->where('type_contrat_id', $typeContrat->id)->whereIn('id', $donnees['contrats'])->get();

        foreach ($contrats as $contrat) {
            $contrat->delete();
        }

        return back()->with('succes', $contrats->count().' contrat(s) mis à la corbeille.');
    }

    public function restaurerGroupe(Request $request, TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $donnees = $request->validate([
            'contrats' => ['required', 'array', 'min:1'],
            'contrats.*' => ['integer'],
        ]);

        $contrats = Contrat::onlyTrashed()->where('type_contrat_id', $typeContrat->id)->whereIn('id', $donnees['contrats'])->get();

        foreach ($contrats as $contrat) {
            $contrat->restore();
        }

        return back()->with('succes', $contrats->count().' contrat(s) restauré(s).');
    }

    public function forceDestroyGroupe(Request $request, TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);

        $donnees = $request->validate([
            'contrats' => ['required', 'array', 'min:1'],
            'contrats.*' => ['integer'],
        ]);

        $contrats = Contrat::onlyTrashed()->with('fichiers')->where('type_contrat_id', $typeContrat->id)->whereIn('id', $donnees['contrats'])->get();

        foreach ($contrats as $contrat) {
            foreach ($contrat->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $contrat->forceDelete();
        }

        return back()->with('succes', $contrats->count().' contrat(s) supprimé(s) définitivement.');
    }

    /** Dépose (ou remplace) LE contrat signé — case fixe unique, voir le docblock de la classe. */
    public function uploaderContratSigne(Request $request, TypeContrat $typeContrat, Contrat $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'edit'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $fichier = $contrat->fichiers()->where('role', ContratFichier::ROLE_CONTRAT_SIGNE)->firstOrFail();

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $contrat, 'Contrat signé');

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Contrat signé déposé.');
    }

    public function supprimerContratSigne(TypeContrat $typeContrat, Contrat $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $fichier = $contrat->fichiers()->where('role', ContratFichier::ROLE_CONTRAT_SIGNE)->firstOrFail();

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Contrat signé retiré. La case reste disponible pour un nouveau dépôt.');
    }

    /** Ajoute UN NOUVEL avenant (liste en nombre libre, voir le docblock de la classe). */
    public function ajouterAvenant(Request $request, TypeContrat $typeContrat, Contrat $contrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'edit'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);

        $libelle = $request->input('libelle');
        $request->validate(['libelle' => ['nullable', 'string', 'max:150']]);

        $document = $this->creerDocumentDepose($request, $contrat, $libelle ?: 'Avenant');

        $numeroSuivant = (int) $contrat->fichiers()->where('role', ContratFichier::ROLE_AVENANT)->max('numero') + 1;

        ContratFichier::create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_AVENANT,
            'numero' => $numeroSuivant,
            'libelle' => $libelle,
            'document_id' => $document->id,
        ]);

        return back()->with('succes', 'Avenant ajouté.');
    }

    public function supprimerAvenant(TypeContrat $typeContrat, Contrat $contrat, ContratFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('contrats', 'delete'), 403);
        abort_if($contrat->type_contrat_id !== $typeContrat->id, 404);
        abort_if($fichier->contrat_id !== $contrat->id, 404);
        abort_if($fichier->role !== ContratFichier::ROLE_AVENANT, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
        }

        $fichier->delete();

        return back()->with('succes', 'Avenant retiré.');
    }

    /** @return array<string, mixed> */
    protected function valider(Request $request): array
    {
        $donnees = $request->validate([
            'partenaire_prestataire' => ['required', 'string', 'max:200'],
            'objet' => ['required', 'string', 'max:255'],
            'montant' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'date_signature' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            // La comparaison avec `date_debut` n'est appliquée QUE si ce
            // champ est lui-même renseigné — `date_debut` étant facultatif
            // (voir le docblock de la migration des contrats), le laisser
            // vide ne doit jamais faire échouer la validation de `date_fin`.
            'date_fin' => ['nullable', 'date', Rule::when($request->filled('date_debut'), ['after_or_equal:date_debut'])],
            'statut' => ['required', Rule::in(array_keys(Contrat::STATUTS))],
            'responsable_id' => ['nullable', Rule::exists('users', 'id')],
            'contact_nom' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_telephone' => ['nullable', 'string', 'max:30'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        // Champ vide ("") converti en NULL plutôt qu'une chaîne vide — sans
        // ça, le cast `decimal:2` d'App\Models\Contrat transformerait une
        // chaîne vide en "0.00" au lieu de NULL (même principe que
        // PvSoutenanceController::normaliserTexteImport() pour les champs
        // facultatifs).
        $donnees['montant'] = ($donnees['montant'] ?? '') !== '' ? $donnees['montant'] : null;

        return $donnees;
    }

    /** Crée la case fixe "contrat signé", vide, à la création du contrat. */
    protected function creerCasesVides(Contrat $contrat): void
    {
        ContratFichier::create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
            'numero' => null,
            'libelle' => null,
            'document_id' => null,
        ]);
    }

    /** Catégories actives, pour les onglets (voir gestion/contrats/_onglets.blade.php) — partagé par index() et la redirection de routes/web.php. */
    protected function categoriesActives()
    {
        return TypeContrat::query()->where('actif', true)->orderBy('ordre')->get();
    }

    /** Utilisateurs sélectionnables comme "responsable" — tous les comptes, du plus récemment créé au plus ancien (liste courte dans ce projet, pas besoin de filtrer par rôle). */
    protected function responsablesPossibles()
    {
        return User::query()->orderBy('name')->get();
    }

    /** Dépose le fichier envoyé et crée le App\Models\Document derrière — même principe que ReunionController::creerDocumentDepose(). */
    protected function creerDocumentDepose(Request $request, Contrat $contrat, string $libelleDescription): Document
    {
        $type = $this->typeDocumentContrat();
        abort_if(! $type, 500, "Le type de document \"CONTRAT\" est introuvable — contactez l'administrateur.");

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

        $contrat->loadMissing('typeContrat');

        return Document::create([
            'description' => $contrat->typeContrat->libelle.' — '.$contrat->partenaire_prestataire.' — '.$libelleDescription,
            'type_document_id' => $type->id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document CONTRAT déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentContrat(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'CONTRAT')->first();
    }
}
