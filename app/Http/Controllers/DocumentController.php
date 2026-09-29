<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\Semestres;
use App\Support\TypesDiplome;
use App\Support\TypesFichiers;
use App\Support\TypesPvSoutenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Liste/création/modification/suppression des documents — remplace
 * App\Filament\Resources\DocumentResource (sur demande du client, qui ne
 * veut plus de Filament). Mêmes règles, mêmes champs conditionnels (voir
 * form()/mutateFormDataBeforeCreate()/mutateFormDataBeforeSave() de
 * l'ancienne Resource, repris ici à l'identique), même droits
 * (App\Support\ResourceAccess) — seul l'habillage change.
 *
 * Les champs qui ne concernent que certains types de document (titulaire,
 * numéro de diplôme, thème de mémoire...) sont TOUJOURS acceptés côté
 * serveur si envoyés : c'est le formulaire (JS, voir
 * resources/views/gestion/documents/_champs-formulaire.blade.php) qui les
 * cache/désactive selon le type choisi, mais un serveur ne doit jamais faire
 * confiance à ce que le JavaScript a montré ou caché.
 */
class DocumentController extends Controller
{
    public function __construct()
    {
        // Vérifié sur CHAQUE action (pas seulement l'affichage) : create/
        // store exigent en plus "create", update "edit", destroy/restore/
        // forceDestroy "delete" — voir chaque méthode.
        abort_unless(ResourceAccess::can('documents', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $requete = Document::query()
            ->with(['typeDocument', 'sousTypeDocument', 'filiere', 'niveau', 'anneeAcademique', 'uploadePar'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('type_document_id'), fn ($q) => $q->where('type_document_id', $request->integer('type_document_id')))
            ->when($request->filled('sous_type_document_id'), fn ($q) => $q->where('sous_type_document_id', $request->integer('sous_type_document_id')))
            ->when($request->filled('filiere_id'), fn ($q) => $q->where('filiere_id', $request->integer('filiere_id')))
            ->when($request->filled('niveau_id'), fn ($q) => $q->where('niveau_id', $request->integer('niveau_id')))
            ->when($request->filled('annee_academique_id'), fn ($q) => $q->where('annee_academique_id', $request->integer('annee_academique_id')))
            ->when($request->filled('statut_retrait'), fn ($q) => $q->where('statut_retrait', $request->string('statut_retrait')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('description', 'like', $terme)
                        ->orWhere('nom_original', 'like', $terme)
                        ->orWhere('nom_titulaire', 'like', $terme)
                        ->orWhere('numero_diplome', 'like', $terme)
                        ->orWhere('theme_memoire', 'like', $terme);
                });
            })
            ->latest('created_at');

        $documents = $requete->paginate(20)->withQueryString();

        return view('gestion.documents.index', [
            'documents' => $documents,
            'corbeille' => $corbeille,
            'types' => TypeDocument::query()->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'typeDiplomeAttestationIds' => TypesDiplome::ids(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(ResourceAccess::can('documents', 'create'), 403);

        return view('gestion.documents.form', $this->donneesFormulaire(
            new Document(),
            // Type présélectionné (ex. venant du bouton "Ajouter" d'une
            // page Secrétariat, voir SecretariatController) — une simple
            // suggestion pré-remplie, jamais imposée : l'utilisateur reste
            // libre de choisir un autre type avant d'enregistrer, et le
            // contrôle serveur (valider()) ne fait confiance qu'à la
            // valeur réellement soumise dans le formulaire.
            typePreselectionneId: $request->integer('type_document_id') ?: null,
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'create'), 403);

        $donnees = $this->valider($request, creation: true);

        $chemin = $request->file('fichier')->store('uploads', 'documents');

        $donnees['chemin'] = $chemin;
        $donnees['nom_original'] = $request->file('fichier')->getClientOriginalName();
        $donnees['extension'] = pathinfo($donnees['nom_original'], PATHINFO_EXTENSION);
        $donnees['taille'] = Storage::disk('documents')->size($chemin);
        $donnees['uploaded_by'] = Auth::id();

        if (($donnees['statut_retrait'] ?? null) === Document::STATUT_RETRAIT_RETIRE) {
            $donnees['retrait_enregistre_par_id'] = Auth::id();
            $donnees['date_retrait'] = $donnees['date_retrait'] ?? now();
        }

        unset($donnees['fichier']);

        Document::create($donnees);

        return redirect()->route('gestion.documents.index')->with('succes', 'Document ajouté.');
    }

    public function edit(Document $document): View
    {
        abort_unless(ResourceAccess::can('documents', 'edit'), 403);

        return view('gestion.documents.form', $this->donneesFormulaire($document));
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'edit'), 403);

        $donnees = $this->valider($request, creation: false);

        $ancienChemin = null;

        if ($request->hasFile('fichier')) {
            $ancienChemin = $document->chemin;
            $chemin = $request->file('fichier')->store('uploads', 'documents');

            $donnees['chemin'] = $chemin;
            $donnees['nom_original'] = $request->file('fichier')->getClientOriginalName();
            $donnees['extension'] = pathinfo($donnees['nom_original'], PATHINFO_EXTENSION);
            $donnees['taille'] = Storage::disk('documents')->size($chemin);
        } else {
            // Pas de nouveau fichier : on garde le fichier/les métadonnées
            // déjà enregistrés, jamais touché par ce formulaire.
            unset($donnees['nom_original']);
        }

        unset($donnees['fichier']);

        $donnees['modifie_par'] = Auth::id();

        // Même logique que EditDocument::mutateFormDataBeforeSave() : qui a
        // enregistré le retrait (seulement au moment où il bascule, pour ne
        // pas écraser l'auteur d'origine à chaque modification), et
        // nettoyage si on repasse à "Non retiré".
        if (array_key_exists('statut_retrait', $donnees)) {
            if ($donnees['statut_retrait'] === Document::STATUT_RETRAIT_RETIRE) {
                if ($document->statut_retrait !== Document::STATUT_RETRAIT_RETIRE) {
                    $donnees['retrait_enregistre_par_id'] = Auth::id();
                }
                $donnees['date_retrait'] = $donnees['date_retrait'] ?? now();
            } else {
                $donnees['date_retrait'] = null;
                $donnees['retrait_enregistre_par_id'] = null;
            }
        }

        $document->update($donnees);

        if ($ancienChemin) {
            Storage::disk('documents')->delete($ancienChemin);
        }

        return redirect()->route('gestion.documents.index')->with('succes', 'Document modifié.');
    }

    /** Mise à la corbeille (SoftDeletes) — jamais une suppression définitive, voir forceDestroy(). */
    public function destroy(Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $document->delete();

        return back()->with('succes', 'Document mis à la corbeille.');
    }

    public function restore(int $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        Document::onlyTrashed()->findOrFail($document)->restore();

        return back()->with('succes', 'Document restauré.');
    }

    public function forceDestroy(int $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        Document::onlyTrashed()->findOrFail($document)->forceDelete();

        return back()->with('succes', 'Document supprimé définitivement.');
    }

    /**
     * Bouton rapide "Marquer comme retiré"/"Annuler le retrait" — même
     * action que dans DocumentResource::table(), sans passer par le
     * formulaire complet.
     */
    public function basculerRetrait(Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'edit'), 403);

        if ($document->estRetire()) {
            $document->update([
                'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
                'date_retrait' => null,
                'retrait_enregistre_par_id' => null,
            ]);
        } else {
            $document->update([
                'statut_retrait' => Document::STATUT_RETRAIT_RETIRE,
                'date_retrait' => now(),
                'retrait_enregistre_par_id' => Auth::id(),
            ]);
        }

        return back()->with('succes', 'Statut de retrait mis à jour.');
    }

