@extends('layouts.gestion')

@section('title', ($role ? 'Modifier — ' : 'Ajouter — ').'un rôle')

@section('content')
<h1 class="h3">Rôles &amp; permissions</h1>
<h2 class="h5">{{ $role ? 'Modifier' : 'Ajouter' }}</h2>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 900px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<form method="POST"
      action="{{ $role ? route('gestion.roles.update', $role->id) : route('gestion.roles.store') }}">
    @csrf
    @if ($role) @method('PUT') @endif

    <div class="card shadow-sm mb-3" style="max-width: 900px;">
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
            <div class="card-header bg-transparent">{{ $libelle }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach (\App\Support\ResourceAccess::ACTIONS as $action => $libelleAction)
                        @continue($cle === 'audit_logs' && $action !== 'view')
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
