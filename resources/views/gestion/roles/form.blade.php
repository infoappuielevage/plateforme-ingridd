@extends('layouts.gestion')

@section('title', ($role ? 'Modifier — ' : 'Ajouter — ').'un rôle')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Rôles &amp; permissions</h1>
        <p class="text-muted small mb-0">{{ $role ? 'Modifier — '.$role->name : 'Ajouter un rôle' }}</p>
    </div>
    <a href="{{ route('gestion.roles.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 900px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

{{--
    Redesign demandé par le client ("pour les rôles et permissions tout se
    ressemble, rends-la plus vivante comme ce qu'on avait fait pour les
    autres") : chaque carte de ressource reprend ici le même langage visuel
    que les écrans "show" de l'application (etats-financiers, pv-soutenances,
    sessions-deliberation...) — un en-tête coloré (rotation des 4 classes
    header-primary/header-accent/header-success/header-info définies dans
    public/css/style.css) avec une icône Bootstrap Icons, au lieu d'un simple
    "card-header bg-transparent" identique partout. L'icône de chaque
    ressource reprend celle déjà utilisée pour elle dans le menu de
    navigation (voir App\Support\ResourceAccess::ICONES).
--}}
@php
    $couleursEnTete = ['header-primary', 'header-accent', 'header-success', 'header-info'];
@endphp

<form method="POST"
      action="{{ $role ? route('gestion.roles.update', $role->id) : route('gestion.roles.store') }}">
    @csrf
    @if ($role) @method('PUT') @endif

    <div class="card shadow-sm mb-3" style="max-width: 900px;">
        <div class="card-header header-primary d-flex align-items-center gap-2">
            <i class="bi bi-person-badge"></i> Informations générales
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Identifiant (slug) <span class="text-danger">*</span></label>
                    <input type="text" name="slug" class="form-control" maxlength="50"
                           value="{{ old('slug', $role->slug ?? '') }}"
                           {{ $role ? 'disabled' : 'required' }}>
                    <div class="form-text">
                        Doit correspondre à la valeur du champ « Rôle » d'un utilisateur.
                        @if ($role) Non modifiable après création. @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nom affiché <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" maxlength="100"
                           value="{{ old('name', $role->name ?? '') }}" required>
                </div>
            </div>
        </div>
    </div>

    @php
        $permissionsActuelles = old('permissions', $role->permissions ?? []);
    @endphp

    @foreach (\App\Support\ResourceAccess::RESSOURCES as $cle => $libelle)
        <div class="card shadow-sm mb-3" style="max-width: 900px;">
            <div class="card-header {{ $couleursEnTete[$loop->index % 4] }} d-flex align-items-center gap-2">
                <i class="bi {{ \App\Support\ResourceAccess::ICONES[$cle] ?? 'bi-ui-checks' }}"></i> {{ $libelle }}
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach (\App\Support\ResourceAccess::ACTIONS as $action => $libelleAction)
                        @continue($cle === 'audit_logs' && $action !== 'view')
                        @continue($cle === 'verrouillage_documents' && $action !== 'edit')
                        <div class="col-6 col-md-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="permissions[{{ $cle }}][{{ $action }}]"
                                       id="permission_{{ $cle }}_{{ $action }}" class="form-check-input" value="1"
                                       @checked(data_get($permissionsActuelles, "{$cle}.{$action}", false))>
                                <label class="form-check-label" for="permission_{{ $cle }}_{{ $action }}">{{ $libelleAction }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $role ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.roles.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
@endsection
