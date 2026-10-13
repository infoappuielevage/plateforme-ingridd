<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\TypePieceEtudiant;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel des pièces attendues dans un dossier étudiant — voir le
 * docblock de la migration 2026_10_09_000001_create_types_pieces_etudiants_table.php.
 * MÊME PRINCIPE que App\Http\Controllers\ChampSupplementaireController
 * (contrôleur dédié plutôt que rattaché à ReferentielController, pour les
 * mêmes raisons : une synchronisation rétroactive sur les dossiers déjà
 * existants qu'aucun des référentiels "simples" ne fait) — voir
 * App\Models\TypePieceEtudiant::synchroniserCasesRetroactives(), appelée
 * ci-dessous (déplacée sur le modèle pour pouvoir aussi être appelée depuis
 * database/seeders/TypePieceEtudiantSeeder.php).
 */
class TypePieceEtudiantController extends Controller
{
    use ListeTriable;

    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'ordre', 'asc');

        $requete = TypePieceEtudiant::query()->when($corbeille, fn ($q) => $q->onlyTrashed());
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        $types = $requete->paginate(8)->withQueryString();

        return view('gestion.types-pieces-etudiants.index', [
            'types' => $types,
            'corbeille' => $corbeille,
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Portée" trie directement sur la colonne booléenne `recurrente`
     * elle-même (affichée via deux badges, voir la vue), pas sur le libellé
     * qui en dérive.
     *
     * @return array<string, string>
     */
    protected function colonnesTriables(): array
    {
        return [
            'code' => 'code',
            'libelle' => 'libelle',
            'recurrente' => 'recurrente',
            'ordre' => 'ordre',
            'actif' => 'actif',
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'create'), 403);

        return view('gestion.types-pieces-etudiants.form', ['type' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'create'), 403);

        $type = TypePieceEtudiant::create($this->valider($request, null));

        // Demande explicite du client : un type ajouté doit aussi profiter
        // aux dossiers déjà créés, pas seulement aux nouveaux.
        $type->synchroniserCasesRetroactives();

        return redirect()->route('gestion.types-pieces-etudiants.index')->with('succes', 'Type de pièce ajouté.');
    }

    public function edit(TypePieceEtudiant $typePieceEtudiant): View
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'edit'), 403);

        return view('gestion.types-pieces-etudiants.form', ['type' => $typePieceEtudiant]);
    }

    public function update(Request $request, TypePieceEtudiant $typePieceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'edit'), 403);

        $typePieceEtudiant->update($this->valider($request, $typePieceEtudiant));

        // Même synchro qu'à la création : réactiver un type (ou changer son
        // libellé) doit lui aussi profiter aux dossiers déjà créés.
        $typePieceEtudiant->synchroniserCasesRetroactives();

        return redirect()->route('gestion.types-pieces-etudiants.index')->with('succes', 'Type de pièce modifié.');
    }

    /** Mise à la corbeille — jamais si ce type est encore utilisé par au moins une case ayant un document déposé. */
    public function destroy(TypePieceEtudiant $typePieceEtudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'delete'), 403);

        if ($typePieceEtudiant->fichiers()->whereNotNull('document_id')->exists()) {
            return back()->withErrors([
                'suppression' => 'Ce type est encore utilisé par au moins un document déposé : impossible de le mettre à la corbeille.',
            ]);
        }

        $typePieceEtudiant->delete();

        return back()->with('succes', 'Type de pièce mis à la corbeille.');
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'delete'), 403);

        $type = TypePieceEtudiant::onlyTrashed()->findOrFail($id);
        $type->restore();

        $type->synchroniserCasesRetroactives();

        return back()->with('succes', 'Type de pièce restauré.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_pieces_etudiants', 'delete'), 403);

        TypePieceEtudiant::onlyTrashed()->findOrFail($id)->forceDelete();

        return back()->with('succes', 'Type de pièce supprimé définitivement.');
    }

    protected function valider(Request $request, ?TypePieceEtudiant $type): array
    {
        $donnees = $request->validate([
            'code' => [
                $type ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('types_pieces_etudiants', 'code')->ignore($type?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'ordre' => ['required', 'integer', 'min:0'],
        ]);

        $donnees['actif'] = $request->boolean('actif');

        if ($type) {
            // `code` ET `recurrente` ne se modifient jamais après création —
            // changer "recurrente" sur un type déjà utilisé ferait coexister
            // des cases rattachées à l'étudiant ET à une inscription pour un
            // même type, incohérent à l'affichage (voir
            // App\Http\Controllers\EtudiantController::creerCasesIdentite()/
            // creerCasesInscription()).
            unset($donnees['code']);
        } else {
            $donnees['recurrente'] = $request->boolean('recurrente');
        }

        return $donnees;
    }
}
