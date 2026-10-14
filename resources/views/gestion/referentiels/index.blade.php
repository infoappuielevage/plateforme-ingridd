@extends('layouts.gestion')

@section('title', $definition['pluriel'])

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $definition['pluriel'] }}</h1>
        <p class="text-muted small mb-0">
            Référentiel{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            @if ($corbeillePeutEtreVidee && \App\Support\ResourceAccess::can($definition['cle'], 'delete_definitif'))
                <form method="POST" action="{{ route('gestion.referentiels.corbeille.vider', ['referentiel' => $referentiel]) }}" class="d-inline"
                      onsubmit="return confirm('Tous les éléments actuellement dans la corbeille seront supprimés définitivement (ceux encore utilisés ailleurs seront ignorés). Cette action est irréversible. Continuer ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3-fill"></i> Vider la corbeille
                    </button>
                </form>
            @endif
        @else
            <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel, 'corbeille' => 1]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-trash3"></i> Corbeille
            </a>
            <a href="{{ route('gestion.referentiels.export', array_merge(['referentiel' => $referentiel], request()->query())) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can($definition['cle'], 'create'))
                <a href="{{ route('gestion.referentiels.import.formulaire', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importer
                </a>
                <a href="{{ route('gestion.referentiels.create', ['referentiel' => $referentiel]) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Ajouter
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

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="row g-2 align-items-end">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        <div class="col-md-8">
            <label class="form-label small">Recherche</label>
            <input type="text" name="recherche" class="form-control" placeholder="Code, libellé..." value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

@php
    // Deux droits désormais distincts (demande explicite du client) : voir
    // le commentaire équivalent dans champs-supplementaires/index.blade.php.
    $peutMettreCorbeille = \App\Support\ResourceAccess::can($definition['cle'], 'delete');
    $peutSupprimerDefinitivement = \App\Support\ResourceAccess::can($definition['cle'], 'delete_definitif');
    $peutSupprimerReferentiel = $peutMettreCorbeille || $peutSupprimerDefinitivement;
@endphp

@if ($peutSupprimerReferentiel)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que
         resources/views/gestion/documents/index.blade.php : les cases à
         cocher et les boutons de la barre ci-dessous s'y rattachent via
         l'attribut HTML `form="..."` plutôt que d'être imbriqués dedans. --}}
    <form id="formSelectionReferentiel" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesReferentiel" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionReferentiel">0</strong> élément(s) sélectionné(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                @if ($peutMettreCorbeille)
                    <button type="submit" form="formSelectionReferentiel" formaction="{{ route('gestion.referentiels.corbeille-groupee', ['referentiel' => $referentiel]) }}"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Mettre les éléments sélectionnés à la corbeille ?');">
                        <i class="bi bi-trash3"></i> Mettre à la corbeille
                    </button>
                @endif
            @else
                @if ($peutMettreCorbeille)
                    <button type="submit" form="formSelectionReferentiel" formaction="{{ route('gestion.referentiels.corbeille.restaurer-groupe', ['referentiel' => $referentiel]) }}"
                            class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                    </button>
                @endif
                @if ($peutSupprimerDefinitivement)
                    <button type="submit" form="formSelectionReferentiel" formaction="{{ route('gestion.referentiels.corbeille.force-destroy-groupe', ['referentiel' => $referentiel]) }}"
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
                    @if ($peutSupprimerReferentiel)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousReferentiel" title="Tout sélectionner">
                        </th>
                    @endif
                    @if ($definition['a_code'])
                        <x-th-triable cle="code" libelle="Code" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.referentiels.index" :parametres="['referentiel' => $referentiel]" />
                    @endif
                    <x-th-triable cle="libelle" libelle="Libellé" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.referentiels.index" :parametres="['referentiel' => $referentiel]" />
                    @if ($definition['a_code'])
                        <x-th-triable cle="ordre" libelle="Ordre" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.referentiels.index" :parametres="['referentiel' => $referentiel]" />
                    @endif
                    <x-th-triable cle="actif" libelle="Actif" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.referentiels.index" :parametres="['referentiel' => $referentiel]" />
                    <x-th-triable cle="documents_count" libelle="Documents" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.referentiels.index" :parametres="['referentiel' => $referentiel]" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        @if ($peutSupprimerReferentiel)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-referentiel"
                                       name="ids[]" value="{{ $item->id }}" form="formSelectionReferentiel">
                            </td>
                        @endif
                        @if ($definition['a_code'])
                            <td><code>{{ $item->code }}</code></td>
                        @endif
                        <td>{{ $item->libelle }}</td>
                        @if ($definition['a_code'])
                            <td class="text-muted small">{{ $item->ordre }}</td>
                        @endif
                        <td>
                            @if ($item->actif)
                                <span class="badge text-bg-success">Actif</span>
                            @else
                                <span class="badge text-bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $item->documents_count }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    @if (\App\Support\ResourceAccess::can($definition['cle'], 'edit'))
                                        <a href="{{ route('gestion.referentiels.edit', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.referentiels.destroy', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre {{ $definition['singulier'] }} à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($peutMettreCorbeille)
                                        <form method="POST" action="{{ route('gestion.referentiels.restore', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if ($peutSupprimerDefinitivement)
                                        <form method="POST" action="{{ route('gestion.referentiels.force-destroy', ['referentiel' => $referentiel, 'id' => $item->id]) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerReferentiel ? 7 : 6 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun élément.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-footer bg-transparent">
            {{ $items->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerReferentiel)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-referentiel');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousReferentiel');
                const barre = document.getElementById('barreActionsGroupeesReferentiel');
                const compteur = document.getElementById('compteurSelectionReferentiel');

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
