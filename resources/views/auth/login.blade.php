@extends('layouts.invite')

@section('title', 'Connexion')

@section('content')
<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-3 text-primary">Connexion</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $erreur)
                        <li>{{ $erreur }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('gestion.connexion.soumettre') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}">
            </div>

            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <x-champ-mot-de-passe name="password" required />
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="se_souvenir" class="form-check-input" id="se_souvenir">
                <label class="form-check-label" for="se_souvenir">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('mot-de-passe.oublie') }}" class="small">Mot de passe oublié ?</a>
        </div>
    </div>
</div>
@endsection
