@extends('layouts.gestion')

@section('title', 'Rôles & permissions')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Rôles &amp; permissions</h1>
    <a href="{{ route('gestion.roles.create') }}" class="btn btn-primary">
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
                    <x-th-triable cle="slug" libelle="Identifiant" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.roles.index" />
                    <x-th-triable cle="name" libelle="Nom" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.roles.index" />
                    <x-th-triable cle="users_count" libelle="Utilisateurs" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.roles.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td><code>{{ $role->slug }}</code></td>
                        <td>{{ $role->name }}</td>
                        <td class="text-muted small">{{ $role->users_count }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('gestion.roles.edit', $role->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @unless ($role->slug === \App\Models\User::ROLE_ADMIN)
                                    <form method="POST" action="{{ route('gestion.roles.destroy', $role->id) }}" class="d-inline"
                                          onsubmit="return confirm('Supprimer ce rôle ? Les utilisateurs qui l\'ont encore perdront tous leurs droits (sauf connexion).');">
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
                        <td colspan="4">
                            <div class="empty-state text-center py-5">
                                <p class="text-muted mb-0">Aucun rôle.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($roles->hasPages())
        <div class="card-footer bg-transparent">
            {{ $roles->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
