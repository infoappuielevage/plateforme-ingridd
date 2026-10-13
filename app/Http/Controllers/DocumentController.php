<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\FamillesDocument;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use App\Support\Semestres;
use App\Support\TypesDiplome;
use App\Support\TypesEcranDedie;
use App\Support\TypesFichiers;
use App\Support\TypesPvSoutenance;
use App\Support\Verrouillage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    use ListeTriable, VerifieVerrouillage;

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
        // Onglet "famille" au-dessus de la liste (voir App\Support\FamillesDocument)
        // — remplace ListDocuments::getTabs() (Filament, retiré). Un onglet
        // inconnu dans l'URL retombe silencieusement sur "tous".
        $onglet = FamillesDocument::ongletValide($request->string('onglet', 'tous')->toString());

        $documents = $this->requeteListe($request, $onglet, $corbeille)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête — même principe que
        // PvSoutenanceController::index().
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc');

        return view('gestion.documents.index', [
            'documents' => $documents,
            'corbeille' => $corbeille,
            // Ignore les filtres actifs (onglet, recherche...) — "Vider la
            // corbeille" agit toujours sur TOUS les documents corbeillés,
            // jamais seulement ceux affichés à l'écran (voir viderCorbeille()).
            'corbeillePeutEtreVidee' => $corbeille && Document::onlyTrashed()->exists(),
            'onglet' => $onglet,
            'onglets' => FamillesDocument::onglets(),
            'types' => TypeDocument::query()->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'typeDiplomeAttestationIds' => TypesDiplome::ids(),
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
        $onglet = FamillesDocument::ongletValide($request->string('onglet', 'tous')->toString());

        $documents = $this->requeteListe($request, $onglet, $corbeille)->get();

        $lignes = $documents->map(fn (Document $document) => [
            $document->description,
            $document->typeDocument?->libelle,
            $document->sousTypeDocument?->libelle,
            $document->filiere?->libelle,
            $document->niveau?->libelle,
            $document->anneeAcademique?->libelle,
            $document->statut_retrait,
            $document->uploadePar?->name,
            $document->nom_original,
            $document->created_at?->format('d/m/Y H:i'),
        ]);

        return FichiersExcel::exporter(
            'documents-'.now()->format('Y-m-d').'.xlsx',
            ['Description', 'Type', 'Sous-type', 'Filière', 'Niveau', 'Année académique', 'Statut retrait', 'Déposé par', 'Fichier original', 'Date de dépôt'],
            $lignes
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Document> */
    protected function requeteListe(Request $request, string $onglet, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        $query = Document::query()
            ->with(['typeDocument', 'sousTypeDocument', 'filiere', 'niveau', 'anneeAcademique', 'uploadePar'])
            // Jamais les pièces d'un écran dédié ici (voir App\Support\
            // TypesEcranDedie) : au-delà du doublon ("comme à la création",
            // voir donneesFormulaire() ci-dessous), les laisser dans "Tous
            // les documents" permettait à un rôle n'ayant QUE le droit
            // générique "documents" (ex. "Gestionnaire", voir
            // database/seeders/RoleSeeder.php) de voir et télécharger des
            // pièces d'étudiant/de soutenance sans avoir le droit du module
            // correspondant — faille corrigée sur demande explicite du
            // client (voir aussi les routes documents.telecharger/voir).
            ->whereNotIn('type_document_id', TypesEcranDedie::ids())
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when(FamillesDocument::codesPour($onglet), function ($q, array $codes) {
                $q->whereHas('typeDocument', fn ($q2) => $q2->whereIn('code', $codes));
            })
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
            });

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Type" et "Filière / Niveau / Année" ne sont PAS incluses : ce sont
     * des cellules composites qui mélangent plusieurs valeurs (type + sous-
     * type + description, ou filière + niveau/semestre + année), sans valeur
     * unique claire sur laquelle trier. "Ajouté par" est une relation, donc
     * une sous-requête corrélée plutôt qu'un orderBy direct — même principe
     * que PvSoutenanceController::colonnesTriables() pour "Filière".
     * "created_at" reste le tri par défaut (identique à l'ancien
     * ->latest('created_at')) même si aucune colonne "Date de dépôt" dédiée
     * n'existe (elle est affichée à l'intérieur de la cellule "Fichier").
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'created_at' => 'created_at',
            'nom_original' => 'nom_original',
            'ajoute_par' => fn ($query, string $direction) => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'documents.uploaded_by'),
                $direction
            ),
            'statut_retrait' => 'statut_retrait',
        ];
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
        $this->abortSiEcranDedie($document);

        return view('gestion.documents.form', $this->donneesFormulaire($document));
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'edit'), 403);
        $this->abortSiEcranDedie($document);

        // Uniquement si un NOUVEAU fichier est envoyé (remplacement) — voir
        // App\Support\Verrouillage, dont la portée est volontairement
        // limitée au fichier lui-même : modifier les autres champs
        // (description, type...) d'un document verrouillé reste permis.
        if ($request->hasFile('fichier')) {
            $reponse = $this->refuserSiVerrouille($document);
            if ($reponse) {
                return $reponse;
            }
        }

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
        $this->abortSiEcranDedie($document);

        $reponse = $this->refuserSiVerrouille($document);
        if ($reponse) {
            return $reponse;
        }

        $document->delete();

        return back()->with('succes', 'Document mis à la corbeille.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les documents
     * corbeillés en un clic, sans avoir à les sélectionner un par un — sur
     * demande du client, en plus de la suppression définitive individuelle
     * déjà possible (voir forceDestroy()). Remplace
     * App\Filament\Resources\CorbeilleResource\Pages\ListCorbeille::getHeaderActions()
     * (Filament, retiré).
     *
     * Passe par chaque document individuellement (plutôt qu'un
     * Document::onlyTrashed()->forceDelete() en une seule requête SQL) :
     * c'est Document::booted() (static::deleting) qui efface le fichier
     * physique de chacun, et l'historique (voir AuditLog) qui journalise
     * chaque suppression — les deux exigent que l'événement Eloquent
     * "deleting"/"deleted" se déclenche pour chaque modèle, ce qu'une
     * suppression de masse en une seule requête ne ferait pas.
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        [$documents, $ignores] = $this->separerVerrouilles(Document::onlyTrashed()->get());

        foreach ($documents as $document) {
            $document->forceDelete();
        }

        return redirect()
            ->route('gestion.documents.index', ['corbeille' => 1])
            ->with('succes', $this->messageAvecIgnores($documents->count().' document(s) supprimé(s) définitivement.', $ignores));
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de documents (cases à
     * cocher, voir resources/views/gestion/documents/index.blade.php) mis à
     * la corbeille en une seule action, plutôt qu'un par un — sur demande
     * explicite du client. Remplace
     * App\Filament\Resources\DocumentResource\Pages\ListDocuments
     * (DeleteBulkAction, Filament, retiré).
     *
     * `Document::query()` (pas onlyTrashed()) : un id déjà corbeillé glissé
     * dans la sélection est ignoré plutôt que traité une seconde fois — le
     * scope global SoftDeletes l'exclut de lui-même.
     *
     * Comme viderCorbeille() : chaque document est traité individuellement
     * (jamais une seule requête SQL groupée), pour que Document::booted()
     * (fichier physique) et l'historique (AuditLog) se déclenchent bien pour
     * chacun.
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $donnees = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['integer'],
        ]);

        [$documents, $ignores] = $this->separerVerrouilles(
            Document::query()
                ->whereIn('id', $donnees['documents'])
                ->whereNotIn('type_document_id', TypesEcranDedie::ids())
                ->get()
        );

        foreach ($documents as $document) {
            $document->delete();
        }

        return back()->with('succes', $this->messageAvecIgnores($documents->count().' document(s) mis à la corbeille.', $ignores));
    }

    public function restore(int $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $document = Document::onlyTrashed()->findOrFail($document);
        $this->abortSiEcranDedie($document);

        $document->restore();

        return back()->with('succes', 'Document restauré.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $donnees = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['integer'],
        ]);

        $documents = Document::onlyTrashed()
            ->whereIn('id', $donnees['documents'])
            ->whereNotIn('type_document_id', TypesEcranDedie::ids())
            ->get();

        foreach ($documents as $document) {
            $document->restore();
        }

        return back()->with('succes', $documents->count().' document(s) restauré(s).');
    }

    public function forceDestroy(int $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $document = Document::onlyTrashed()->findOrFail($document);
        $this->abortSiEcranDedie($document);

        $reponse = $this->refuserSiVerrouille($document);
        if ($reponse) {
            return $reponse;
        }

        $document->forceDelete();

        return back()->with('succes', 'Document supprimé définitivement.');
    }

    /**
     * Suppression définitive groupée — pendant de forceDestroy() pour une
     * sélection multiple, sans avoir à vider TOUTE la corbeille (voir
     * viderCorbeille()) quand seule une partie doit disparaître.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'delete'), 403);

        $donnees = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['integer'],
        ]);

        [$documents, $ignores] = $this->separerVerrouilles(
            Document::onlyTrashed()
                ->whereIn('id', $donnees['documents'])
                ->whereNotIn('type_document_id', TypesEcranDedie::ids())
                ->get()
        );

        foreach ($documents as $document) {
            $document->forceDelete();
        }

        return back()->with('succes', $this->messageAvecIgnores($documents->count().' document(s) supprimé(s) définitivement.', $ignores));
    }

    /**
     * Bouton rapide "Marquer comme retiré"/"Annuler le retrait" — même
     * action que dans DocumentResource::table(), sans passer par le
     * formulaire complet.
     */
    public function basculerRetrait(Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('documents', 'edit'), 403);
        $this->abortSiEcranDedie($document);

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

    /**
     * Verrouille MANUELLEMENT un document, à la demande — en plus des deux
     * seuils automatiques (année académique / date, réglés dans
     * Administration > Paramètres), demande explicite du client : pouvoir
     * figer un document précis même s'il ne tombe sous aucun des deux
     * seuils. Gérée par le droit ResourceAccess "verrouillage_documents" >
     * "Modifier" plutôt que réservée en dur au rôle admin (demande explicite
     * du client, "intégrer le verrouillage dans les rôles et permissions") —
     * un rôle "admin" a de toute façon toujours ce droit (voir
     * ResourceAccess::can()), coché ou non dans la matrice.
     *
     * Efface toujours `deverrouille_le`/`deverrouille_par_id` : les deux
     * paires de colonnes sont mutuellement exclusives, voir le docblock de
     * App\Support\Verrouillage.
     */
    public function verrouiller(Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('verrouillage_documents', 'edit'), 403);

        $document->update([
            'verrouille_le' => now(),
            'verrouille_par_id' => Auth::id(),
            'deverrouille_le' => null,
            'deverrouille_par_id' => null,
        ]);

        return back()->with('succes', 'Document verrouillé — il ne peut plus être remplacé ni supprimé sans déverrouillage.');
    }

    /**
     * Déverrouille EXPLICITEMENT un document verrouillé (voir
     * App\Support\Verrouillage) — demande explicite du client : même un
     * administrateur ne doit pas pouvoir contourner le verrouillage
     * silencieusement, cette action doit rester une décision volontaire et
     * tracée. Gérée par le même droit ResourceAccess que verrouiller()
     * ci-dessus — voir son docblock pour le changement par rapport à la
     * version précédente (réservée en dur au rôle admin).
     *
     * Pas de condition "déjà déverrouillé" ici : renvoyer un succès silencieux
     * sur un document déjà déverrouillé est inoffensif (déverrouille_par_id/
     * deverrouille_le seraient simplement réécrits avec l'utilisateur et
     * l'horodatage actuels), inutile d'ajouter une branche d'erreur dédiée.
     *
     * Efface toujours `verrouille_le`/`verrouille_par_id` — même raison que
     * verrouiller() ci-dessus.
     */
    public function deverrouiller(Document $document): RedirectResponse
    {
        abort_unless(ResourceAccess::can('verrouillage_documents', 'edit'), 403);

        $document->update([
            'deverrouille_le' => now(),
            'deverrouille_par_id' => Auth::id(),
            'verrouille_le' => null,
            'verrouille_par_id' => null,
        ]);

        return back()->with('succes', 'Document déverrouillé — il peut désormais être remplacé ou supprimé.');
    }

    /**
     * Bloque toute manipulation d'un document "à écran dédié" (voir
     * App\Support\TypesEcranDedie) depuis ce formulaire général — PAS une
     * question de droit ici (même un administrateur est concerné, voir
     * App\Support\ResourceAccess::can() qui donne toujours "true" à
     * "admin" : ce garde-fou agit AVANT/EN PLUS de ResourceAccess) : ces
     * documents ont leur propre contrôleur (EtudiantController,
     * PvSoutenanceController...), seul à connaître les règles qui vont avec
     * (ex. une case qui ne doit JAMAIS disparaître même quand on retire son
     * fichier, voir EtudiantController::supprimerFichier()) — les modifier/
     * supprimer ici court-circuiterait ces règles et laisserait une case
     * avec un `document_id` pointant vers un document supprimé.
     *
     * 404 plutôt que 403 : ne pas laisser deviner qu'un document "caché" de
     * la liste générale (voir index() ci-dessus) existe bien à cet id.
     */
    protected function abortSiEcranDedie(Document $document): void
    {
        abort_if(in_array($document->type_document_id, TypesEcranDedie::ids(), true), 404);
    }

    /**
     * Sépare une sélection de documents entre ceux réellement supprimables
     * et ceux encore verrouillés (voir App\Support\Verrouillage) — utilisée
     * par viderCorbeille()/mettreCorbeilleGroupee()/forceDestroyGroupe() pour
     * qu'UN SEUL document verrouillé glissé dans la sélection n'empêche pas
     * de traiter les autres : il est simplement ignoré, et le nombre ignoré
     * est indiqué dans le message de confirmation (voir messageAvecIgnores()
     * ci-dessous).
     *
     * @param  \Illuminate\Support\Collection<int, Document>  $documents
     * @return array{0: \Illuminate\Support\Collection<int, Document>, 1: int}
     */
    protected function separerVerrouilles(Collection $documents): array
    {
        $autorises = $documents->reject(fn (Document $document) => Verrouillage::estVerrouille($document));

        return [$autorises, $documents->count() - $autorises->count()];
    }

    protected function messageAvecIgnores(string $message, int $ignores): string
    {
        return $ignores > 0
            ? $message." ({$ignores} document(s) verrouillé(s) ignoré(s), non supprimé(s).)"
            : $message;
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
        //
        // Même chose, à la création SEULEMENT, pour les trois types qui ont
        // désormais leur propre écran dédié (voir App\Support\
        // TypesEcranDedie) : on ne les propose plus ici, pour éviter de
        // créer un document orphelin en passant à côté de PvSoutenance/
        // SessionDeliberation/SessionReleve. En modification, un document
        // déjà classé ainsi (déposé avant ce changement, ou via le Secrétariat
        // à l'époque) garde son type visible, comme pour un type désactivé.
        $typesQuery = TypeDocument::query()->orderBy('ordre');
        $sousTypesQuery = SousTypeDocument::query()->orderBy('ordre');
        $filieresQuery = Filiere::query()->orderBy('ordre');
        $niveauxQuery = Niveau::query()->orderBy('ordre');
        $anneesQuery = AnneeAcademique::query()->orderByDesc('libelle');

        if (! $document->exists) {
            $typesQuery->where('actif', true)->whereNotIn('id', TypesEcranDedie::ids());
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

        // "Titulaire" est obligatoire pour un diplôme/attestation ou un PV de
        // soutenance (demande explicite du client, voir les deux référentiels
        // App\Support\TypesDiplome/TypesPvSoutenance) — absent des règles
        // ci-dessous, le champ ne serait jamais réellement exigé côté serveur
        // (le formulaire, lui, le marque déjà requis en JS selon le type
        // choisi, mais voir le docblock de la classe : un serveur ne doit
        // jamais faire confiance à ce que le JavaScript a validé).
        $typeId = (int) $request->input('type_document_id');
        $titulaireExige = in_array($typeId, TypesDiplome::ids(), true)
            || in_array($typeId, TypesPvSoutenance::ids(), true);

        return $request->validate([
            // Rule::notIn(...) uniquement à la création : un formulaire
            // trafiqué (le <select> ne propose déjà plus ces types-là, voir
            // donneesFormulaire()) ne doit pas pouvoir créer de document
            // orphelin pour un type à écran dédié. En modification, on ne
            // bloque rien : un document déjà classé ainsi doit rester
            // modifiable normalement (voir App\Support\TypesEcranDedie).
            'type_document_id' => [
                'required',
                Rule::exists('type_documents', 'id'),
                Rule::when($creation, Rule::notIn(TypesEcranDedie::ids())),
            ],
            // ->where(...) : un sous-type doit appartenir au type soumis, pas
            // seulement exister quelque part — le formulaire (JS) ne propose
            // déjà que les sous-types du type choisi, mais un serveur ne doit
            // jamais faire confiance à ce que le JavaScript a montré/caché
            // (voir le docblock de la classe) ; sans ce ->where(), un
            // sous-type d'un AUTRE type resterait accepté si forcé dans la
            // requête.
            'sous_type_document_id' => [
                'nullable',
                Rule::exists('sous_types_documents', 'id')
                    ->where(fn ($q) => $q->where('type_document_id', $request->input('type_document_id'))),
            ],
            'filiere_id' => ['nullable', Rule::exists('filieres', 'id')],
            'niveau_id' => ['nullable', Rule::exists('niveaux', 'id')],
            'annee_academique_id' => ['nullable', Rule::exists('annees_academiques', 'id')],
            'semestre' => ['nullable', 'integer', 'min:1', 'max:10'],
            'nom_titulaire' => [$titulaireExige ? 'required' : 'nullable', 'string', 'max:255'],
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
        ], [
            'type_document_id.not_in' => 'Ce type de document a désormais son propre écran dédié (PV de soutenance, PV de délibération ou Relevés de notes) — utilisez-le plutôt que ce formulaire général.',
        ]);
    }
}
