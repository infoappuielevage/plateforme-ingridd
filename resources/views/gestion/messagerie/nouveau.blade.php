{{--
    Nouveau message — reprend exactement la logique de diffusion de l'ancien
    écran Annonces (tout le monde / un rôle / un utilisateur précis), voir
    App\Http\Controllers\MessagerieController::envoyerNouveau(). Réservé en
    dur au rôle admin (voir le docblock du contrôleur).

    Variables attendues : $roles (slug => nom), $utilisateurs (id => nom).
--}}
@extends('layouts.gestion')

@section('title', 'Nouveau message — Messagerie')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Nouveau message</h1>
        <p class="text-muted small mb-0">Démarre une conversation avec un utilisateur, un rôle, ou tout le monde.</p>
    </div>
    <a href="{{ route('gestion.messagerie.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour à la messagerie
    </a>
</div>

<div class="card shadow-sm" style="max-width: 680px;">
<div class="card-body">
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('gestion.messagerie.envoyer-nouveau') }}" id="messagerie-form-nouveau">
    @csrf

    <div class="mb-3">
        <label class="form-label">Destinataires <span class="text-danger">*</span></label>

        <div class="form-check">
            <input type="radio" name="destinataire_type" id="destinataire_tous" class="form-check-input"
                   value="tous" data-cible="" @checked(old('destinataire_type', 'tous') === 'tous') required>
            <label class="form-check-label" for="destinataire_tous">Tout le monde</label>
        </div>
        <div class="form-check">
            <input type="radio" name="destinataire_type" id="destinataire_role" class="form-check-input"
                   value="role" data-cible="#messagerie-bloc-role" @checked(old('destinataire_type') === 'role')>
            <label class="form-check-label" for="destinataire_role">Un rôle précis</label>
        </div>
        <div class="form-check">
            <input type="radio" name="destinataire_type" id="destinataire_utilisateur" class="form-check-input"
                   value="utilisateur" data-cible="#messagerie-bloc-utilisateur" @checked(old('destinataire_type') === 'utilisateur')>
            <label class="form-check-label" for="destinataire_utilisateur">Un utilisateur précis</label>
        </div>
    </div>

    <div class="mb-3 d-none" id="messagerie-bloc-role">
        <label class="form-label">Rôle <span class="text-danger">*</span></label>
        <select name="role" class="form-select">
            <option value="" disabled @selected(! old('role'))>— Choisir —</option>
            @foreach ($roles as $slug => $nom)
                <option value="{{ $slug }}" @selected(old('role') === $slug)>{{ $nom }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3 d-none" id="messagerie-bloc-utilisateur">
        <label class="form-label">Utilisateur <span class="text-danger">*</span></label>
        <select name="utilisateur_id" class="form-select">
            <option value="" disabled @selected(! old('utilisateur_id'))>— Choisir —</option>
            @foreach ($utilisateurs as $id => $nom)
                <option value="{{ $id }}" @selected((int) old('utilisateur_id') === $id)>{{ $nom }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Titre <span class="text-danger">*</span></label>
        <input type="text" name="titre" class="form-control" maxlength="150" value="{{ old('titre') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Message <span class="text-danger">*</span></label>
        <textarea name="message" class="form-control" rows="5" maxlength="2000" required>{{ old('message') }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i> Envoyer</button>
    <a href="{{ route('gestion.messagerie.index') }}" class="btn btn-outline-secondary">Annuler</a>
</form>
</div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const form = document.getElementById('messagerie-form-nouveau');
        if (!form) return;

        const boutons = form.querySelectorAll('input[name="destinataire_type"]');
        const blocs = form.querySelectorAll('#messagerie-bloc-role, #messagerie-bloc-utilisateur');

        function actualiser() {
            const coche = form.querySelector('input[name="destinataire_type"]:checked');
            const cible = coche ? coche.getAttribute('data-cible') : '';

            blocs.forEach(function (bloc) {
                const visible = cible === ('#' + bloc.id);
                bloc.classList.toggle('d-none', ! visible);
                bloc.querySelectorAll('select').forEach(function (champ) {
                    champ.required = visible;
                });
            });
        }

        boutons.forEach(function (bouton) { bouton.addEventListener('change', actualiser); });
        actualiser();
    })();
</script>
@endsection
