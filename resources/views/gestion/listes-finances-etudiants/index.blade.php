@extends('layouts.gestion')

@section('title', 'Liste des étudiants (finances)')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Liste des étudiants (finances)</h1>
        <p class="text-muted small mb-0">
            Un dossier par année académique{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.listes-finances-etudiants.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('listes_finances_etudiants', 'delete'))
                <a href="{{ route('gestion.listes-finances-etudiants.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('listes_finances_etudiants', 'create'))
                <a href="{{ route('gestion.listes-finances-etudiants.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau dossier
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

@if (session('succes'))
    <div class="alert alert-success">{{ session('succes') }}</div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-th-triable cle="annee_academique" libelle="Année académique" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.listes-finances-etudiants.index" />
                    <x-th-triable cle="fichiers_deposes" libelle="Fichiers déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.listes-finances-etudiants.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($listes as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->anneeAcademique?->libelle }}</td>
                        <td class="text-muted small">{{ $item->fichiers->count() }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.listes-finances-etudiants.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('listes_finances_etudiants', 'delete'))
                                        <form method="POST" action="{{ route('gestion.listes-finances-etudiants.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce dossier à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('listes_finances_etudiants', 'delete'))
                                        <form method="POST" action="{{ route('gestion.listes-finances-etudiants.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.listes-finances-etudiants.force-destroy', $item->id) }}" class="d-inline"
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
                        <td colspan="3">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-people fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun dossier pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
