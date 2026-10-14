<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Un seul contrôleur pour les trois référentiels "simples" — Filières,
 * Niveaux, Années académiques — qui partagent exactement la même forme
 * (code/libellé/ordre/actif, une relation `documents()`, une corbeille).
 * Remplace FiliereResource/NiveauResource/AnneeAcademiqueResource (sur
 * demande du client, qui ne veut plus de Filament) ainsi que le trait
 * App\Filament\Concerns\HasReferentielBulkActions, dont le garde-fou
 * "jamais de corbeille pour une ligne encore utilisée" est repris ici à
 * l'identique (voir supprimer() plus bas).
 *
 * Le type de document (voir TypeDocumentController) N'EST PAS ici : il a des
 * champs et un sous-référentiel (sous-types) en plus, qui ne rentrent pas
 * dans cette forme commune — un contrôleur dédié reste plus lisible pour lui
 * que d'ajouter des cas particuliers partout ici.
 *
 * Un seul contrôleur générique plutôt que trois quasi identiques : c'est
 * exactement le même principe de mutualisation que HasReferentielBulkActions
 * utilisait déjà côté Filament pour ces trois référentiels — voir la
 * constante DEFINITIONS ci-dessous, le seul endroit à modifier pour changer
 * le comportement d'un de ces trois écrans.
 *
 * Actions groupées (sélection multiple) : voir mettreCorbeilleGroupee() /
 * restaurerGroupe() / forceDestroyGroupe() plus bas — ajoutées sur demande
 * du client, même modèle que les 5 autres écrans qui les ont déjà
 * (Étudiants, PV de soutenance, sessions de délibération/relevés, réunions).
 */
class ReferentielController extends Controller
{
    use ListeTriable;

    /**
     * @var array<string, array{modele: class-string<Model>, cle: string, singulier: string, pluriel: string, a_code: bool, tri: array{0: string, 1: string}, aide_actif: string, regles_libelle?: array, colonne_fk?: string, tables_referencees?: array<int, string>}>
     */
    protected const DEFINITIONS = [
        'filieres' => [
            'modele' => Filiere::class,
            'cle' => 'filieres',
            'singulier' => 'filière',
            'pluriel' => 'Filières',
            'a_code' => true,
            'tri' => ['ordre', 'asc'],
            'aide_actif' => 'Une filière inactive n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.',
            // Voir encoreUtilise() plus bas : en plus de App\Models\Document
            // (déjà vérifié via $item->documents()), ces tables référencent
            // `filiere_id` en `restrictOnDelete()` — un audit demandé par le
            // client (voir le docblock de la classe) a trouvé que
            // "supprimer définitivement" une filière encore utilisée par
            // l'une d'elles plantait avec une erreur SQL brute au lieu d'un
            // message clair, exactement comme le bug déjà corrigé sur les
            // années académiques.
            'colonne_fk' => 'filiere_id',
            'tables_referencees' => ['sessions_deliberation', 'sessions_releves', 'pv_soutenances', 'etudiant_inscriptions'],
        ],
        'niveaux' => [
            'modele' => Niveau::class,
            'cle' => 'niveaux',
            'singulier' => 'niveau',
            'pluriel' => 'Niveaux',
            'a_code' => true,
            'tri' => ['ordre', 'asc'],
            'aide_actif' => 'Un niveau inactif n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.',
            'colonne_fk' => 'niveau_id',
            'tables_referencees' => ['etudiant_inscriptions'],
        ],
        'annees-academiques' => [
            'modele' => AnneeAcademique::class,
            'cle' => 'annees_academiques',
            'singulier' => 'année académique',
            'pluriel' => 'Années académiques',
            'a_code' => false,
            'tri' => ['libelle', 'desc'],
            'aide_actif' => 'Une année inactive n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.',
            'regles_libelle' => ['required', 'string', 'max:9', 'regex:/^\d{4}-\d{4}$/'],
            // Contrairement à filières/niveaux (unicité sur `code`), les
            // années académiques n'ont pas de code : l'unicité porte sur
            // `libelle` (voir la migration create_annees_academiques_table,
            // `->unique()` sur la colonne). Sans cette règle, créer un
            // doublon (ex. "2020-2021" déjà existant) ne remontait aucun
            // message clair : la requête passait la validation puis
            // plantait brutalement sur la contrainte SQL
            // (Illuminate\Database\UniqueConstraintViolationException, vu
            // en pratique par le client) — voir valider() plus bas.
            'libelle_unique' => true,
            'colonne_fk' => 'annee_academique_id',
            'tables_referencees' => [
                'sessions_deliberation', 'sessions_releves', 'etudiant_inscriptions', 'reunions',
                'recette_scolarites', 'recette_dossiers', 'depenses', 'listes_finances_etudiants',
            ],
        ],
    ];

