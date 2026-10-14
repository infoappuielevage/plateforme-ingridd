<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\TypeDepense;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel des catégories de dépenses — voir le docblock de la migration
 * 2026_10_20_000001_create_types_depenses_table.php. MÊME PRINCIPE que
 * App\Http\Controllers\TypePieceEtudiantController (contrôleur dédié plutôt
 * que rattaché à ReferentielController, pour la même raison : une
 * synchronisation rétroactive sur les années académiques déjà existantes
 * qu'aucun des référentiels "simples" ne fait) — voir
 * App\Models\TypeDepense::synchroniserCasesRetroactives(), appelée
 * ci-dessous.
 */
class TypeDepenseController extends Controller
{
    use ListeTriable;

    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('types_depenses', 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'ordre', 'asc');

        $requete = TypeDepense::query()->when($corbeille, fn ($q) => $q->onlyTrashed());
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        $types = $requete->paginate(8)->withQueryString();

        return view('gestion.types-depenses.index', [
            'types' => $types,
            'corbeille' => $corbeille,
            'corbeillePeutEtreVidee' => $corbeille && TypeDepense::onlyTrashed()->exists(),
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
        abort_unless(ResourceAccess::can('types_depenses', 'create'), 403);

        return view('gestion.types-depenses.form', ['type' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'create'), 403);

        $type = TypeDepense::create($this->valider($request, null));

        // Demande explicite du client : une catégorie ajoutée doit aussi
        // profiter aux années académiques déjà créées, pas seulement aux
        // nouvelles.
        $type->synchroniserCasesRetroactives();

        return redirect()->route('gestion.types-depenses.index')->with('succes', 'Catégorie de dépense ajoutée.');
    }

    public function edit(TypeDepense $typeDepense): View
    {
        abort_unless(ResourceAccess::can('types_depenses', 'edit'), 403);

        return view('gestion.types-depenses.form', ['type' => $typeDepense]);
    }

    public function update(Request $request, TypeDepense $typeDepense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'edit'), 403);

        $typeDepense->update($this->valider($request, $typeDepense));

        // Même synchro qu'à la création : réactiver une catégorie doit lui
        // aussi profiter aux années académiques déjà créées.
        $typeDepense->synchroniserCasesRetroactives();

        return redirect()->route('gestion.types-depenses.index')->with('succes', 'Catégorie de dépense modifiée.');
    }

    /** Mise à la corbeille — jamais si cette catégorie a encore au moins un bordereau/fichier Excel déposé. */
    public function destroy(TypeDepense $typeDepense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'delete'), 403);

        $utilisee = $typeDepense->depenses()
            ->where(fn ($q) => $q->whereNotNull('bordereau_document_id')->orWhereNotNull('excel_document_id'))
            ->exists();

        if ($utilisee) {
            return back()->withErrors([
                'suppression' => 'Cette catégorie a encore au moins un document déposé : impossible de la mettre à la corbeille.',
            ]);
        }

        $typeDepense->delete();

        return back()->with('succes', 'Catégorie de dépense mise à la corbeille.');
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'delete'), 403);

        $type = TypeDepense::onlyTrashed()->findOrFail($id);
        $type->restore();

        $type->synchroniserCasesRetroactives();

        return back()->with('succes', 'Catégorie de dépense restaurée.');
    }

    /**
     * `depenses.type_depense_id` est en `restrictOnDelete()` (voir le
     * docblock de 2026_10_20_000002_create_depenses_table.php) : un audit
     * demandé par le client a trouvé que cette méthode n'avait AUCUN
     * garde-fou, contrairement à destroy() ci-dessus — forceDelete() sur une
     * catégorie plantait donc toujours avec une erreur SQL brute dès qu'au
     * moins une année académique existait (le cas normal, voir
     * App\Models\TypeDepense::synchroniserCasesRetroactives()).
     *
     * Les lignes App\Models\Depense de cette catégorie sont forcément VIDES
     * à ce stade (bordereau_document_id/excel_document_id tous deux null) :
     * destroy() ci-dessus bloque déjà la mise à la corbeille tant qu'un
     * document est déposé, et une fois à la corbeille le type n'est plus
     * accessible en upload (`{typeDepense:code}` exclut les types corbeillés
     * de la liaison de route) — aucun nouveau dépôt n'a donc pu avoir lieu
     * entre-temps. Les supprimer directement ici (plutôt que de bloquer la
     * suppression définitive) est donc sans perte de donnée.
     */
    public function forceDestroy(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'delete_definitif'), 403);

        $type = TypeDepense::onlyTrashed()->findOrFail($id);

        foreach ($type->depenses as $depense) {
            $depense->forceDelete();
        }

        $type->forceDelete();

        return back()->with('succes', 'Catégorie de dépense supprimée définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUTES les catégories
     * de dépenses corbeillées en un clic — même raisonnement que
     * forceDestroy() ci-dessus (lignes App\Models\Depense forcément vides à
     * ce stade, voir son docblock), appliqué à CHAQUE catégorie corbeillée
     * plutôt qu'une seule.
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_depenses', 'delete_definitif'), 403);

        $types = TypeDepense::onlyTrashed()->with('depenses')->get();

        foreach ($types as $type) {
            foreach ($type->depenses as $depense) {
                $depense->forceDelete();
            }

            $type->forceDelete();
        }

        return back()->with('succes', $types->count().' catégorie(s) de dépense supprimée(s) définitivement.');
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique) : MÊME cascade (dépenses
     * rattachées), limitée aux catégories mises à la corbeille depuis plus
     * de $jours jours. Appelée depuis la console (aucun utilisateur
     * authentifié) — sans abort_unless ni redirection.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $types = TypeDepense::onlyTrashed()->with('depenses')->where('deleted_at', '<', now()->subDays($jours))->get();

        foreach ($types as $type) {
            foreach ($type->depenses as $depense) {
                $depense->forceDelete();
            }

            $type->forceDelete();
        }

        return ['supprimes' => $types->count(), 'ignores' => 0];
    }

    /** @return array<string, mixed> */
    protected function valider(Request $request, ?TypeDepense $type): array
    {
        $donnees = $request->validate([
            'code' => [
                $type ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('types_depenses', 'code')->ignore($type?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'ordre' => ['required', 'integer', 'min:0'],
        ]);

        $donnees['actif'] = $request->boolean('actif');

        // `code` ne se modifie jamais après création — sert de clé stable
        // pour l'URL de chaque onglet (voir routes/web.php, `{typeDepense:code}`)
        // et pour le seeder de départ.
        if ($type) {
            unset($donnees['code']);
        }

        return $donnees;
    }
}
