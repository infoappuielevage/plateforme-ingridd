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

    {{--
        Boutons "Tout cocher" (demande explicite du client, "un bouton pour
        sélectionner toutes les permissions pour chaque [ressource], et un
        autre bouton pour tout sélectionner") : un bouton par carte de
        ressource (coche/décoche uniquement les cases de CETTE carte) et un
        bouton global ci-dessous (coche/décoche TOUTES les cases de la page).
        Bascule unique plutôt que deux boutons "Cocher"/"Décocher" distincts :
        son libellé reflète l'état courant et s'inverse au clic — voir le
        script en bas de ce fichier. Purement du JavaScript côté formulaire :
        aucun changement à la logique des droits elle-même (App\Support\ResourceAccess),
        l'administrateur garde la main et doit toujours cliquer sur
        "Enregistrer" pour valider.
    --}}
    <div class="d-flex justify-content-between align-items-center mb-2" style="max-width: 900px;">
        <h2 class="h6 text-muted mb-0">Permissions par ressource</h2>
        <button type="button" id="boutonToutCocherGlobal" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-check2-square"></i> Tout cocher
        </button>
    </div>

    <div id="blocPermissionsRessources">
        @foreach (\App\Support\ResourceAccess::RESSOURCES as $cle => $libelle)
            <div class="card shadow-sm mb-3" style="max-width: 900px;">
                <div class="card-header {{ $couleursEnTete[$loop->index % 4] }} d-flex align-items-center justify-content-between gap-2">
                    <span><i class="bi {{ \App\Support\ResourceAccess::ICONES[$cle] ?? 'bi-ui-checks' }}"></i> {{ $libelle }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary bouton-tout-cocher-ressource">
                        Tout cocher
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach (\App\Support\ResourceAccess::ACTIONS as $action => $libelleAction)
                            @continue($cle === 'audit_logs' && $action !== 'view')
                            @continue($cle === 'verrouillage_documents' && $action !== 'edit')
                            @continue($cle === 'depenses' && $action === 'delete_definitif')
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
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $role ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.roles.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
@endsection

@section('scripts')
    <script>
        (function () {
            var blocPermissions = document.getElementById('blocPermissionsRessources');
            if (! blocPermissions) return;

            function casesDe(conteneur) {
                return Array.from(conteneur.querySelectorAll('input[type="checkbox"]'));
            }

            function actualiserLibelle(bouton, cases) {
                var toutCoche = cases.length > 0 && cases.every(function (c) { return c.checked; });
                bouton.innerHTML = bouton.id === 'boutonToutCocherGlobal'
                    ? '<i class="bi bi-check2-square"></i> ' + (toutCoche ? 'Tout décocher' : 'Tout cocher')
                    : (toutCoche ? 'Tout décocher' : 'Tout cocher');
            }

            function basculer(cases) {
                var toutCoche = cases.length > 0 && cases.every(function (c) { return c.checked; });
                cases.forEach(function (c) { c.checked = ! toutCoche; });
            }

            var boutonGlobal = document.getElementById('boutonToutCocherGlobal');
            var casesGlobales = casesDe(blocPermissions);

            function actualiserTout() {
                boutonsParCarte.forEach(function (entree) {
                    actualiserLibelle(entree.bouton, entree.cases);
                });
                if (boutonGlobal) {
                    actualiserLibelle(boutonGlobal, casesGlobales);
                }
            }

            // Un bouton "Tout cocher" par carte de ressource — ne coche/décoche que les cases de CETTE carte.
            var boutonsParCarte = Array.from(blocPermissions.querySelectorAll('.bouton-tout-cocher-ressource')).map(function (bouton) {
                var carte = bouton.closest('.card');
                var cases = casesDe(carte);

                bouton.addEventListener('click', function () {
                    basculer(cases);
                    actualiserTout();
                });

                return {bouton: bouton, cases: cases};
            });

            // Bouton global — coche/décoche TOUTES les cases de la page, quelle que soit la carte.
            if (boutonGlobal) {
                boutonGlobal.addEventListener('click', function () {
                    basculer(casesGlobales);
                    actualiserTout();
                });
            }

            // Une case cochée/décochée à la main (indépendamment des boutons) met aussi à jour les libellés.
            casesGlobales.forEach(function (c) {
                c.addEventListener('change', actualiserTout);
            });

            actualiserTout();
        })();
    </script>
@endsection
