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
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
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
 * Gestion des PV de délibération — nouvelle structure dédiée (demande
 * explicite du client), en remplacement du simple "un document = un
 * fichier" utilisé jusqu'ici pour le type PV_DELIBERATION. Voir le docblock
 * de la migration 2026_09_30_000023_create_sessions_deliberation_table.php
 * pour le contexte complet.
 *
 * Chaque fichier réellement déposé (note d'information, PV par semestre/
 * année de thèse) reste un vrai App\Models\Document — on ne duplique pas le
 * cycle de vie fichier (disque, téléchargement contrôlé, corbeille,
 * historique) ici, on se contente de le rattacher à une "case"
 * (App\Models\SessionDeliberationFichier). C'est pourquoi typeDocumentPv()/
 * sousTypePour() ci-dessous vont chercher le type/sous-type PV_DELIBERATION
 * déjà existant plutôt que d'en créer un nouveau.
 */
class SessionDeliberationController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $sessions = $this->requeteListe($request, $corbeille)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête — même principe que
        // PvSoutenanceController::index().
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc');

        return view('gestion.sessions-deliberation.index', [
            'sessions' => $sessions,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && SessionDeliberation::onlyTrashed()->exists(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'onglets' => ['tous' => 'Tous'] + SessionDeliberation::CYCLES,
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

        $lignes = $sessions->map(fn (SessionDeliberation $session) => [
            $session->anneeAcademique?->libelle,
            $session->sessionLibelle(),
            $session->cycleLibelle(),
            $session->filiere?->libelle,
            $session->description,
            $session->created_at?->format('d/m/Y H:i'),
        ]);

        return FichiersExcel::exporter(
            'sessions-deliberation-'.now()->format('Y-m-d').'.xlsx',
            ['Année académique', 'Session', 'Cycle', 'Filière', 'Description', 'Créée le'],
            $lignes
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<SessionDeliberation> */
    protected function requeteListe(Request $request, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        $cycle = $this->ongletCycleValide($request);

        $query = SessionDeliberation::query()
            ->with(['anneeAcademique', 'filiere', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('annee_academique_id'), fn ($q) => $q->where('annee_academique_id', $request->integer('annee_academique_id')))
            // Onglet "cycle" (voir index()/resources/views/gestion/sessions-deliberation/index.blade.php)
            // — remplace l'ancien filtre "Cycle" à liste déroulante, même
            // principe que PvSoutenanceController::requeteListe() pour
            // "niveau" (demande explicite du client : "fait la même chose
            // que ce qu'on a fait pour le PV de soutenance pour les
            // Cycle..."). 'tous' = aucun filtre.
            ->when($cycle !== 'tous', fn ($q) => $q->where('cycle', $cycle))
            ->when($request->filled('filiere_id'), fn ($q) => $q->where('filiere_id', $request->integer('filiere_id')));

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'created_at', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Année académique" et "Filière" sont des relations, donc une
     * sous-requête corrélée plutôt qu'un orderBy direct (même principe que
     * PvSoutenanceController::colonnesTriables() pour "Filière"). "Fichiers
     * déposés" est un compteur calculé (withCount), pas une colonne réelle.
     * "created_at" n'a pas de colonne visible dédiée (pas de "Créée le" dans
     * ce tableau) mais reste le tri par défaut, identique au ->latest(
     * 'created_at') qu'elle remplace.
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn ($query, string $direction) => $query->orderBy(
                AnneeAcademique::select('libelle')->whereColumn('annees_academiques.id', 'sessions_deliberation.annee_academique_id'),
                $direction
            ),
            'session' => 'session',
            'cycle' => 'cycle',
            'filiere' => fn ($query, string $direction) => $query->orderBy(
                Filiere::select('libelle')->whereColumn('filieres.id', 'sessions_deliberation.filiere_id'),
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
     * Onglet "cycle" valide pour la liste — 'tous' (aucun filtre) ou l'une
     * des valeurs de SessionDeliberation::CYCLES. Un lien externe/favori
     * obsolète pointant vers une valeur inconnue retombe silencieusement sur
     * 'tous' plutôt qu'une erreur SQL — même principe que
     * PvSoutenanceController::ongletNiveauValide().
     */
    protected function ongletCycleValide(Request $request): string
    {
        $valeur = $request->string('cycle')->toString();

        return array_key_exists($valeur, SessionDeliberation::CYCLES) ? $valeur : 'tous';
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'create'), 403);

        return view('gestion.sessions-deliberation.form', [
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'sessions' => SessionDeliberation::SESSIONS,
            'cycles' => SessionDeliberation::CYCLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'create'), 403);

        $donnees = $request->validate([
            'annee_academique_id' => ['required', Rule::exists('annees_academiques', 'id')],
            'session' => ['required', Rule::in(array_keys(SessionDeliberation::SESSIONS))],
            'cycle' => ['required', Rule::in(array_keys(SessionDeliberation::CYCLES))],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Combinaison déjà existante (même contrainte unique qu'en base,
        // vérifiée ici en amont pour renvoyer une erreur de formulaire lisible
        // plutôt qu'une exception SQL brute).
        $existeDeja = SessionDeliberation::query()
            ->where('annee_academique_id', $donnees['annee_academique_id'])
            ->where('session', $donnees['session'])
            ->where('cycle', $donnees['cycle'])
            ->where('filiere_id', $donnees['filiere_id'])
            ->exists();

        if ($existeDeja) {
            return back()->withInput()->withErrors([
                'filiere_id' => 'Une session de délibération existe déjà pour cette combinaison année académique / session / cycle / filière.',
            ]);
        }

        $donnees['created_by'] = Auth::id();

        $session = DB::transaction(function () use ($donnees) {
            $session = SessionDeliberation::create($donnees);
            $this->creerCasesVides($session);

            return $session;
        });

        return redirect()
            ->route('gestion.sessions-deliberation.show', $session)
            ->with('succes', 'Session de délibération créée. Vous pouvez maintenant y déposer les fichiers, progressivement.');
    }

    public function show(SessionDeliberation $sessionDeliberation): View
    {
        $sessionDeliberation->load(['anneeAcademique', 'filiere', 'creePar', 'fichiers.document', 'fichiers.champSupplementaire']);

        $notes = $sessionDeliberation->fichiers
            ->whereIn('role', array_keys(SessionDeliberationFichier::ROLES_NOTES))
            ->sortBy(fn (SessionDeliberationFichier $f) => array_search($f->role, array_keys(SessionDeliberationFichier::ROLES_NOTES), true));

        $numerotees = $sessionDeliberation->fichiers
            ->where('role', $sessionDeliberation->roleNumerote())
            ->sortBy('numero');

        // Champs supplémentaires ajoutés depuis l'administration (voir
        // App\Models\ChampSupplementaire) — triés par leur "ordre" plutôt que
        // par id, pour que l'administration garde la main sur l'affichage.
        $supplementaires = $sessionDeliberation->fichiers
            ->where('role', SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE)
            ->sortBy(fn (SessionDeliberationFichier $f) => $f->champSupplementaire?->ordre ?? 0);

        $parametres = Parametre::actuel();

        return view('gestion.sessions-deliberation.show', [
            'session' => $sessionDeliberation,
            'notes' => $notes,
            'numerotees' => $numerotees,
            'supplementaires' => $supplementaires,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function update(Request $request, SessionDeliberation $sessionDeliberation): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'edit'), 403);

        // Seule la description est modifiable après création : changer
        // l'année/la session/le cycle/la filière une fois les fichiers
        // déposés obligerait à recalculer/déplacer toutes les cases
        // existantes — hors périmètre demandé par le client pour cette
        // v1 (créer une nouvelle session dans la bonne combinaison reste
        // toujours possible).
        $donnees = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $sessionDeliberation->update($donnees);

        return back()->with('succes', 'Description mise à jour.');
    }

    public function destroy(SessionDeliberation $sessionDeliberation): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete'), 403);

        $sessionDeliberation->delete();
        $this->mettreFichiersALaCorbeille($sessionDeliberation);

        return redirect()->route('gestion.sessions-deliberation.index')->with('succes', 'Session de délibération mise à la corbeille.');
    }

    public function restore(int $sessionDeliberation): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete'), 403);

        $sessionTrouvee = SessionDeliberation::onlyTrashed()->findOrFail($sessionDeliberation);
        $sessionTrouvee->restore();
        $this->restaurerFichiers($sessionTrouvee);

        return back()->with('succes', 'Session de délibération restaurée.');
    }

    /**
     * Cascade MISE À LA CORBEILLE — demande explicite du client ("cascade
     * incohérente" : voir le docblock équivalent de ReunionController pour
     * le détail du problème signalé). Même garde-fou (document déjà à la
     * corbeille ignoré).
     */
    protected function mettreFichiersALaCorbeille(SessionDeliberation $sessionDeliberation): void
    {
        $sessionDeliberation->loadMissing('fichiers');

        foreach ($sessionDeliberation->fichiers as $fichier) {
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
    protected function restaurerFichiers(SessionDeliberation $sessionDeliberation): void
    {
        $sessionDeliberation->loadMissing('fichiers');

        foreach ($sessionDeliberation->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::onlyTrashed()->find($fichier->document_id)?->restore();
            }
        }
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une case de cette session — sans quoi ces
     * documents resteraient en base sans plus aucune case pour y accéder
     * depuis cet écran. Ils ne sont PLUS récupérables depuis Documents >
     * Tous les documents (voir App\Support\TypesEcranDedie et le docblock
     * de DocumentController::index() : un document d'écran dédié, PV de
     * délibération compris, en est désormais exclu, même corbeillé — faille
     * de sécurité corrigée, "revoir la sécurité pour éviter l'accessibilité
     * à des pages confidentielles sans autorisation") ; seul "Vider la
     * corbeille" (DocumentController::viderCorbeille(), volontairement non
     * filtré) peut encore les effacer définitivement, en nettoyage global.
     */
    public function forceDestroy(int $sessionDeliberation): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete_definitif'), 403);

        $session = SessionDeliberation::onlyTrashed()->with('fichiers')->findOrFail($sessionDeliberation);

        foreach ($session->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $session->forceDelete();

        return redirect()->route('gestion.sessions-deliberation.index', ['corbeille' => 1])->with('succes', 'Session de délibération supprimée définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUTES les sessions de
     * délibération corbeillées en un clic, sans avoir à les sélectionner une
     * par une — même principe que DocumentController::viderCorbeille(), sur
     * demande du client ("Vider la corbeille" manquant sur la plupart des
     * écrans). Porte sur TOUTE la corbeille, jamais seulement la page
     * affichée (voir `corbeillePeutEtreVidee` dans index()) : agit directement
     * sur SessionDeliberation::onlyTrashed(), jamais sur une sélection.
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete_definitif'), 403);

        $sessions = SessionDeliberation::onlyTrashed()->with('fichiers')->get();

        foreach ($sessions as $session) {
            foreach ($session->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $session->forceDelete();
        }

        return redirect()
            ->route('gestion.sessions-deliberation.index', ['corbeille' => 1])
            ->with('succes', $sessions->count().' session(s) de délibération supprimée(s) définitivement.');
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique) : MÊME cascade, limitée aux
     * sessions mises à la corbeille depuis plus de $jours jours. Appelée
     * depuis la console (aucun utilisateur authentifié) — sans
     * abort_unless ni redirection.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $sessions = SessionDeliberation::onlyTrashed()->with('fichiers')->where('deleted_at', '<', now()->subDays($jours))->get();

        foreach ($sessions as $session) {
            foreach ($session->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $session->forceDelete();
        }

        return ['supprimes' => $sessions->count(), 'ignores' => 0];
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de sessions (cases à
     * cocher, voir resources/views/gestion/sessions-deliberation/index.blade.php)
     * mises à la corbeille en une seule action — MÊME PRINCIPE que
     * DocumentController::mettreCorbeilleGroupee(), sur demande explicite du
     * client ("permettre de faire des sélections pour des actions sur les
     * tableaux différents").
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionDeliberation::query()->with('fichiers')->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            $session->delete();
            $this->mettreFichiersALaCorbeille($session);
        }

        return back()->with('succes', $sessions->count().' session(s) de délibération mise(s) à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionDeliberation::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            $session->restore();
            $this->restaurerFichiers($session);
        }

        return back()->with('succes', $sessions->count().' session(s) de délibération restaurée(s).');
    }

    /**
     * Suppression définitive groupée — MÊME RAISONNEMENT que forceDestroy()
     * ci-dessus : chaque document encore attaché à une case est mis à la
     * corbeille AVANT de supprimer la session elle-même.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete_definitif'), 403);

        $donnees = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['integer'],
        ]);

        $sessions = SessionDeliberation::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['sessions'])->get();

        foreach ($sessions as $session) {
            foreach ($session->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $session->forceDelete();
        }

        return back()->with('succes', $sessions->count().' session(s) de délibération supprimée(s) définitivement.');
    }

    /**
     * Dépose (ou remplace) le fichier d'UNE case. Crée un vrai
     * App\Models\Document derrière (voir le docblock de la classe) — s'il y
     * en avait déjà un, l'ancien est mis à la corbeille (jamais supprimé
     * physiquement tout de suite, même règle que DocumentController::update()).
     */
    public function uploaderFichier(Request $request, SessionDeliberation $sessionDeliberation, SessionDeliberationFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'edit'), 403);
        abort_if($fichier->session_deliberation_id !== $sessionDeliberation->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type PV_DELIBERATION (voir TypeDocumentSeeder) casserait sinon
        // ce dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $typePv = $this->typeDocumentPv();
        abort_if(! $typePv, 500, "Le type de document \"PV_DELIBERATION\" est introuvable — contactez l'administrateur.");

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
            'description' => $sessionDeliberation->cycleLibelle().' — '.$sessionDeliberation->filiere->libelle.' — '.$fichier->libelle(),
            'type_document_id' => $typePv->id,
            'sous_type_document_id' => $this->sousTypePour($sessionDeliberation)?->id,
            'filiere_id' => $sessionDeliberation->filiere_id,
            'niveau_id' => $this->niveauPour($sessionDeliberation, $fichier)?->id,
            'annee_academique_id' => $sessionDeliberation->annee_academique_id,
            'semestre' => $fichier->role === SessionDeliberationFichier::ROLE_SEMESTRE ? $fichier->numero : null,
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

    public function supprimerFichier(SessionDeliberation $sessionDeliberation, SessionDeliberationFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('sessions_deliberation', 'delete'), 403);
        abort_if($fichier->session_deliberation_id !== $sessionDeliberation->id, 404);

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
     * Crée toutes les cases vides d'une session à sa création : deux notes
     * d'information (étudiants/enseignants) + une case par semestre
     * (Licence/Master) ou par année de thèse (Doctorat) — voir
     * SessionDeliberation::numerosAttendus()/roleNumerote() — plus une case
     * par champ supplémentaire actif applicable à ce cycle (voir
     * creerCasesChampsSupplementaires() ci-dessous).
     */
    protected function creerCasesVides(SessionDeliberation $session): void
    {
        foreach (array_keys(SessionDeliberationFichier::ROLES_NOTES) as $role) {
            SessionDeliberationFichier::create([
                'session_deliberation_id' => $session->id,
                'role' => $role,
                'numero' => null,
                'document_id' => null,
            ]);
        }

        $role = $session->roleNumerote();

        foreach ($session->numerosAttendus() as $numero) {
            SessionDeliberationFichier::create([
                'session_deliberation_id' => $session->id,
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
    protected function creerCasesChampsSupplementaires(SessionDeliberation $session): void
    {
        ChampSupplementaire::query()
            ->where('actif', true)
            ->where('pour_pv', true)
            ->get()
            ->filter(fn (ChampSupplementaire $champ) => in_array($session->cycle, $champ->cycles ?? [], true))
            ->each(function (ChampSupplementaire $champ) use ($session) {
                SessionDeliberationFichier::create([
                    'session_deliberation_id' => $session->id,
                    'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
                    'numero' => null,
                    'champ_supplementaire_id' => $champ->id,
                    'document_id' => null,
                ]);
            });
    }

    /** Type de document PV_DELIBERATION déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentPv(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'PV_DELIBERATION')->first();
    }

    /**
     * Sous-type "Licence"/"Master" déjà existant (voir SousTypeDocumentSeeder)
     * pour rester cohérent avec les anciens documents PV_DELIBERATION — le
     * Doctorat n'a pas de sous-type prévu pour l'instant, la case reste alors
     * simplement sans sous-type.
     */
    protected function sousTypePour(SessionDeliberation $session): ?SousTypeDocument
    {
        $type = $this->typeDocumentPv();

        if (! $type || $session->cycle === SessionDeliberation::CYCLE_DOCTORAT) {
            return null;
        }

        $libelle = $session->cycle === SessionDeliberation::CYCLE_MASTER ? 'Master' : 'Licence';

        return SousTypeDocument::query()->where('type_document_id', $type->id)->where('libelle', $libelle)->first();
    }

    /**
     * Niveau (L1...M2) déduit du numéro de semestre — voir
     * Semestres::codeNiveauPourSemestre(). Null pour les notes d'information
     * et pour le Doctorat (pas de référentiel Niveau pour ce cycle, voir
     * database/seeders/NiveauSeeder.php).
     */
    protected function niveauPour(SessionDeliberation $session, SessionDeliberationFichier $fichier): ?Niveau
    {
        if ($fichier->role !== SessionDeliberationFichier::ROLE_SEMESTRE || ! $fichier->numero) {
            return null;
        }

        $code = Semestres::codeNiveauPourSemestre($fichier->numero);

        return $code ? Niveau::query()->where('code', $code)->first() : null;
    }
}
