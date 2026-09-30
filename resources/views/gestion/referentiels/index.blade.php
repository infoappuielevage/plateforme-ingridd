@extends('layouts.gestion')

@section('title', $definition['pluriel'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $definition['pluriel'] }}</h1>
        <p class="text-muted small mb-0">
            Référentiel{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel, 'corbeille' => 1]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-trash3"></i> Corbeille
            </a>
            @if (\App\Support\ResourceAccess::can($definition['cle'], 'create'))
                <a href="{{ route('gestion.referentiels.create', ['referentiel' => $referentiel]) }}" class="btn btn-primary">
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

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="row g-2 align-items-end">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        <div class="col-md-8">
            <label class="form-label small">Recherche</label>
            <input type="text" name="recherche" class="form-control" placeholder="Code, libellé..." value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @if ($definition['a_code'])
                        <th>Code</th>
                    @endif
                    <th>Libellé</th>
                    @if ($definition['a_code'])
                        <th>Ordre</th>
                    @endif
                    <th>Actif</th>
                    <th>Documents</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        @if ($definition['a_code'])
                            <td><code>{{ $item->code }}</code></td>
                        @endif
                        <td>{{ $item->libelle }}</td>
                        @if ($definition['a_code'])
                            <td class="text-muted small">{{ $item->ordre }}</td>
                        @endif
                        <td>
                            @if ($item->actif)
                                <span class="badge text-bg-success">Actif</span>
                            @else
                                <span class="badge text-bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $item->documents_count }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    @if (\App\Support\ResourceAccess::can($definition['cle'], 'edit'))
                                        <a href="{{ route('gestion.referentiels.edit', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can($definition['cle'], 'delete'))
                                        <form method="POST" action="{{ route('gestion.referentiels.destroy', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre {{ $definition['singulier'] }} à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can($definition['cle'], 'delete'))
                                        <form method="POST" action="{{ route('gestion.referentiels.restore', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.referentiels.force-destroy', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline"
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
                        <td colspan="6">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun élément.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-footer bg-transparent">
            {{ $items->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
