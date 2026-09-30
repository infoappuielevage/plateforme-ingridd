<?php

namespace App\Http\Controllers;

use App\Models\ChampSupplementaire;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Parametre;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
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
 * Gestion des PV de soutenance — structure dédiée (demande explicite du
 * client, à partir d'un exemple de registre papier), en remplacement du
 * simple "un document = un fichier" utilisé jusqu'ici pour le type
 * PV_SOUTENANCE. Voir le docblock de la migration
 * 2026_10_04_000001_create_pv_soutenances_table.php pour le contexte complet.
 *
 * Contrairement à SessionDeliberationController/SessionReleveController (un
 * enregistrement = une filière entière pour une session), un enregistrement
 * ici concerne UN SEUL étudiant : pas de pré-check de combinaison en amont
 * (comme pour sessions_deliberation), les deux contraintes d'unicité
 * (numero_ordre_registre par niveau, numero_pv globalement) sont validées
 * directement via Rule::unique() dans valider() ci-dessous.
 *
 * Chaque fichier réellement déposé (diplôme, image du registre, champ
 * supplémentaire) reste un vrai App\Models\Document, rattaché à une "case"
 * (App\Models\PvSoutenanceFichier) — même principe que pour le PV de
 * délibération/le Relevé de notes.
 */
class PvSoutenanceController extends Controller
{
    public function __construct()
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $requete = PvSoutenance::query()
            ->with(['filiere', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('niveau'), fn ($q) => $q->where('niveau', $request->string('niveau')))
            ->when($request->filled('filiere_id'), fn ($q) => $q->where('filiere_id', $request->integer('filiere_id')))
            // Remplace l'ancien écran Documents filtré par type (ex.
            // /gestion/documents?type_document_id=...), destiné à disparaître
            // au profit de cette liste — demande explicite du client : ce
            // filtre permet de retrouver en un clic les diplômes encore à
            // retirer, ou au contraire déjà remis.
            ->when($request->filled('diplome_retrait'), fn ($q) => $request->string('diplome_retrait')->toString() === 'retire'
                ? $q->whereNotNull('diplome_date_retrait')
                : $q->whereNull('diplome_date_retrait'))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where(function ($q) use ($terme) {
                    $q->where('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%")
                        ->orWhere('numero_pv', 'like', "%{$terme}%");
                });
            })
            ->latest('date_soutenance');

        return view('gestion.pv-soutenances.index', [
            'pvSoutenances' => $requete->paginate(20)->withQueryString(),
            'corbeille' => $corbeille,
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'niveaux' => PvSoutenance::NIVEAUX,
        ]);
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        return view('gestion.pv-soutenances.form', [
            'pvSoutenance' => null,
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'niveaux' => PvSoutenance::NIVEAUX,
            'decisions' => PvSoutenance::DECISIONS,
            'mentions' => PvSoutenance::MENTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        $donnees = $this->valider($request);
        $donnees['created_by'] = Auth::id();

        $pvSoutenance = DB::transaction(function () use ($donnees) {
            $pvSoutenance = PvSoutenance::create($donnees);
            $this->creerCasesVides($pvSoutenance);

            return $pvSoutenance;
        });

        return redirect()
            ->route('gestion.pv-soutenances.show', $pvSoutenance)
            ->with('succes', 'PV de soutenance créé. Vous pouvez maintenant y déposer les documents.');
    }

    public function show(PvSoutenance $pvSoutenance): View
    {
        $pvSoutenance->load(['filiere', 'creePar', 'fichiers.document', 'fichiers.champSupplementaire']);

        $fixes = $pvSoutenance->fichiers
            ->whereIn('role', array_keys(PvSoutenanceFichier::ROLES_FIXES))
            ->sortBy(fn (PvSoutenanceFichier $f) => array_search($f->role, array_keys(PvSoutenanceFichier::ROLES_FIXES), true));

        // Champs supplémentaires ajoutés depuis l'administration (voir
        // App\Models\ChampSupplementaire) — triés par leur "ordre" plutôt que
        // par id, pour que l'administration garde la main sur l'affichage.
        $supplementaires = $pvSoutenance->fichiers
            ->where('role', PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE)
            ->sortBy(fn (PvSoutenanceFichier $f) => $f->champSupplementaire?->ordre ?? 0);

        $parametres = Parametre::actuel();

        return view('gestion.pv-soutenances.show', [
            'pvSoutenance' => $pvSoutenance,
            'fixes' => $fixes,
            'supplementaires' => $supplementaires,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(PvSoutenance $pvSoutenance): View
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        return view('gestion.pv-soutenances.form', [
            'pvSoutenance' => $pvSoutenance,
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'niveaux' => PvSoutenance::NIVEAUX,
            'decisions' => PvSoutenance::DECISIONS,
            'mentions' => PvSoutenance::MENTIONS,
        ]);
    }

    /**
     * Contrairement à PV de délibération/Relevé de notes (description
     * uniquement modifiable après création) : ici, TOUS les champs restent
     * modifiables — ce sont des données factuelles propres à un étudiant
     * (nom, dates, moyenne, jury...), saisies à la main depuis un registre
     * papier, donc susceptibles de correction après coup. Les deux
     * contraintes d'unicité (numero_ordre_registre par niveau, numero_pv)
     * restent bien sûr revalidées ici, en ignorant l'enregistrement courant.
     */
    public function update(Request $request, PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $donnees = $this->valider($request, $pvSoutenance);

        $pvSoutenance->update($donnees);

        return redirect()
            ->route('gestion.pv-soutenances.show', $pvSoutenance)
            ->with('succes', 'PV de soutenance modifié.');
    }

    public function destroy(PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $pvSoutenance->delete();

        return redirect()->route('gestion.pv-soutenances.index')->with('succes', 'PV de soutenance mis à la corbeille.');
    }

    public function restore(int $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        PvSoutenance::onlyTrashed()->findOrFail($pvSoutenance)->restore();

        return back()->with('succes', 'PV de soutenance restauré.');
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une case de ce PV — même raisonnement que
     * SessionDeliberationController::forceDestroy().
     */
    public function forceDestroy(int $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $pv = PvSoutenance::onlyTrashed()->with('fichiers')->findOrFail($pvSoutenance);

        foreach ($pv->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $pv->forceDelete();

        return redirect()->route('gestion.pv-soutenances.index', ['corbeille' => 1])->with('succes', 'PV de soutenance supprimé définitivement.');
    }

    /**
     * Dépose (ou remplace) le fichier d'UNE case. Crée un vrai
     * App\Models\Document derrière (voir le docblock de la classe) — s'il y
     * en avait déjà un, l'ancien est mis à la corbeille (jamais supprimé
     * physiquement tout de suite, même règle que DocumentController::update()).
     */
    public function uploaderFichier(Request $request, PvSoutenance $pvSoutenance, PvSoutenanceFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);
        abort_if($fichier->pv_soutenance_id !== $pvSoutenance->id, 404);

        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type PV_SOUTENANCE (voir TypeDocumentSeeder) casserait sinon ce
        // dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $type = $this->typeDocumentPvSoutenance();
        abort_if(! $type, 500, "Le type de document \"PV_SOUTENANCE\" est introuvable — contactez l'administrateur.");

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
            'description' => $pvSoutenance->niveauLibelle().' — '.$pvSoutenance->nomComplet().' — '.$fichier->libelle(),
            'type_document_id' => $type->id,
            'sous_type_document_id' => null,
            'filiere_id' => $pvSoutenance->filiere_id,
            'niveau_id' => null,
            'annee_academique_id' => null,
            'semestre' => null,
            'theme_memoire' => $pvSoutenance->theme,
            'date_soutenance' => $pvSoutenance->date_soutenance,
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

        return back()->with('succes', 'Document déposé.');
    }

    public function supprimerFichier(PvSoutenance $pvSoutenance, PvSoutenanceFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);
        abort_if($fichier->pv_soutenance_id !== $pvSoutenance->id, 404);

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Document retiré. La case reste disponible pour un nouveau dépôt.');
    }

    /**
     * Enregistre (ou corrige) le retrait du diplôme — demande explicite du
     * client : savoir non seulement SI le diplôme a été retiré, mais QUI est
     * venu le chercher (l'étudiant lui-même, ou une autre personne — auquel
     * cas son nom est précisé) et À QUELLE DATE. Voir le docblock de
     * App\Models\PvSoutenance pour la distinction entre `diplome_date_retrait`
     * (saisie à la main) et `diplome_retrait_enregistre_le` (toujours
     * "maintenant", jamais modifiable). Peut être rappelée plusieurs fois
     * pour corriger une saisie (pas de verrou après le premier enregistrement).
     */
    public function enregistrerRetraitDiplome(Request $request, PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $donnees = $request->validate([
            'diplome_retirant' => ['required', Rule::in(array_keys(PvSoutenance::RETIRANTS))],
            'diplome_nom_retirant' => [
                'nullable', 'string', 'max:150',
                'required_if:diplome_retirant,'.PvSoutenance::RETIRANT_AUTRE,
            ],
            'diplome_date_retrait' => ['required', 'date'],
        ]);

        // "L'étudiant lui-même" : on efface un éventuel nom saisi avant un
        // changement de choix, pour ne jamais garder un nom orphelin en base.
        if ($donnees['diplome_retirant'] === PvSoutenance::RETIRANT_ETUDIANT) {
            $donnees['diplome_nom_retirant'] = null;
        }

        $donnees['diplome_retrait_enregistre_par_id'] = Auth::id();
        $donnees['diplome_retrait_enregistre_le'] = now();

        $pvSoutenance->update($donnees);

        return back()->with('succes', 'Retrait du diplôme enregistré.');
    }

    /** Annule un retrait enregistré par erreur — remet le diplôme à "Non retiré". */
    public function annulerRetraitDiplome(PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $pvSoutenance->update([
            'diplome_retirant' => null,
            'diplome_nom_retirant' => null,
            'diplome_date_retrait' => null,
            'diplome_retrait_enregistre_par_id' => null,
            'diplome_retrait_enregistre_le' => null,
        ]);

        return back()->with('succes', 'Retrait du diplôme annulé.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function valider(Request $request, ?PvSoutenance $pvSoutenance = null): array
    {
        return $request->validate([
            'niveau' => ['required', Rule::in(array_keys(PvSoutenance::NIVEAUX))],
            'date_soutenance' => ['required', 'date'],
            // Unique PAR NIVEAU (chaque registre papier a sa propre
            // numérotation) — voir le docblock de la migration
            // 2026_10_04_000001_create_pv_soutenances_table.php.
            'numero_ordre_registre' => [
                'required', 'string', 'max:50',
                Rule::unique('pv_soutenances', 'numero_ordre_registre')
                    ->where(fn ($q) => $q->where('niveau', $request->input('niveau')))
                    ->ignore($pvSoutenance?->id),
            ],
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
            'date_naissance' => ['required', 'date'],
            'lieu_naissance' => ['required', 'string', 'max:150'],
            // Unique pour toute la plateforme.
            'numero_pv' => [
                'required', 'string', 'max:50',
                Rule::unique('pv_soutenances', 'numero_pv')->ignore($pvSoutenance?->id),
            ],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
            'theme' => ['required', 'string', 'max:2000'],
            'decision_jury' => ['required', Rule::in(array_keys(PvSoutenance::DECISIONS))],
            'moyenne' => ['required', 'numeric', 'between:0,20'],
            'mention' => ['required', Rule::in(array_keys(PvSoutenance::MENTIONS))],
            'president_jury' => ['required', 'string', 'max:150'],
            'directeur_memoire' => ['nullable', 'string', 'max:150'],
            'rapporteur' => ['nullable', 'string', 'max:150'],
            'maitre_stage' => ['nullable', 'string', 'max:150'],
        ]);
    }

    /**
     * Crée toutes les cases vides d'un PV à sa création : les deux rôles
     * fixes (diplôme, image du registre) + une case par champ supplémentaire
     * actif applicable à ce niveau (voir creerCasesChampsSupplementaires()
     * ci-dessous).
     */
    protected function creerCasesVides(PvSoutenance $pvSoutenance): void
    {
        foreach (array_keys(PvSoutenanceFichier::ROLES_FIXES) as $role) {
            PvSoutenanceFichier::create([
                'pv_soutenance_id' => $pvSoutenance->id,
                'role' => $role,
                'champ_supplementaire_id' => null,
                'document_id' => null,
            ]);
        }

        $this->creerCasesChampsSupplementaires($pvSoutenance);
    }

    /**
     * Cases pour les champs supplémentaires actifs applicables à CE niveau
     * (voir App\Models\ChampSupplementaire::applicableSoutenance()) — réponse
     * du client à la question posée lors de la construction de cet écran
     * ("Oui, via l'administration"), ajoutables depuis Référentiels > Champs
     * supplémentaires sans toucher au code. Les mêmes cases sont ajoutées
     * rétroactivement sur les PV déjà existants par
     * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()
     * quand un champ est créé/modifié après coup.
     */
    protected function creerCasesChampsSupplementaires(PvSoutenance $pvSoutenance): void
    {
        ChampSupplementaire::query()
            ->where('actif', true)
            ->where('pour_soutenance', true)
            ->get()
            ->filter(fn (ChampSupplementaire $champ) => $champ->applicableSoutenance($pvSoutenance->niveau))
            ->each(function (ChampSupplementaire $champ) use ($pvSoutenance) {
                PvSoutenanceFichier::create([
                    'pv_soutenance_id' => $pvSoutenance->id,
                    'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
                    'champ_supplementaire_id' => $champ->id,
                    'document_id' => null,
                ]);
            });
    }

    /** Type de document PV_SOUTENANCE déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentPvSoutenance(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'PV_SOUTENANCE')->first();
    }
}
