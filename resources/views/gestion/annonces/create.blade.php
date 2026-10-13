@extends('layouts.gestion')

@section('title', 'Annonces')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Annonces</h1>
        <p class="text-muted small mb-0">Envoie un message dans la cloche de notifications d'un utilisateur, de tout un rôle, ou de tout le monde.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 680px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm" style="max-width: 680px;">
    <div class="card-body">
        <form method="POST" action="{{ route('gestion.annonces.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Destinataires <span class="text-danger">*</span></label>

                <div class="form-check">
                    <input type="radio" name="destinataire_type" id="destinataire_tous" class="form-check-input" value="tous"
                           @checked(old('destinataire_type', 'tous') === 'tous')>
                    <label class="form-check-label" for="destinataire_tous">Tout le monde</label>
                </div>

                <div class="form-check">
                    <input type="radio" name="destinataire_type" id="destinataire_role" class="form-check-input" value="role"
                           @checked(old('destinataire_type') === 'role')>
                    <label class="form-check-label" for="destinataire_role">Un rôle précis</label>
                </div>
                <select name="role" id="champ_role" class="form-select mt-1 mb-2 d-none" style="max-width: 320px;">
                    <option value="" disabled @selected(! old('role'))>— Choisir un rôle —</option>
                    @foreach ($roles as $slug => $nom)
                        <option value="{{ $slug }}" @selected(old('role') === $slug)>{{ $nom }}</option>
                    @endforeach
                </select>

                <div class="form-check">
                    <input type="radio" name="destinataire_type" id="destinataire_utilisateur" class="form-check-input" value="utilisateur"
                           @checked(old('destinataire_type') === 'utilisateur')>
                    <label class="form-check-label" for="destinataire_utilisateur">Un utilisateur précis</label>
                </div>
                <select name="utilisateur_id" id="champ_utilisateur" class="form-select mt-1 d-none" style="max-width: 320px;">
                    <option value="" disabled @selected(! old('utilisateur_id'))>— Choisir un utilisateur —</option>
                    @foreach ($utilisateurs as $id => $nom)
                        <option value="{{ $id }}" @selected((string) old('utilisateur_id') === (string) $id)>{{ $nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Titre <span class="text-danger">*</span></label>
                <input type="text" name="titre" class="form-control" maxlength="150" value="{{ old('titre') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Message <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="4" maxlength="2000" required>{{ old('message') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Envoyer
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <script>
        // Affiche/masque la liste de rôles ou d'utilisateurs selon le type de
        // destinataire choisi — simple confort d'affichage, la validation
        // réelle (required_if) reste faite côté serveur (voir AnnonceController::store()).
        (function () {
            const champRole = document.getElementById('champ_role');
            const champUtilisateur = document.getElementById('champ_utilisateur');
            const boutonsDestinataire = document.querySelectorAll('input[name="destinataire_type"]');

            function actualiser() {
                const valeur = document.querySelector('input[name="destinataire_type"]:checked')?.value;
                champRole.classList.toggle('d-none', valeur !== 'role');
                champUtilisateur.classList.toggle('d-none', valeur !== 'utilisateur');
            }

            boutonsDestinataire.forEach((bouton) => bouton.addEventListener('change', actualiser));
            actualiser();
        })();
    </script>
@endsection
