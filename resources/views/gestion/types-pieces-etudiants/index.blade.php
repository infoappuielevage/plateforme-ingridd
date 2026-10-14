@extends('layouts.gestion')

@section('title', 'Types de pièces (dossier étudiant)')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Types de pièces (dossier étudiant)</h1>
        <p class="text-muted small mb-0">
            La liste des pièces attendues dans chaque dossier étudiant{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    @php
        // Deux droits désormais distincts (demande explicite du client) :
        // voir le commentaire équivalent dans champs-supplementaires/index.blade.php.
        $peutMettreCorbeille = \App\Support\ResourceAccess::can('types_pieces_etudiants', 'delete');
        $peutSupprimerDefinitivement = \App\Support\ResourceAccess::can('types_pieces_etudiants', 'delete_definitif');
    @endphp
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.types-pieces-etudiants.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            @if ($corbeillePeutEtreVidee && $peutSupprimerDefinitivement)
                <form method="POST" action="{{ route('gestion.types-pieces-etudiants.corbeille.vider') }}" class="d-inline"
                      onsubmit="return confirm('Tous les types de pièce actuellement dans la corbeille seront supprimés définitivement. Cette action est irréversible. Continuer ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3-fill"></i> Vider la corbeille
                    </button>
                </form>
            @endif
        @else
            @if ($peutMettreCorbeille || $peutSupprimerDefinitivement)
                <a href="{{ route('gestion.types-pieces-etudiants.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('types_pieces_etudiants', 'create'))
                <a href="{{ route('gestion.types-pieces-etudiants.create') }}" class="btn btn-primary">
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
                    <x-th-triable cle="code" libelle="Code" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-pieces-etudiants.index" />
                    <x-th-triable cle="libelle" libelle="Libellé" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-pieces-etudiants.index" />
                    <x-th-triable cle="recurrente" libelle="Portée" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-pieces-etudiants.index" />
                    <x-th-triable cle="ordre" libelle="Ordre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-pieces-etudiants.index" />
                    <x-th-triable cle="actif" libelle="Actif" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.types-pieces-etudiants.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr>
                        <td class="text-muted small">{{ $type->code }}</td>
                        <td>{{ $type->libelle }}</td>
                        <td>
                            @if ($type->recurrente)
                                <span class="badge text-bg-info">Par année académique</span>
                            @else
                                <span class="badge text-bg-secondary">Par dossier (identité)</span>
                            @endif
                        </td>
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
                                    @if (\App\Support\ResourceAccess::can('types_pieces_etudiants', 'edit'))
                                        <a href="{{ route('gestion.types-pieces-etudiants.edit', $type->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.types-pieces-etudiants.destroy', $type->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce type de pièce à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.types-pieces-etudiants.restore', $type->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if ($peutSupprimerDefinitivement)
                                        <form method="POST" action="{{ route('gestion.types-pieces-etudiants.force-destroy', $type->id) }}" class="d-inline"
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
                                <i class="bi bi-folder2-open fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun type de pièce pour l\'instant.' }}
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