    protected function donneesFormulaire(Document $document, ?int $typePreselectionneId = null): array
    {
        $parametres = Parametre::actuel();

        // Types/sous-types actifs uniquement à la création (même règle que
        // l'ancien formulaire Filament : on ne propose plus de classer un
        // NOUVEAU document sous un type désactivé) — mais en modification,
        // TOUS les types/sous-types (actifs ou non), pour que le type déjà
        // choisi sur ce document (ex. "Diplôme", désactivé depuis) reste
        // visible dans la liste au lieu de disparaître silencieusement du
        // formulaire.
        $typesQuery = TypeDocument::query()->orderBy('ordre');
        $sousTypesQuery = SousTypeDocument::query()->orderBy('ordre');
        $filieresQuery = Filiere::query()->orderBy('ordre');
        $niveauxQuery = Niveau::query()->orderBy('ordre');
        $anneesQuery = AnneeAcademique::query()->orderByDesc('libelle');

        if (! $document->exists) {
            $typesQuery->where('actif', true);
            $sousTypesQuery->where('actif', true);
            $filieresQuery->where('actif', true);
            $niveauxQuery->where('actif', true);
            $anneesQuery->where('actif', true);
        }

        return [
            'document' => $document->exists ? $document : null,
            'typePreselectionneId' => $typePreselectionneId,
            'types' => $typesQuery->get(),
            'sousTypes' => $sousTypesQuery->get()->groupBy('type_document_id'),
            'filieres' => $filieresQuery->get(),
            'niveaux' => $niveauxQuery->get(),
            'anneesAcademiques' => $anneesQuery->get(),
            'typeDiplomeAttestationIds' => TypesDiplome::ids(),
            'typePvSoutenanceIds' => TypesPvSoutenance::ids(),
            // Un ou plusieurs types peuvent afficher le champ "Semestre"
            // (voir App\Models\TypeDocument::champ_semestre_actif) —
            // remplace l'ancienne recherche en dur du type "RELEVE_NOTES"
            // par son code : un administrateur peut désormais activer ce
            // champ sur n'importe quel type depuis l'administration.
            'typesAvecSemestreIds' => TypeDocument::query()->where('champ_semestre_actif', true)->pluck('id')->all(),
            'semestresParNiveau' => $this->semestresParNiveau(),
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ];
    }

