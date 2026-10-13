@extends('layouts.gestion')

@section('title', 'Types de documents')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Types de documents</h1>
        <p class="text-muted small mb-0">
            Référentiel{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.types-documents.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            <a href="{{ route('gestion.types-documents.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-trash3"></i> Corbeille
            </a>
            <a href="{{ route('gestion.types-documents.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('types_documents', 'create'))
                <a href="{{ route('gestion.types-documents.create') }}" class="btn btn-primary">
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
    <form method="GET" action="{{ route('gestion.types-documents.index') }}" class="row g-2 align-items-end">
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
                    <x-th-triable cle="code" libelle="Code" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="libelle" libelle="Libellé" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="ordre" libelle="Ordre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="actif" libelle="Actif" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="epingle_secretariat" libelle="Secrétariat" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="champs_diplome_actifs" libelle="Champs diplôme" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="champs_soutenance_actifs" libelle="Champs soutenance" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="champ_semestre_actif" libelle="Semestre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <x-th-triable cle="documents_count" libelle="Documents" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-documents.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr>
                        <td><code>{{ $type->code }}</code></td>
                        <td>{{ $type->libelle }}</td>
                        <td class="text-muted small">{{ $type->ordre }}</td>
                        <td>
                            @if ($type->actif)
                                <span class="badge text-bg-success">Actif</span>
                            @else
                                <span class="badge text-bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td>{!! $type->epingle_secretariat ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td>{!! $type->champs_diplome_actifs ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td>{!! $type->champs_soutenance_actifs ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td>{!! $type->champ_semestre_actif ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td class="text-muted small">{{ $type->documents_count }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    @if (\App\Support\ResourceAccess::can('types_documents', 'edit'))
                                        <a href="{{ route('gestion.types-documents.edit', $type->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can('types_documents', 'delete'))
                                        <form method="POST" action="{{ route('gestion.types-documents.destroy', $type->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce type de document à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('types_documents', 'delete'))
                                        <form method="POST" action="{{ route('gestion.types-documents.restore', $type->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.types-documents.force-destroy', $type->id) }}" class="d-inline"
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
                        <td colspan="10">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun type de document.' }}
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
