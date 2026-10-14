<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\TypeContrat;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel des catégories de contrats — voir le docblock de la migration
 * 2026_10_23_000001_create_types_contrats_table.php. MÊME PRINCIPE que
 * App\Http\Controllers\TypeDepenseController, à une différence près :
 * App\Models\TypeContrat n'a AUCUNE synchronisation rétroactive (un contrat
 * est créé à la demande, pas une ligne par année académique) — ajouter ou
 * réactiver une catégorie ici n'a donc besoin de rien recréer ailleurs,
 * contrairement à TypeDepense::synchroniserCasesRetroactives().
 */
class TypeContratController extends Controller
{
    use ListeTriable;

    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('types_contrats', 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'ordre', 'asc');

        $requete = TypeContrat::query()->when($corbeille, fn ($q) => $q->onlyTrashed());
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        $types = $requete->paginate(8)->withQueryString();

        return view('gestion.types-contrats.index', [
            'types' => $types,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && TypeContrat::onlyTrashed()->exists(),
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /** @return array<string, string> */
    protected function colonnesTriables(): array
    {
        return [
            'code' => 'code',
            'libelle' => 'libelle',
            'ordre' => 'ordre',
            'actif' => 'actif',
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('types_contrats', 'create'), 403);

        return view('gestion.types-contrats.form', ['type' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'create'), 403);

        TypeContrat::create($this->valider($request, null));

        return redirect()->route('gestion.types-contrats.index')->with('succes', 'Catégorie de contrat ajoutée.');
    }

    public function edit(TypeContrat $typeContrat): View
    {
        abort_unless(ResourceAccess::can('types_contrats', 'edit'), 403);

        return view('gestion.types-contrats.form', ['type' => $typeContrat]);
    }

    public function update(Request $request, TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'edit'), 403);

        $typeContrat->update($this->valider($request, $typeContrat));

        return redirect()->route('gestion.types-contrats.index')->with('succes', 'Catégorie de contrat modifiée.');
    }

    /**
     * Mise à la corbeille — jamais si cette catégorie a encore au moins un
     * contrat (actif OU déjà à la corbeille, voir le commentaire de
     * forceDestroy() ci-dessous : contrairement à App\Models\Depense, un
     * contrat porte de vrais documents, impossible de garantir qu'une
     * catégorie corbeillée est "forcément vide").
     */
    public function destroy(TypeContrat $typeContrat): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'delete'), 403);

        if ($typeContrat->contrats()->withTrashed()->exists()) {
            return back()->withErrors([
                'suppression' => 'Cette catégorie a encore au moins un contrat (actif ou à la corbeille) : impossible de la mettre à la corbeille.',
            ]);
        }

        $typeContrat->delete();

        return back()->with('succes', 'Catégorie de contrat mise à la corbeille.');
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'delete'), 403);

        TypeContrat::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('succes', 'Catégorie de contrat restaurée.');
    }

    /**
     * `contrats.type_contrat_id` est en `restrictOnDelete()` (voir le
     * docblock de 2026_10_23_000002_create_contrats_table.php) : destroy()
     * ci-dessus garantit déjà qu'une catégorie corbeillée ici n'a PLUS AUCUN
     * contrat, actif ou non — contrairement à TypeDepenseController::
     * forceDestroy() (qui doit lui-même purger les lignes Depense vides de
     * la catégorie), rien à nettoyer ici : la contrainte SQL ne peut donc
     * jamais se déclencher, ce garde-fou reste un filet de sécurité en plus.
     */
    public function forceDestroy(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'delete'), 403);

        $type = TypeContrat::onlyTrashed()->findOrFail($id);

        abort_if($type->contrats()->withTrashed()->exists(), 409, 'Cette catégorie a encore au moins un contrat : impossible de la supprimer définitivement.');

        $type->forceDelete();

        return back()->with('succes', 'Catégorie de contrat supprimée définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUTES les catégories
     * de contrat corbeillées en un clic — même principe que
     * DocumentController::viderCorbeille(). MÊME garde-fou que forceDestroy()
     * ci-dessus (une catégorie encore utilisée est ignorée plutôt que de
     * faire échouer toute l'opération — jamais d'abort_if() dans une boucle).
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_contrats', 'delete'), 403);

        $types = TypeContrat::onlyTrashed()->get();

        $supprimes = 0;
        $ignores = 0;
        foreach ($types as $type) {
            if ($type->contrats()->withTrashed()->exists()) {
                $ignores++;

                continue;
            }

            $type->forceDelete();
            $supprimes++;
        }

        $message = "{$supprimes} catégorie(s) de contrat supprimée(s) définitivement.";
        if ($ignores > 0) {
            $message .= " {$ignores} ignorée(s) car encore utilisée(s) par au moins un contrat.";
        }

        return back()->with('succes', $message);
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique) : MÊME garde-fou (catégorie
     * encore utilisée par un contrat, même corbeillé, jamais purgée),
     * limité aux catégories mises à la corbeille depuis plus de $jours
     * jours. Appelée depuis la console (aucun utilisateur authentifié) —
     * sans abort_unless ni redirection.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $types = TypeContrat::onlyTrashed()->where('deleted_at', '<', now()->subDays($jours))->get();

        $supprimes = 0;
        $ignores = 0;
        foreach ($types as $type) {
            if ($type->contrats()->withTrashed()->exists()) {
                $ignores++;

                continue;
            }

            $type->forceDelete();
            $supprimes++;
        }

        return ['supprimes' => $supprimes, 'ignores' => $ignores];
    }

    /** @return array<string, mixed> */
    protected function valider(Request $request, ?TypeContrat $type): array
    {
        $donnees = $request->validate([
            'code' => [
                $type ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('types_contrats', 'code')->ignore($type?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'ordre' => ['required', 'integer', 'min:0'],
        ]);

        $donnees['actif'] = $request->boolean('actif');

        // `code` ne se modifie jamais après création — sert de clé stable
        // pour l'URL de chaque onglet (voir routes/web.php, `{typeContrat:code}`)
        // et pour le seeder de départ.
        if ($type) {
            unset($donnees['code']);
        }

        return $donnees;
    }
}