    public function index(Request $request, string $referentiel): View
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables($definition);
        $triResolu = $this->resoudreTri($request, $colonnesTriables, $definition['tri'][0], $definition['tri'][1]);

        $items = $this->requeteListe($request, $definition, $corbeille, $colonnesTriables, $triResolu)
            ->paginate(8)
            ->withQueryString();

        return view('gestion.referentiels.index', [
            'referentiel' => $referentiel,
            'definition' => $definition,
            'items' => $items,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && $definition['modele']::onlyTrashed()->exists(),
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /**
     * Export Excel (.xlsx) de la liste — demandé par le client, respecte
     * EXACTEMENT les mêmes filtres que l'écran (voir requeteListe()
     * ci-dessous, partagée avec index()), simplement sans pagination. MÊME
     * ORDRE DE COLONNES que modeleImport() ci-dessous, pour qu'un export
     * puisse servir de base à un futur import.
     */
    public function export(Request $request, string $referentiel): StreamedResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables($definition);
        $triResolu = $this->resoudreTri($request, $colonnesTriables, $definition['tri'][0], $definition['tri'][1]);

        $items = $this->requeteListe($request, $definition, $corbeille, $colonnesTriables, $triResolu)->get();

        $entetes = $definition['a_code'] ? ['Code', 'Libellé', 'Ordre', 'Actif'] : ['Libellé', 'Actif'];

        $lignes = $items->map(fn (Model $item) => $definition['a_code']
            ? [$item->code, $item->libelle, $item->ordre, $item->actif ? 'oui' : 'non']
            : [$item->libelle, $item->actif ? 'oui' : 'non']);

        return FichiersExcel::exporter(
            Str::slug($definition['pluriel']).'-'.now()->format('Y-m-d').'.xlsx',
            $entetes,
            $lignes
        );
    }

    /**
     * @param  array<string, string|\Closure>  $colonnesTriables  voir colonnesTriables() ci-dessous
     * @param  array{0: string, 1: string}  $triResolu  voir App\Http\Controllers\Concerns\ListeTriable::resoudreTri()
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    protected function requeteListe(Request $request, array $definition, bool $corbeille, array $colonnesTriables, array $triResolu): \Illuminate\Database\Eloquent\Builder
    {
        // withCount('documents') toujours appliqué (même pour export(), qui
        // ne s'en sert pas) — nécessaire pour que l'alias `documents_count`
        // existe déjà quand colonnesTriables() trie dessus (voir
        // ListeTriable), même principe que TypeDocumentController::requeteListe().
        $query = $definition['modele']::query()
            ->withCount('documents')
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('libelle', 'like', $terme)->orWhere('code', 'like', $terme);
                });
            });

        $this->appliquerTri($query, $colonnesTriables, $triResolu);

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — DYNAMIQUE selon $definition['a_code'] : "Code"/"Ordre" n'existent
     * pas pour les années académiques (voir DEFINITIONS). "Documents" trie
     * sur l'alias `documents_count` ajouté par le ->withCount('documents')
     * de requeteListe() ci-dessus. Le tri par défaut reste
     * $definition['tri'] (celui que ->orderBy(...$definition['tri']) appliquait
     * auparavant), propre à chaque sous-référentiel (voir index()/export()).
     *
     * @return array<string, string>
     */
    protected function colonnesTriables(array $definition): array
    {
        $colonnes = ['libelle' => 'libelle', 'actif' => 'actif', 'documents_count' => 'documents_count'];

        if ($definition['a_code']) {
            $colonnes['code'] = 'code';
            $colonnes['ordre'] = 'ordre';
        }

        return $colonnes;
    }

    public function create(string $referentiel): View
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'create'), 403);

        return view('gestion.referentiels.form', [
            'referentiel' => $referentiel,
            'definition' => $definition,
            'item' => null,
        ]);
    }

    public function store(Request $request, string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'create'), 403);

        $donnees = $this->valider($request, $referentiel, null);

        $definition['modele']::create($donnees);

        return redirect()
            ->route('gestion.referentiels.index', ['referentiel' => $referentiel])
            ->with('succes', ucfirst($definition['singulier']).' ajouté(e).');
    }

    public function edit(string $referentiel, int $id): View
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'edit'), 403);

        $item = $definition['modele']::findOrFail($id);

        return view('gestion.referentiels.form', [
            'referentiel' => $referentiel,
            'definition' => $definition,
            'item' => $item,
        ]);
    }

    public function update(Request $request, string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'edit'), 403);

        $item = $definition['modele']::findOrFail($id);
        $donnees = $this->valider($request, $referentiel, $item);

        $item->update($donnees);

        return redirect()
            ->route('gestion.referentiels.index', ['referentiel' => $referentiel])
            ->with('succes', ucfirst($definition['singulier']).' modifié(e).');
    }

    /**
     * Mise à la corbeille — jamais si ce référentiel est encore utilisé par
     * au moins un document (même corbeillé, voir `withTrashed()` ci-dessous)
     * NI par au moins une ligne d'un des autres écrans qui le référencent en
     * `restrictOnDelete()` (voir DEFINITIONS, `tables_referencees`, et
     * encoreUtilise() plus bas) : même garde-fou que HasReferentielBulkActions
     * côté Filament, pour ne jamais laisser une ligne avec une référence
     * invalide.
     */
    public function destroy(string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $item = $definition['modele']::findOrFail($id);

        if ($this->encoreUtilise($item, $definition)) {
            return back()->withErrors([
                'suppression' => ucfirst($definition['singulier']).' est encore utilisé(e) ailleurs dans l\'application : impossible de le/la mettre à la corbeille.',
            ]);
        }

        $item->delete();

        return back()->with('succes', ucfirst($definition['singulier']).' mis(e) à la corbeille.');
    }

    public function restore(string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $definition['modele']::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('succes', ucfirst($definition['singulier']).' restauré(e).');
    }

    /**
     * Suppression définitive — même garde-fou que destroy() ci-dessus. Cette
     * revérification est nécessaire même si destroy() l'empêche déjà
     * d'arriver à la corbeille : une ligne a pu devenir utilisée APRÈS sa
     * mise à la corbeille (ex. une année académique mise à la corbeille,
     * puis réutilisée ailleurs par erreur avant sa suppression définitive) —
     * sans cette revérification, la requête passait et plantait brutalement
     * sur la contrainte SQL (`restrictOnDelete()`) plutôt que de remonter un
     * message clair, un audit demandé par le client a trouvé ce cas précis
     * totalement non vérifié ici (contrairement à forceDestroyGroupe()
     * ci-dessous, qui avait déjà ce garde-fou).
     */
    public function forceDestroy(string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $item = $definition['modele']::onlyTrashed()->findOrFail($id);

        if ($this->encoreUtilise($item, $definition)) {
            return back()->withErrors([
                'suppression' => ucfirst($definition['singulier']).' est encore utilisé(e) ailleurs dans l\'application : impossible de le/la supprimer définitivement.',
            ]);
        }

        $item->forceDelete();

        return back()->with('succes', ucfirst($definition['singulier']).' supprimé(e) définitivement.');
    }

    /**
     * Cette ligne est-elle encore référencée quelque part dans l'application
     * (document classé dedans OU ligne d'un autre écran la référençant en
     * `restrictOnDelete()`, voir DEFINITIONS) ? Centralise le garde-fou
     * utilisé par destroy()/forceDestroy()/mettreCorbeilleGroupee()/
     * forceDestroyGroupe() — sans ça, mettre à la corbeille (ou supprimer
     * définitivement) une filière/un niveau/une année académique encore
     * utilisé(e) ailleurs plante avec une erreur SQL brute au lieu d'un
     * message clair (même anti-pattern que le bug déjà corrigé sur le
     * doublon de libellé des années académiques, voir valider() plus bas).
     */
    protected function encoreUtilise(Model $item, array $definition): bool
    {
        if ($item->documents()->withTrashed()->exists()) {
            return true;
        }

        foreach ($definition['tables_referencees'] ?? [] as $table) {
            if (DB::table($table)->where($definition['colonne_fk'], $item->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Actions groupées (sélection multiple sur la liste) — demandées par le
     * client ("permettre de faire des sélections pour des actions sur les
     * tableaux différents"), même modèle que DocumentController::
     * mettreCorbeilleGroupee() et consorts, déjà repris sur les 5 autres
     * écrans (Étudiants, PV de soutenance, sessions de délibération/relevés,
     * réunions) — adapté ici aux trois référentiels de ce contrôleur (voir
     * DEFINITIONS, un seul jeu de méthodes pour les trois).
     *
     * Une ligne encore utilisée (document OU ligne d'un autre écran, voir
     * encoreUtilise()) est IGNORÉE en silence plutôt que de faire échouer
     * toute la sélection — même garde-fou que destroy() ci-dessus, mais
     * appliqué ligne par ligne : contrairement à DocumentController (un seul
     * whereNotIn() global sur les écrans dédiés, fixes), l'utilisation se
     * vérifie ici au cas par cas.
     */
    public function mettreCorbeilleGroupee(Request $request, string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $items = $definition['modele']::query()->whereIn('id', $donnees['ids'])->get();

        $mis = 0;
        $ignores = 0;
        foreach ($items as $item) {
            if ($this->encoreUtilise($item, $definition)) {
                $ignores++;

                continue;
            }

            $item->delete();
            $mis++;
        }

        return back()->with('succes', $this->messageGroupe($mis, 'mis(e)(s) à la corbeille', $ignores));
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request, string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $items = $definition['modele']::onlyTrashed()->whereIn('id', $donnees['ids'])->get();

        foreach ($items as $item) {
            $item->restore();
        }

        return back()->with('succes', $this->messageGroupe($items->count(), 'restauré(e)(s)'));
    }

    /**
     * Suppression définitive groupée — même garde-fou que mettreCorbeilleGroupee()
     * ci-dessus (une ligne encore utilisée est ignorée, jamais une erreur qui
     * bloquerait toute la sélection) : en pratique ne devrait jamais rien
     * ignorer ici, puisque destroy() empêche déjà une ligne encore utilisée
     * d'atteindre la corbeille — mais la revérification coûte peu et évite
     * tout contournement si la donnée change entre-temps (un document
     * nouvellement classé dans ce référentiel après sa mise à la corbeille).
     */
    public function forceDestroyGroupe(Request $request, string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $items = $definition['modele']::onlyTrashed()->whereIn('id', $donnees['ids'])->get();

        $supprimes = 0;
        $ignores = 0;
        foreach ($items as $item) {
            if ($this->encoreUtilise($item, $definition)) {
                $ignores++;

                continue;
            }

            $item->forceDelete();
            $supprimes++;
        }

        return back()->with('succes', $this->messageGroupe($supprimes, 'supprimé(e)(s) définitivement', $ignores));
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les éléments
     * corbeillés de CE référentiel en un clic — même principe que
     * DocumentController::viderCorbeille(). MÊME garde-fou que
     * forceDestroyGroupe() ci-dessus (encoreUtilise()) : un élément redevenu
     * utilisé après sa mise à la corbeille est ignoré plutôt que de faire
     * échouer toute l'opération.
     */
    public function viderCorbeille(string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $items = $definition['modele']::onlyTrashed()->get();

        $supprimes = 0;
        $ignores = 0;
        foreach ($items as $item) {
            if ($this->encoreUtilise($item, $definition)) {
                $ignores++;

                continue;
            }

            $item->forceDelete();
            $supprimes++;
        }

        return back()->with('succes', $this->messageGroupe($supprimes, 'supprimé(e)(s) définitivement', $ignores));
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique, voir Administration > Paramètres
     * > Corbeilles) : MÊME garde-fou (encoreUtilise()), limité aux éléments
     * mis à la corbeille depuis plus de $jours jours. Appelée depuis la
     * console (aucun utilisateur authentifié), une fois par sous-référentiel
     * (filieres/niveaux/annees-academiques) — sans abort_unless ni
     * redirection, juste des compteurs.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(string $referentiel, int $jours): array
    {
        $definition = $this->definition($referentiel);

        $items = $definition['modele']::onlyTrashed()->where('deleted_at', '<', now()->subDays($jours))->get();

        $supprimes = 0;
        $ignores = 0;
        foreach ($items as $item) {
            if ($this->encoreUtilise($item, $definition)) {
                $ignores++;

                continue;
            }

            $item->forceDelete();
            $supprimes++;
        }

        return ['supprimes' => $supprimes, 'ignores' => $ignores];
    }

    /** Message de retour générique des trois actions groupées ci-dessus — "élément(s)" plutôt que le mot exact du référentiel pour éviter tout problème d'accord (filière/niveau/année académique n'ont pas le même genre). */
    protected function messageGroupe(int $traites, string $action, int $ignores = 0): string
    {
        $message = "{$traites} élément(s) {$action}.";

        if ($ignores > 0) {
            $message .= " {$ignores} ignoré(s) car encore utilisé(s) par au moins un document.";
        }

        return $message;
    }

    /** Formulaire d'import — se contente d'afficher la page, voir import() pour le traitement. */
    public function formulaireImport(string $referentiel): View
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'create'), 403);

        return view('gestion.referentiels.import', [
            'referentiel' => $referentiel,
            'definition' => $definition,
        ]);
    }

    /** Modèle .xlsx vide (une ligne d'exemple) à remplir avant import — MÊME ORDRE DE COLONNES que import() ci-dessous. */
    public function modeleImport(string $referentiel): StreamedResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'create'), 403);

        $entetes = $definition['a_code'] ? ['Code', 'Libellé', 'Ordre', 'Actif'] : ['Libellé', 'Actif'];
        $exemple = $definition['a_code'] ? [['EXEMPLE', 'Exemple', 1, 'oui']] : [['2030-2031', 'oui']];

        return FichiersExcel::exporter(
            'modele-import-'.Str::slug($definition['pluriel']).'.xlsx',
            $entetes,
            $exemple
        );
    }

    /**
     * Import groupé depuis un fichier Excel — demandé par le client ("Excel
     * import/export"), MÊME FORMAT que modeleImport()/export() ci-dessus.
     * Correspondance par CODE pour filières/niveaux (stable, voir le
     * docblock de la classe), par LIBELLÉ pour les années académiques (qui
     * n'ont pas de code) — une ligne déjà connue MET À JOUR plutôt que de
     * dupliquer, le code lui-même ne change jamais après création (même
     * règle que valider() plus haut).
     *
     * Chaque ligne est traitée indépendamment : une ligne invalide est
     * signalée et sautée, elle ne fait jamais échouer le reste du fichier.
     */
    public function import(Request $request, string $referentiel): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'create'), 403);

        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $lignes = FichiersExcel::lireLignes($donnees['fichier']->getRealPath());

        $crees = 0;
        $misAJour = 0;
        $erreurs = [];

        foreach ($lignes as $numeroLigne => $ligne) {
            if ($definition['a_code']) {
                [$code, $libelle, $ordre, $actifTexte] = array_pad($ligne, 4, null);
            } else {
                [$libelle, $actifTexte] = array_pad($ligne, 2, null);
                $code = null;
                $ordre = null;
            }

            $regles = [
                'libelle' => $definition['regles_libelle'] ?? ['required', 'string', 'max:150'],
            ];
            if ($definition['a_code']) {
                $regles['code'] = ['required', 'string', 'max:50'];
                $regles['ordre'] = ['required', 'integer', 'min:0'];
            }

            $valide = Validator::make(compact('code', 'libelle', 'ordre'), $regles);

            if ($valide->fails()) {
                $erreurs[] = "Ligne {$numeroLigne} : ".$valide->errors()->first();

                continue;
            }

            $actif = in_array(Str::lower(trim((string) $actifTexte)), ['oui', '1', 'true', 'actif', 'yes'], true);

            $correspondance = $definition['a_code'] ? ['code' => $code] : ['libelle' => $libelle];

            try {
                $item = $definition['modele']::query()->where($correspondance)->first();

                $valeurs = ['libelle' => $libelle, 'actif' => $actif];
                if ($definition['a_code']) {
                    $valeurs['ordre'] = (int) $ordre;
                }

                if ($item) {
                    $item->update($valeurs);
                    $misAJour++;
                } else {
                    if ($definition['a_code']) {
                        $valeurs['code'] = $code;
                    }
                    $definition['modele']::create($valeurs);
                    $crees++;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $erreurs[] = "Ligne {$numeroLigne} : erreur inattendue — voir les journaux du serveur.";
            }
        }

        return redirect()->route('gestion.referentiels.index', ['referentiel' => $referentiel])->with('resultatImport', [
            'crees' => $crees,
            'mis_a_jour' => $misAJour,
            'erreurs' => $erreurs,
        ]);
    }

    /** @return array{modele: class-string<Model>, cle: string, singulier: string, pluriel: string, a_code: bool, tri: array{0: string, 1: string}, aide_actif: string, regles_libelle?: array} */
    protected function definition(string $referentiel): array
    {
        return self::DEFINITIONS[$referentiel] ?? abort(404);
    }

    protected function valider(Request $request, string $referentiel, ?Model $enregistrement): array
    {
        $definition = $this->definition($referentiel);

        $regles = [
            'libelle' => $definition['regles_libelle'] ?? ['required', 'string', 'max:150'],
            'actif' => ['sometimes', 'boolean'],
        ];

        // Voir DEFINITIONS ci-dessus : seules les années académiques ont une
        // contrainte d'unicité sur `libelle` (filières/niveaux l'ont sur
        // `code`, déjà couverte plus bas).
        if ($definition['libelle_unique'] ?? false) {
            $table = (new $definition['modele'])->getTable();
            $regles['libelle'][] = Rule::unique($table, 'libelle')->ignore($enregistrement?->id);
        }

        if ($definition['a_code']) {
            $table = (new $definition['modele'])->getTable();

            // "required" seulement à la création : le champ Code est
            // désactivé (disabled) sur le formulaire de modification (voir
            // gestion/referentiels/form.blade.php) — un champ HTML
            // désactivé n'est jamais envoyé dans la requête, donc l'exiger
            // ici en modification ferait échouer la validation à chaque
            // enregistrement, même sans y toucher.
            $regles['code'] = [
                $enregistrement ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique($table, 'code')->ignore($enregistrement?->id),
            ];
            $regles['ordre'] = ['required', 'integer', 'min:0'];
        }

        $donnees = $request->validate($regles);
        $donnees['actif'] = $request->boolean('actif');

        // Le code ne se modifie jamais après création (même règle que côté
        // Filament : TextInput::make('code')->disabled(...) — un document
        // déjà classé sous ce code ne doit jamais se retrouver avec un code
        // différent sous le capot sans le vouloir).
        if ($enregistrement) {
            unset($donnees['code']);
        }

        return $donnees;
    }
}
