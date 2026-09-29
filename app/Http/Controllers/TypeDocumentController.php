<?php

namespace App\Http\Controllers;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel des types de documents — remplace TypeDocumentResource (sur
 * demande du client, qui ne veut plus de Filament). Contrôleur dédié plutôt
 * que rattaché à App\Http\Controllers\ReferentielController (voir son
 * docblock) : ce référentiel a quatre réglages en plus (voir la migration
 * 2026_09_29_000021_... et App\Models\TypeDocument) et un sous-référentiel
 * (les sous-types, gérés directement sur la page de modification du type,
 * comme l'ancien SousTypesRelationManager de Filament).
 */
class TypeDocumentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('types_documents', 'view'), 403);

        $corbeille = $request->boolean('corbeille');

        $types = TypeDocument::query()
            ->withCount('documents')
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(fn ($q) => $q->where('libelle', 'like', $terme)->orWhere('code', 'like', $terme));
            })
            ->orderBy('ordre')
            ->paginate(20)
            ->withQueryString();

        return view('gestion.types-documents.index', [
            'types' => $types,
            'corbeille' => $corbeille,
        ]);
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('types_documents', 'create'), 403);

        return view('gestion.types-documents.form', ['type' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'create'), 403);

        TypeDocument::create($this->valider($request, null));

        return redirect()->route('gestion.types-documents.index')->with('succes', 'Type de document ajouté.');
    }

    public function edit(TypeDocument $type): View
    {
        abort_unless(ResourceAccess::can('types_documents', 'edit'), 403);

        return view('gestion.types-documents.form', [
            'type' => $type,
            'sousTypes' => $type->sousTypes()->withCount('documents')->orderBy('ordre')->get(),
        ]);
    }

    public function update(Request $request, TypeDocument $type): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'edit'), 403);

        $type->update($this->valider($request, $type));

        return redirect()->route('gestion.types-documents.index')->with('succes', 'Type de document modifié.');
    }

    /** Mise à la corbeille — même garde-fou que ReferentielController::destroy() : jamais si encore utilisé. */
    public function destroy(TypeDocument $type): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'delete'), 403);

        if ($type->documents()->withTrashed()->exists()) {
            return back()->withErrors(['suppression' => 'Ce type est encore utilisé par au moins un document : impossible de le mettre à la corbeille.']);
        }

        $type->delete();

        return back()->with('succes', 'Type de document mis à la corbeille.');
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'delete'), 403);

        TypeDocument::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('succes', 'Type de document restauré.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'delete'), 403);

        TypeDocument::onlyTrashed()->findOrFail($id)->forceDelete();

        return back()->with('succes', 'Type de document supprimé définitivement.');
    }

    protected function valider(Request $request, ?TypeDocument $type): array
    {
        $donnees = $request->validate([
            'code' => [
                $type ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('type_documents', 'code')->ignore($type?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'ordre' => ['required', 'integer', 'min:0'],
        ]);

        // Cases à cocher : jamais envoyées par le navigateur quand elles
        // sont décochées, contrairement à "actif" plus haut qui a un champ
        // hidden jumeau (voir gestion/types-documents/form.blade.php) —
        // ->boolean() gère les deux cas (présent ou totalement absent).
        $donnees['actif'] = $request->boolean('actif');
        $donnees['champs_diplome_actifs'] = $request->boolean('champs_diplome_actifs');
        $donnees['champs_soutenance_actifs'] = $request->boolean('champs_soutenance_actifs');
        $donnees['champ_semestre_actif'] = $request->boolean('champ_semestre_actif');
        $donnees['epingle_secretariat'] = $request->boolean('epingle_secretariat');

        if ($type) {
            unset($donnees['code']);
        }

        return $donnees;
    }

    /**
     * Sous-types (gérés directement sur la page de modification du type
     * parent — voir gestion/types-documents/form.blade.php) : create/update/
     * destroy seulement, pas de liste/corbeille propre — voir le docblock de
     * App\Models\SousTypeDocument pour pourquoi une suppression directe
     * (sans corbeille) est suffisante ici.
     */
    public function storeSousType(Request $request, TypeDocument $type): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'create'), 403);

        $type->sousTypes()->create($this->validerSousType($request));

        return back()->with('succes', 'Sous-type ajouté.');
    }

    public function updateSousType(Request $request, TypeDocument $type, SousTypeDocument $sousType): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'edit'), 403);
        abort_unless($sousType->type_document_id === $type->id, 404);

        $sousType->update($this->validerSousType($request, $sousType));

        return back()->with('succes', 'Sous-type modifié.');
    }

    /**
     * Suppression directe (pas de corbeille) : ->nullOnDelete() sur
     * `documents.sous_type_document_id` protège déjà tout document qui y
     * était classé (son sous-type redevient simplement vide) — même
     * comportement que l'ancien SousTypesRelationManager de Filament, qui
     * n'avait lui non plus aucun garde-fou sur cette suppression.
     */
    public function destroySousType(TypeDocument $type, SousTypeDocument $sousType): RedirectResponse
    {
        abort_unless(ResourceAccess::can('types_documents', 'delete'), 403);
        abort_unless($sousType->type_document_id === $type->id, 404);

        $sousType->delete();

        return back()->with('succes', 'Sous-type supprimé.');
    }

    /**
     * Champs préfixés `sous_type_...` (au lieu de `libelle`/`ordre`/`actif`
     * tout court) : le formulaire du type parent et la modale d'un sous-type
     * sont sur la même page (voir gestion/types-documents/form.blade.php).
     * En cas d'échec de validation sur la modale, Laravel repeuple les
     * anciennes valeurs par nom de champ (old()) indépendamment du error bag
     * utilisé — des noms identiques auraient donc écrasé le libellé/ordre/
     * actif du TYPE avec ceux du SOUS-TYPE au rechargement de la page.
     *
     * Le bag nommé "sousType" (validateWithBag) permet en plus à la vue de
     * distinguer "il y a une erreur sur le type" de "il y a une erreur sur un
     * sous-type", pour rouvrir la bonne modale plutôt que d'afficher l'alerte
     * générique du formulaire principal.
     */
    protected function validerSousType(Request $request, ?SousTypeDocument $sousType = null): array
    {
        $valide = $request->validateWithBag('sousType', [
            'sous_type_libelle' => ['required', 'string', 'max:150'],
            'sous_type_ordre' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'libelle' => $valide['sous_type_libelle'],
            'ordre' => $valide['sous_type_ordre'],
            'actif' => $request->boolean('sous_type_actif'),
        ];
    }
}
