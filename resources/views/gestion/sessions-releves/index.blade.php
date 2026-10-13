@extends('layouts.gestion')

@section('title', 'Relevés de notes')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Relevés de notes</h1>
        <p class="text-muted small mb-0">
            Sessions{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.sessions-releves.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('sessions_releves', 'delete'))
                <a href="{{ route('gestion.sessions-releves.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            <a href="{{ route('gestion.sessions-releves.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('sessions_releves', 'create'))
                <a href="{{ route('gestion.sessions-releves.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouvelle session
                </a>
            @endif
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

{{-- Onglets par cycle — voir resources/views/gestion/sessions-deliberation/index.blade.php
     (identique, miroir volontaire de cet écran). --}}
@unless ($corbeille)
    <ul class="nav nav-tabs-niveaux mb-3">
        @foreach ($onglets as $cle => $libelle)
            <li class="nav-item">
                <a class="nav-link niveau-tab-{{ $cle }} {{ $cycleActif === $cle ? 'active' : '' }}"
                   href="{{ route('gestion.sessions-releves.index', array_merge(request()->except('page'), $cle === 'tous' ? ['cycle' => null] : ['cycle' => $cle])) }}">
                    {{ $libelle }}
                </a>
            </li>
        @endforeach
    </ul>
@endunless

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.sessions-releves.index') }}" class="row g-2 align-items-end" id="formFiltresSessionsReleves">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        @if ($cycleActif !== 'tous')
            <input type="hidden" name="cycle" value="{{ $cycleActif }}">
        @endif
        <div class="col-md-4">
            <label class="form-label small">Année académique</label>
            <select name="annee_academique_id" class="form-select filtre-auto">
                <option value="">Toutes</option>
                @foreach ($anneesAcademiques as $annee)
                    <option value="{{ $annee->id }}" @selected(request('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Filière</label>
            <select name="filiere_id" class="form-select filtre-auto">
                <option value="">Toutes</option>
                @foreach ($filieres as $filiere)
                    <option value="{{ $filiere->id }}" @selected(request('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise les filtres (pas l'onglet de cycle) — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['annee_academique_id', 'filiere_id']))
                <a href="{{ route('gestion.sessions-releves.index', array_filter(['corbeille' => $corbeille ? 1 : null, 'cycle' => $cycleActif !== 'tous' ? $cycleActif : null])) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    // Les filtres à liste déroulante filtrent immédiatement au choix, sans
    // attendre un clic sur "Filtrer" — même comportement que pour les PV de
    // soutenance (demande explicite du client).
    document.querySelectorAll('#formFiltresSessionsReleves .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresSessionsReleves').submit();
        });
    });
</script>

@php $peutSupprimerSessionsReleves = \App\Support\ResourceAccess::can('sessions_releves', 'delete'); @endphp

@if ($peutSupprimerSessionsReleves)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que
         resources/views/gestion/documents/index.blade.php. --}}
    <form id="formSelectionSessionsReleves" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesSessionsReleves" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionSessionsReleves">0</strong> session(s) sélectionnée(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                <button type="submit" form="formSelectionSessionsReleves" formaction="{{ route('gestion.sessions-releves.corbeille-groupee') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Mettre les sessions sélectionnées à la corbeille ?');">
                    <i class="bi bi-trash3"></i> Mettre à la corbeille
                </button>
            @else
                <button type="submit" form="formSelectionSessionsReleves" formaction="{{ route('gestion.sessions-releves.corbeille.restaurer-groupe') }}"
                        class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                </button>
                <button type="submit" form="formSelectionSessionsReleves" formaction="{{ route('gestion.sessions-releves.corbeille.force-destroy-groupe') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Supprimer définitivement les sessions sélectionnées ? Cette action est irréversible.');">
                    <i class="bi bi-x-circle"></i> Supprimer définitivement
                </button>
            @endif
        </div>
    </div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @if ($peutSupprimerSessionsReleves)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousSessionsReleves" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="annee_academique" libelle="Année académique" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.sessions-releves.index" />
                    <x-th-triable cle="session" libelle="Session" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.sessions-releves.index" />
                    <x-th-triable cle="cycle" libelle="Cycle" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.sessions-releves.index" />
                    <x-th-triable cle="filiere" libelle="Filière" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.sessions-releves.index" />
                    <x-th-triable cle="fichiers_deposes" libelle="Fichiers déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.sessions-releves.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $item)
                    <tr>
                        @if ($peutSupprimerSessionsReleves)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-session-releve"
                                       name="sessions[]" value="{{ $item->id }}" form="formSelectionSessionsReleves">
                            </td>
                        @endif
                        <td>{{ $item->anneeAcademique->libelle }}</td>
                        <td>{{ $item->sessionLibelle() }}</td>
                        <td><span class="badge {{ $item->cycleBadgeClasse() }}">{{ $item->cycleLibelle() }}</span></td>
                        <td>{{ $item->filiere->libelle }}</td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.sessions-releves.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('sessions_releves', 'delete'))
                                        <form method="POST" action="{{ route('gestion.sessions-releves.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce relevé de notes à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('sessions_releves', 'delete'))
                                        <form method="POST" action="{{ route('gestion.sessions-releves.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.sessions-releves.force-destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement ? Cette action est irréversible.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Supprimer définitivement">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $peutSupprimerSessionsReleves ? 7 : 6 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-journal-text fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun relevé de notes pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($sessions->hasPages())
        <div class="card-footer bg-transparent">
            {{ $sessions->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerSessionsReleves)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-session-releve');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousSessionsReleves');
                const barre = document.getElementById('barreActionsGroupeesSessionsReleves');
                const compteur = document.getElementById('compteurSelectionSessionsReleves');

                function casesCochees() {
                    return Array.from(casesLigne).filter(function (c) { return c.checked; });
                }

                function actualiserBarre() {
                    const nombre = casesCochees().length;
                    barre.classList.toggle('d-none', nombre === 0);
                    barre.classList.toggle('d-flex', nombre > 0);
                    compteur.textContent = nombre;
                    if (caseTout) {
                        caseTout.checked = nombre > 0 && nombre === casesLigne.length;
                        caseTout.indeterminate = nombre > 0 && nombre < casesLigne.length;
                    }
                }

                casesLigne.forEach(function (c) {
                    c.addEventListener('change', actualiserBarre);
                });

                if (caseTout) {
                    caseTout.addEventListener('change', function () {
                        casesLigne.forEach(function (c) { c.checked = caseTout.checked; });
                        actualiserBarre();
                    });
                }

                actualiserBarre();
            })();
        </script>
    @endsection
@endif
