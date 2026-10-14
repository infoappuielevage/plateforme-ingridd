@extends('layouts.gestion')

@section('title', 'États financiers')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">États financiers</h1>
        <p class="text-muted small mb-0">
            Un enregistrement par année{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.etats-financiers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            @if ($corbeillePeutEtreVidee && \App\Support\ResourceAccess::can('etats_financiers', 'delete_definitif'))
                <form method="POST" action="{{ route('gestion.etats-financiers.corbeille.vider') }}" class="d-inline"
                      onsubmit="return confirm('Tous les états financiers actuellement dans la corbeille seront supprimés définitivement. Cette action est irréversible. Continuer ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3-fill"></i> Vider la corbeille
                    </button>
                </form>
            @endif
        @else
            @if (\App\Support\ResourceAccess::can('etats_financiers', 'delete') || \App\Support\ResourceAccess::can('etats_financiers', 'delete_definitif'))
                <a href="{{ route('gestion.etats-financiers.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('etats_financiers', 'create'))
                <a href="{{ route('gestion.etats-financiers.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouvel état financier
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

@if (session('succes'))
    <div class="alert alert-success">{{ session('succes') }}</div>
@endif

@php
    // Deux droits désormais distincts (demande explicite du client) : voir
    // le commentaire équivalent dans champs-supplementaires/index.blade.php.
    $peutMettreCorbeille = \App\Support\ResourceAccess::can('etats_financiers', 'delete');
    $peutSupprimerDefinitivement = \App\Support\ResourceAccess::can('etats_financiers', 'delete_definitif');
    $peutSupprimerEtatsFinanciers = $peutMettreCorbeille || $peutSupprimerDefinitivement;
@endphp

@if ($peutSupprimerEtatsFinanciers)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que resources/views/gestion/reunions/index.blade.php. --}}
    <form id="formSelectionEtatsFinanciers" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesEtatsFinanciers" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionEtatsFinanciers">0</strong> élément(s) sélectionné(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                @if ($peutMettreCorbeille)
                    <button type="submit" form="formSelectionEtatsFinanciers" formaction="{{ route('gestion.etats-financiers.corbeille-groupee') }}"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Mettre les états financiers sélectionnés à la corbeille ?');">
                        <i class="bi bi-trash3"></i> Mettre à la corbeille
                    </button>
                @endif
            @else
                @if ($peutMettreCorbeille)
                    <button type="submit" form="formSelectionEtatsFinanciers" formaction="{{ route('gestion.etats-financiers.corbeille.restaurer-groupe') }}"
                            class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                    </button>
                @endif
                @if ($peutSupprimerDefinitivement)
                    <button type="submit" form="formSelectionEtatsFinanciers" formaction="{{ route('gestion.etats-financiers.corbeille.force-destroy-groupe') }}"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Supprimer définitivement les éléments sélectionnés ? Cette action est irréversible.');">
                        <i class="bi bi-x-circle"></i> Supprimer définitivement
                    </button>
                @endif
            @endif
        </div>
    </div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @if ($peutSupprimerEtatsFinanciers)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousEtatsFinanciers" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="annee" libelle="Année" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etats-financiers.index" />
                    <x-th-triable cle="fichier" libelle="Fichier" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etats-financiers.index" />
                    <x-th-triable cle="observation" libelle="Observation" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etats-financiers.index" />
                    <th>Verrouillage</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($etatsFinanciers as $item)
                    <tr>
                        @if ($peutSupprimerEtatsFinanciers)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-etat-financier"
                                       name="etats_financiers[]" value="{{ $item->id }}" form="formSelectionEtatsFinanciers">
                            </td>
                        @endif
                        <td class="fw-semibold">{{ $item->annee }}</td>
                        <td>
                            @if ($item->estRempli())
                                <span class="badge text-bg-secondary"><i class="bi bi-file-earmark-check"></i> Déposé</span>
                            @else
                                <span class="text-muted small">Aucun fichier</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ \Illuminate\Support\Str::limit($item->observation, 60) }}</td>
                        <td>
                            @if (! $corbeille)
                                @include('gestion.partials._verrou_document', ['document' => $item->document])
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.etats-financiers.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('etats_financiers', 'edit'))
                                        <a href="{{ route('gestion.etats-financiers.edit', $item->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.etats-financiers.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre cet état financier à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.etats-financiers.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if ($peutSupprimerDefinitivement)
                                        <form method="POST" action="{{ route('gestion.etats-financiers.force-destroy', $item->id) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerEtatsFinanciers ? 6 : 5 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-bar-chart-line fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun état financier pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($etatsFinanciers->hasPages())
        <div class="card-footer bg-transparent">
            {{ $etatsFinanciers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerEtatsFinanciers)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-etat-financier');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousEtatsFinanciers');
                const barre = document.getElementById('barreActionsGroupeesEtatsFinanciers');
                const compteur = document.getElementById('compteurSelectionEtatsFinanciers');

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
