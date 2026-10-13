@extends('layouts.gestion')

@section('title', 'Historique')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">
        Historique
        @if ($filtreModeleLibelle)
            <small class="text-muted fs-6">— {{ $filtreModeleLibelle }}</small>
        @endif
    </h1>
    @if ($filtreModeleLibelle)
        <a href="{{ route('gestion.historique.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg"></i> Voir tout l'historique
        </a>
    @endif
</div>

{{--
    Formulaire de recherche/filtre masqué quand on arrive via le raccourci
    "Voir l'historique" d'une fiche précise (model_type/model_id, voir
    App\Http\Controllers\AuditLogController::index()) : dans ce mode, la
    liste est déjà filtrée sur UN SEUL enregistrement — chercher/filtrer par
    action n'aurait pas de sens tant qu'on n'est pas revenu à la vue
    complète ci-dessus ("Voir tout l'historique").
--}}
@unless ($filtreModeleLibelle)
    <div class="card shadow-sm p-3 mb-3">
        <form method="GET" action="{{ route('gestion.historique.index') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small">Recherche</label>
                <input type="text" name="recherche" class="form-control" placeholder="Utilisateur, détail..." value="{{ request('recherche') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Action</label>
                <select name="action" class="form-select">
                    <option value="">Toutes les actions</option>
                    @foreach ($actions as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(request('action') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
@endunless

@include('gestion.historique._tableau')
@endsection
