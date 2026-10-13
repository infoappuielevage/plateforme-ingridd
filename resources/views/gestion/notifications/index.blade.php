@extends('layouts.gestion')

@section('title', 'Mes notifications')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Mes notifications</h1>
    @if ($notifications->contains(fn ($notification) => is_null($notification->read_at)))
        <form method="POST" action="{{ route('gestion.notifications.tout-marquer-comme-lu') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-check2-all"></i> Tout marquer comme lu
            </button>
        </form>
    @endif
</div>

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <div class="list-group-item d-flex align-items-start gap-3 {{ $notification->read_at ? '' : 'bg-primary-subtle bg-opacity-25' }}">
                <span class="stat-icon flex-shrink-0">
                    <i class="bi {{ $notification->data['icone'] ?? 'bi-bell-fill' }}"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                        @if (! $notification->read_at)
                            <span class="badge bg-primary-subtle text-primary-emphasis">Non lue</span>
                        @endif
                    </div>
                    <div class="text-muted small">{{ $notification->data['message'] ?? '' }}</div>
                    <div class="text-muted small mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                </div>
                @unless ($notification->read_at)
                    <form method="POST" action="{{ route('gestion.notifications.marquer-comme-lue', $notification->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Marquer comme lue">
                            <i class="bi bi-check2"></i>
                        </button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="empty-state text-center py-5">
                <i class="bi bi-bell"></i>
                <p class="text-muted mb-0">Aucune notification pour le moment.</p>
            </div>
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="card-footer bg-transparent">
            {{ $notifications->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
