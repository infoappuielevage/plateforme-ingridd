<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
 * Non repris (volontairement, pour rester simple) : les actions groupées
 * (sélection multiple + activer/désactiver/corbeille en un clic) qu'avait
 * Filament — chaque ligne reste modifiable/supprimable individuellement.
 * Facile à ajouter plus tard si le besoin s'en fait sentir.
 */
class ReferentielController extends Controller
{
    /**
     * @var array<string, array{modele: class-string<Model>, cle: string, singulier: string, pluriel: string, a_code: bool, tri: array{0: string, 1: string}, aide_actif: string, regles_libelle?: array}>
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
        ],
        'niveaux' => [
            'modele' => Niveau::class,
            'cle' => 'niveaux',
            'singulier' => 'niveau',
            'pluriel' => 'Niveaux',
            'a_code' => true,
            'tri' => ['ordre', 'asc'],
            'aide_actif' => 'Un niveau inactif n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.',
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
        ],
    ];

    public function index(Request $request, string $referentiel): View
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'view'), 403);

        $modele = $definition['modele'];
        $corbeille = $request->boolean('corbeille');

        $items = $modele::query()
            ->withCount('documents')
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('libelle', 'like', $terme)->orWhere('code', 'like', $terme);
                });
            })
            ->orderBy(...$definition['tri'])
            ->paginate(20)
            ->withQueryString();

        return view('gestion.referentiels.index', [
            'referentiel' => $referentiel,
            'definition' => $definition,
            'items' => $items,
            'corbeille' => $corbeille,
        ]);
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
     * : même garde-fou que HasReferentielBulkActions côté Filament, pour ne
     * jamais laisser un document avec une référence invalide.
     */
    public function destroy(string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $item = $definition['modele']::findOrFail($id);

        if ($item->documents()->withTrashed()->exists()) {
            return back()->withErrors([
                'suppression' => ucfirst($definition['singulier']).' est encore utilisé(e) par au moins un document : impossible de le/la mettre à la corbeille.',
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

    public function forceDestroy(string $referentiel, int $id): RedirectResponse
    {
        $definition = $this->definition($referentiel);
        abort_unless(ResourceAccess::can($definition['cle'], 'delete'), 403);

        $definition['modele']::onlyTrashed()->findOrFail($id)->forceDelete();

        return back()->with('succes', ucfirst($definition['singulier']).' supprimé(e) définitivement.');
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
