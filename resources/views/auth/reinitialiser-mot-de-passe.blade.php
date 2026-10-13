@extends('layouts.invite')

@section('title', 'Réinitialiser le mot de passe')

@section('content')
<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-3 text-primary">Réinitialiser le mot de passe</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $erreur)
                        <li>{{ $erreur }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('mot-de-passe.mettre-a-jour') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus
                       value="{{ old('email', $email) }}">
            </div>

            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <x-champ-mot-de-passe name="password" minlength="10" required autocomplete="new-password" />
                <div class="form-text">10 caractères minimum, avec au moins une majuscule, une minuscule et un chiffre.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmer le mot de passe</label>
                <x-champ-mot-de-passe name="password_confirmation" minlength="10" required autocomplete="new-password" />
            </div>

            <button type="submit" class="btn btn-primary w-100">Réinitialiser le mot de passe</button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small">&larr; Retour à la connexion</a>
        </div>
    </div>
</div>
@endsection
