@extends('layouts.gestion')

@section('title', 'Utilisateurs')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Utilisateurs</h1>
    <a href="{{ route('gestion.utilisateurs.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Ajouter
    </a>
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
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Dernière connexion</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($utilisateurs as $utilisateur)
                    <tr>
                        <td>{{ $utilisateur->name }}</td>
                        <td>{{ $utilisateur->email }}</td>
                        <td><span class="badge text-bg-secondary">{{ $utilisateur->role }}</span></td>
                        <td class="text-muted small">{{ $utilisateur->derniere_connexion_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('gestion.utilisateurs.edit', $utilisateur->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @unless ($utilisateur->id === auth()->id())
                                    <form method="POST" action="{{ route('gestion.utilisateurs.destroy', $utilisateur->id) }}" class="d-inline"
                                          onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state text-center py-5">
                                <p class="text-muted mb-0">Aucun utilisateur.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