    /**
     * Tous les niveaux (actifs ou non) : simple table de correspondance pour
     * le JavaScript du formulaire, peu de lignes, pas besoin d'exclure les
     * niveaux désactivés ici (contrairement à la liste déroulante elle-même).
     *
     * @return array<int, array<int, array{value:int,label:string}>> id du niveau => options de semestre pour ce niveau
     */
    protected function semestresParNiveau(): array
    {
        return Niveau::query()->get()
            ->mapWithKeys(fn (Niveau $niveau) => [$niveau->id => Semestres::optionsPourCodeNiveau($niveau->code)])
            ->all();
    }

    protected function valider(Request $request, bool $creation): array
    {
        $parametres = Parametre::actuel();
        $tailleMaxKo = $parametres->taille_max_document_mo * 1024;
        $mimesAutorises = TypesFichiers::mimesPour($parametres->types_fichiers_autorises);

        return $request->validate([
            'type_document_id' => ['required', Rule::exists('type_documents', 'id')],
            'sous_type_document_id' => ['nullable', Rule::exists('sous_types_documents', 'id')],
            'filiere_id' => ['nullable', Rule::exists('filieres', 'id')],
            'niveau_id' => ['nullable', Rule::exists('niveaux', 'id')],
            'annee_academique_id' => ['nullable', Rule::exists('annees_academiques', 'id')],
            'semestre' => ['nullable', 'integer', 'min:1', 'max:10'],
            'nom_titulaire' => ['nullable', 'string', 'max:255'],
            'numero_diplome' => ['nullable', 'string', 'max:100'],
            'date_delivrance' => ['nullable', 'date'],
            'theme_memoire' => ['nullable', 'string', 'max:255'],
            'date_soutenance' => ['nullable', 'date'],
            'membres_jury' => ['nullable', 'string', 'max:2000'],
            'mention' => ['nullable', 'string', 'max:100'],
            'statut_retrait' => ['nullable', Rule::in([Document::STATUT_RETRAIT_NON_RETIRE, Document::STATUT_RETRAIT_RETIRE])],
            'date_retrait' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fichier' => [
                $creation ? 'required' : 'nullable',
                'file',
                'max:'.$tailleMaxKo,
                Rule::when(! empty($mimesAutorises), ['mimetypes:'.implode(',', $mimesAutorises)]),
            ],
        ]);
    }
}
