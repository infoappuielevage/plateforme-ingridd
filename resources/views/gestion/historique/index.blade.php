@extends('layouts.gestion')

@section('title', 'Historique')

@section('content')
<h1 class="h3 mb-3">Historique</h1>

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

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entrees as $entree)
                    <tr>
                        <td class="text-muted small text-nowrap">{{ $entree->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $entree->user_name ?? '—' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $actions[$entree->action] ?? $entree->action }}</span></td>
                        <td>{{ $entree->resume }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-clock-history fs-1 text-muted"></i>
                                <p class="text-muted mb-0">Aucune entrée.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($entrees->hasPages())
        <div class="card-footer bg-transparent">
            {{ $entrees->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
