@extends('layouts.invite')

@section('title', 'Mot de passe oublié')

@section('content')
<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-3 text-primary">Mot de passe oublié</h1>

        <p class="text-muted small">
            Indiquez l'adresse email de votre compte : si elle y correspond, un lien de
            réinitialisation vous sera envoyé.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $erreur)
                        <li>{{ $erreur }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('mot-de-passe.envoyer') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}">
            </div>

            <button type="submit" class="btn btn-primary w-100">Envoyer le lien de réinitialisation</button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small">&larr; Retour à la connexion</a>
        </div>
    </div>
</div>
@endsection
