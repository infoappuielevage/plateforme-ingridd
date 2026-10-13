@extends('layouts.gestion')

@section('title', 'Utilisateurs')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
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
                    <x-th-triable cle="name" libelle="Nom" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.utilisateurs.index" />
                    <x-th-triable cle="email" libelle="Email" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.utilisateurs.index" />
                    <x-th-triable cle="role" libelle="Rôle" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.utilisateurs.index" />
                    <x-th-triable cle="bloque_at" libelle="Statut" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.utilisateurs.index" />
                    <x-th-triable cle="derniere_connexion_at" libelle="Dernière connexion" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.utilisateurs.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($utilisateurs as $utilisateur)
                    <tr>
                        <td>{{ $utilisateur->name }}</td>
                        <td>{{ $utilisateur->email }}</td>
                        <td><span class="badge text-bg-secondary">{{ $utilisateur->role }}</span></td>
                        <td>
                            @if ($utilisateur->estBloque())
                                <span class="badge text-bg-danger" title="Bloqué le {{ $utilisateur->bloque_at->format('d/m/Y H:i') }}">
                                    <i class="bi bi-slash-circle"></i> Bloqué
                                </span>
                            @else
                                <span class="badge text-bg-success">Actif</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $utilisateur->derniere_connexion_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('gestion.utilisateurs.edit', $utilisateur->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('gestion.utilisateurs.reinitialiser-mot-de-passe', $utilisateur->id) }}" class="d-inline"
                                      onsubmit="return confirm('Envoyer un lien de réinitialisation du mot de passe à {{ $utilisateur->email }} ?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary" title="Réinitialiser le mot de passe">
                                        <i class="bi bi-key"></i>
                                    </button>
                                </form>
                                @unless ($utilisateur->id === auth()->id())
                                    <form method="POST" action="{{ route('gestion.utilisateurs.bloquer', $utilisateur->id) }}" class="d-inline"
                                          onsubmit="return confirm('{{ $utilisateur->estBloque() ? 'Débloquer' : 'Bloquer' }} ce compte ?');">
                                        @csrf
                                        <button type="submit" class="btn {{ $utilisateur->estBloque() ? 'btn-outline-success' : 'btn-outline-warning' }}"
                                                title="{{ $utilisateur->estBloque() ? 'Débloquer' : 'Bloquer' }}">
                                            <i class="bi {{ $utilisateur->estBloque() ? 'bi-unlock' : 'bi-lock' }}"></i>
                                        </button>
                                    </form>
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
                        <td colspan="6">
                            <div class="empty-state text-center py-5">
                                <p class="text-muted mb-0">Aucun utilisateur.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($utilisateurs->hasPages())
        <div class="card-footer bg-transparent">
            {{ $utilisateurs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
