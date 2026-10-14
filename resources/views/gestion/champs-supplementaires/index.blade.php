@extends('layouts.gestion')

@section('title', 'Champs supplémentaires')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Champs supplémentaires</h1>
        <p class="text-muted small mb-0">
            Documents propres, ajoutables aux PV de délibération et/ou aux Relevés de notes sans toucher au code{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    @php
        // Deux droits désormais distincts (demande explicite du client) :
        // "Supprimer" ne couvre plus que la mise à la corbeille (réversible),
        // "Supprimer définitivement" (voir App\Support\ResourceAccess::ACTIONS)
        // couvre la suppression irréversible (vider la corbeille, supprimer
        // définitivement une ligne). Le lien "Corbeille" reste visible dès
        // que l'un des deux est accordé — utile même à un rôle qui ne peut
        // QUE purger, sans jamais mettre lui-même à la corbeille.
        $peutMettreCorbeille = \App\Support\ResourceAccess::can('champs_supplementaires', 'delete');
        $peutSupprimerDefinitivement = \App\Support\ResourceAccess::can('champs_supplementaires', 'delete_definitif');
    @endphp
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.champs-supplementaires.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            @if ($corbeillePeutEtreVidee && $peutSupprimerDefinitivement)
                <form method="POST" action="{{ route('gestion.champs-supplementaires.corbeille.vider') }}" class="d-inline"
                      onsubmit="return confirm('Tous les champs supplémentaires actuellement dans la corbeille seront supprimés définitivement. Cette action est irréversible. Continuer ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3-fill"></i> Vider la corbeille
                    </button>
                </form>
            @endif
        @else
            @if ($peutMettreCorbeille || $peutSupprimerDefinitivement)
                <a href="{{ route('gestion.champs-supplementaires.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('champs_supplementaires', 'create'))
                <a href="{{ route('gestion.champs-supplementaires.create') }}" class="btn btn-primary">
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
                    <x-th-triable cle="libelle" libelle="Libellé" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <x-th-triable cle="ordre" libelle="Ordre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <x-th-triable cle="actif" libelle="Actif" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <x-th-triable cle="pour_pv" libelle="PV de délibération" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <x-th-triable cle="pour_releve" libelle="Relevé de notes" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <x-th-triable cle="pour_soutenance" libelle="PV de soutenance" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.champs-supplementaires.index" />
                    <th>Cycles</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($champs as $champ)
                    <tr>
                        <td>{{ $champ->libelle }}</td>
                        <td class="text-muted small">{{ $champ->ordre }}</td>
                        <td>
                            @if ($champ->actif)
                                <span class="badge text-bg-success">Actif</span>
                            @else
                                <span class="badge text-bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td>{!! $champ->pour_pv ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td>{!! $champ->pour_releve ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td>{!! $champ->pour_soutenance ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                        <td class="text-muted small">{{ $champ->cyclesLibelle() }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    @if (\App\Support\ResourceAccess::can('champs_supplementaires', 'edit'))
                                        <a href="{{ route('gestion.champs-supplementaires.edit', $champ->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.champs-supplementaires.destroy', $champ->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce champ supplémentaire à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.champs-supplementaires.restore', $champ->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if ($peutSupprimerDefinitivement)
                                        <form method="POST" action="{{ route('gestion.champs-supplementaires.force-destroy', $champ->id) }}" class="d-inline"
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
                        <td colspan="8">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-file-earmark-plus fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun champ supplémentaire pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($champs->hasPages())
        <div class="card-footer bg-transparent">
            {{ $champs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
