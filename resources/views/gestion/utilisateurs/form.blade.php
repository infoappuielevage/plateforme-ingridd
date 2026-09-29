@extends('layouts.gestion')

@section('title', ($utilisateur ? 'Modifier — ' : 'Ajouter — ').'un utilisateur')

@section('content')
<h1 class="h3">Utilisateurs</h1>
<h2 class="h5">{{ $utilisateur ? 'Modifier' : 'Ajouter' }}</h2>

<div class="card shadow-sm" style="max-width: 620px;">
<div class="card-body">
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<form method="POST"
      action="{{ $utilisateur ? route('gestion.utilisateurs.update', $utilisateur->id) : route('gestion.utilisateurs.store') }}">
    @csrf
    @if ($utilisateur) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Nom complet <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" maxlength="150"
                   value="{{ old('name', $utilisateur->name ?? '') }}" required>
        </div>

        <div class="col-12">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" maxlength="150"
                   value="{{ old('email', $utilisateur->email ?? '') }}" required>
        </div>

        <div class="col-12">
            <label class="form-label">Rôle <span class="text-danger">*</span></label>
            <select name="role" class="form-select" required>
                <option value="" disabled @selected(! old('role', $utilisateur->role ?? ''))>— Choisir —</option>
                @foreach ($roles as $slug => $nom)
                    <option value="{{ $slug }}" @selected(old('role', $utilisateur->role ?? '') === $slug)>{{ $nom }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Mot de passe @unless ($utilisateur) <span class="text-danger">*</span> @endunless</label>
            <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password"
                   {{ $utilisateur ? '' : 'required' }}>
            @if ($utilisateur)
                <div class="form-text">Laisser vide pour ne pas modifier le mot de passe existant.</div>
            @else
                <div class="form-text">8 caractères minimum.</div>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $utilisateur ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.utilisateurs.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>
@endsection
