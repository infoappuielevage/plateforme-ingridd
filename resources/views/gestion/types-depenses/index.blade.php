@extends('layouts.gestion')

@section('title', 'Catégories de dépenses')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Catégories de dépenses</h1>
        <p class="text-muted small mb-0">
            Les catégories affichées en onglet sur l'écran Gestion des dépenses{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.types-depenses.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('types_depenses', 'delete'))
                <a href="{{ route('gestion.types-depenses.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('types_depenses', 'create'))
                <a href="{{ route('gestion.types-depenses.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Ajouter
                </a>
            @endif
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-th-triable cle="code" libelle="Code" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-depenses.index" />
                    <x-th-triable cle="libelle" libelle="Libellé" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-depenses.index" />
                    <x-th-triable cle="ordre" libelle="Ordre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-depenses.index" />
                    <x-th-triable cle="actif" libelle="Actif" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-depenses.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr>
                        <td class="text-muted small">{{ $type->code }}</td>
                        <td>{{ $type->libelle }}</td>
                        <td class="text-muted small">{{ $type->ordre }}</td>
                        <td>
                            @if ($type->actif)
                                <span class="badge text-bg-success">Actif</span>
                            @else
                                <span class="badge text-bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    @if (\App\Support\ResourceAccess::can('types_depenses', 'edit'))
                                        <a href="{{ route('gestion.types-depenses.edit', $type->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can('types_depenses', 'delete'))
                                        <form method="POST" action="{{ route('gestion.types-depenses.destroy', $type->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre cette catégorie à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('types_depenses', 'delete'))
                                        <form method="POST" action="{{ route('gestion.types-depenses.restore', $type->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.types-depenses.force-destroy', $type->id) }}" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement ? Cette action est irréversible.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Supprimer définitivement">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-cash-stack fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucune catégorie de dépense pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($types->hasPages())
        <div class="card-footer bg-transparent">
            {{ $types->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
