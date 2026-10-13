{{--
    Vue d'ensemble de toutes les corbeilles de l'application — demande
    explicite du client après discussion sur les insuffisances de la
    corbeille (voir le docblock de App\Support\CorbeillesGlobales). Chaque
    ligne reste filtrée par le droit "delete" de sa propre ressource (voir
    CorbeillesGlobales::elements()) : cet écran ne montre jamais plus que ce
    que l'utilisateur pourrait déjà voir en visitant chaque écran un par un.

    Variables attendues : $elements (voir CorbeillesGlobales::elements()),
    $total (voir CorbeillesGlobales::total()).
--}}
@extends('layouts.gestion')

@section('title', 'Corbeilles')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Corbeilles</h1>
    <span class="badge {{ $total > 0 ? 'text-bg-warning' : 'text-bg-success' }} fs-6">
        {{ $total }} élément{{ $total > 1 ? 's' : '' }} au total
    </span>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Cette page rassemble, pour chaque écran de l'application, ce qui se trouve actuellement à sa corbeille.
    Cliquez sur une ligne pour ouvrir l'écran concerné, en mode corbeille, et restaurer ou supprimer définitivement.
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Écran</th>
                    <th class="text-end">À la corbeille</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($elements as $element)
                    <tr class="{{ $element['compteur'] > 0 ? '' : 'text-muted' }}">
                        <td>
                            <i class="bi {{ $element['icone'] }}"></i>
                            {{ $element['libelle'] }}
                        </td>
                        <td class="text-end">
                            @if ($element['compteur'] > 0)
                                <span class="badge text-bg-warning">{{ $element['compteur'] }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ $element['url'] }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-up-right"></i> Ouvrir
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-trash3 fs-1 text-muted"></i>
                                <p class="text-muted mb-0">Aucune corbeille accessible avec vos droits actuels.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
