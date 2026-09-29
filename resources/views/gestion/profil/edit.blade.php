@extends('layouts.gestion')

@section('title', 'Mon profil')

@section('content')
<h1 class="h3 mb-3">Mon profil</h1>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 620px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('gestion.profil.update') }}" enctype="multipart/form-data" style="max-width: 620px;">
    @csrf
    @method('PUT')

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent">Informations</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Photo de profil</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        @if ($utilisateur->avatar)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($utilisateur->avatar) }}" alt="Photo de profil actuelle"
                                 class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="bi bi-person-fill fs-3 text-secondary"></i>
                            </div>
                        @endif
                    </div>
                    <input type="file" name="avatar" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                    <div class="form-text">PNG, JPEG ou WebP, carrée de préférence ({{ $tailleMaxAvatarMo }} Mo maximum). Laissez vide pour garder vos initiales.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" maxlength="150"
                           value="{{ old('name', $utilisateur->name) }}" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" maxlength="150"
                           value="{{ old('email', $utilisateur->email) }}" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent">Mot de passe</div>
        <div class="card-body">
            <p class="text-muted small">Laissez ces champs vides pour ne pas changer votre mot de passe.</p>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Mot de passe actuel</label>
                    <input type="password" name="mot_de_passe_actuel" class="form-control" autocomplete="current-password">
                </div>
                <div class="col-12">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="nouveau_mot_de_passe" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="nouveau_mot_de_passe_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted small">
                Dernière connexion :
                {{ $utilisateur->derniere_connexion_at?->format('d/m/Y H:i') ?? 'Jamais' }}
                @if ($utilisateur->derniere_connexion_at)
                    depuis {{ $utilisateur->derniere_connexion_ip ?? '—' }}
                @endif
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
@endsection
