@extends('layouts.gestion')

@section('title', 'PV de réunion')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">PV de réunion</h1>
        <p class="text-muted small mb-0">
            Un enregistrement par réunion{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.reunions.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('reunions', 'delete'))
                <a href="{{ route('gestion.reunions.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            <a href="{{ route('gestion.reunions.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('reunions', 'create'))
                <a href="{{ route('gestion.reunions.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouvelle réunion
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

{{-- Onglets par année académique — remplace l'ancien filtre "Année
     académique" à liste déroulante, demande explicite du client ("fait de
     même pour les PV de réunion pour les couleurs et pour le tableau fait
     par année académique"). Même principe que resources/views/gestion/pv-soutenances/index.blade.php
     (onglets par niveau), à une différence près : ce référentiel est OUVERT
     (une nouvelle année créée chaque année), donc chaque onglet reçoit sa
     couleur PAR ROTATION plutôt qu'une teinte dédiée — voir
     App\Models\AnneeAcademique::paletteIndex() et public/css/style.css
     (classes .palette-tab-N). L'onglet "Toutes" réutilise directement
     .niveau-tab-tous (déjà générique, pas spécifique au niveau).

     .nav-tabs-scroll (en plus de .nav-tabs-niveaux, pas à sa place) : sur
     retour du client ("si les années académiques s'accumulent ça sera
     vilain à voir sans scroller") — contrairement à Niveau/Cycle (nombre
     fixe), ce référentiel grandit chaque année, donc les onglets restent
     sur UNE SEULE ligne qui défile horizontalement plutôt que de passer à
     la ligne — voir public/css/style.css. --}}
@unless ($corbeille)
    <ul class="nav nav-tabs-niveaux nav-tabs-scroll mb-3">
        <li class="nav-item">
            <a class="nav-link niveau-tab-tous {{ $anneeActifId === null ? 'active' : '' }}"
               href="{{ route('gestion.reunions.index', array_merge(request()->except('page'), ['annee_academique_id' => null])) }}">
                Toutes
            </a>
        </li>
        @foreach ($anneesAcademiques as $annee)
            <li class="nav-item">
                <a class="nav-link palette-tab-{{ $annee->paletteIndex() }} {{ $anneeActifId === $annee->id ? 'active' : '' }}"
                   href="{{ route('gestion.reunions.index', array_merge(request()->except('page'), ['annee_academique_id' => $annee->id])) }}">
                    {{ $annee->libelle }}
                </a>
            </li>
        @endforeach
    </ul>
@endunless

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.reunions.index') }}" class="row g-2 align-items-end" id="formFiltresReunions">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        {{-- L'année académique active (onglet) est de la navigation, pas un
             filtre à saisir ici : on le reporte tel quel pour que les autres
             filtres restent dans le même onglet. --}}
        @if ($anneeActifId !== null)
            <input type="hidden" name="annee_academique_id" value="{{ $anneeActifId }}">
        @endif
        <div class="col-md-5">
            <label class="form-label small">Type de réunion</label>
            <select name="sous_type_document_id" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($typesReunion as $type)
                    <option value="{{ $type->id }}" @selected(request('sous_type_document_id') == $type->id)>{{ $type->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Recherche (description)</label>
            <input type="text" name="recherche" id="rechercheFiltreReunions" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise les filtres (pas l'onglet d'année académique) — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['sous_type_document_id', 'recherche']))
                <a href="{{ route('gestion.reunions.index', array_filter(['corbeille' => $corbeille ? 1 : null, 'annee_academique_id' => $anneeActifId])) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    // Les filtres à liste déroulante (Année académique, Type de réunion)
    // filtrent immédiatement au choix, sans attendre un clic sur "Filtrer" —
    // même comportement que pour les PV de soutenance (demande explicite du
    // client).
    document.querySelectorAll('#formFiltresReunions .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresReunions').submit();
        });
    });

    // La recherche texte filtre elle aussi automatiquement, mais avec un
    // court délai après la dernière frappe (600 ms) plutôt qu'à chaque
    // caractère : sinon chaque frappe déclencherait un aller-retour serveur.
    // Comme le rechargement de page fait perdre le focus du champ, on le lui
    // redonne ensuite (curseur replacé à la fin) pour pouvoir continuer à
    // taper sans recliquer dedans.
    var champRechercheReunions = document.getElementById('rechercheFiltreReunions');
    if (champRechercheReunions) {
        var delaiRechercheReunions;
        champRechercheReunions.addEventListener('input', function () {
            clearTimeout(delaiRechercheReunions);
            delaiRechercheReunions = setTimeout(function () {
                document.getElementById('formFiltresReunions').submit();
            }, 600);
        });
        if (champRechercheReunions.value) {
            champRechercheReunions.focus();
            var fin = champRechercheReunions.value.length;
            champRechercheReunions.setSelectionRange(fin, fin);
        }
    }
</script>

@php $peutSupprimerReunions = \App\Support\ResourceAccess::can('reunions', 'delete'); @endphp

@if ($peutSupprimerReunions)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que
         resources/views/gestion/documents/index.blade.php. --}}
    <form id="formSelectionReunions" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesReunions" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionReunions">0</strong> réunion(s) sélectionnée(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                <button type="submit" form="formSelectionReunions" formaction="{{ route('gestion.reunions.corbeille-groupee') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Mettre les réunions sélectionnées à la corbeille ?');">
                    <i class="bi bi-trash3"></i> Mettre à la corbeille
                </button>
            @else
                <button type="submit" form="formSelectionReunions" formaction="{{ route('gestion.reunions.corbeille.restaurer-groupe') }}"
                        class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                </button>
                <button type="submit" form="formSelectionReunions" formaction="{{ route('gestion.reunions.corbeille.force-destroy-groupe') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Supprimer définitivement les réunions sélectionnées ? Cette action est irréversible.');">
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
                    @if ($peutSupprimerReunions)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousReunions" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="date_reunion" libelle="Date" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.reunions.index" />
                    <x-th-triable cle="annee_academique" libelle="Année académique" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.reunions.index" />
                    <x-th-triable cle="type" libelle="Type" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.reunions.index" />
                    <x-th-triable cle="description" libelle="Description" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.reunions.index" />
                    <x-th-triable cle="fichiers_deposes" libelle="Fichiers déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.reunions.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reunions as $item)
                    <tr>
                        @if ($peutSupprimerReunions)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-reunion"
                                       name="reunions[]" value="{{ $item->id }}" form="formSelectionReunions">
                            </td>
                        @endif
                        <td>{{ $item->date_reunion?->format('d/m/Y') }}</td>
                        <td><span class="badge bg-palette-{{ $item->anneeAcademique->paletteIndex() }}">{{ $item->anneeAcademique->libelle }}</span></td>
                        <td>{{ $item->typeLibelle() }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($item->description, 60) }}</td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.reunions.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('reunions', 'edit'))
                                        <a href="{{ route('gestion.reunions.edit', $item->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can('reunions', 'delete'))
                                        <form method="POST" action="{{ route('gestion.reunions.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre cette réunion à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('reunions', 'delete'))
                                        <form method="POST" action="{{ route('gestion.reunions.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.reunions.force-destroy', $item->id) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerReunions ? 7 : 6 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-people fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucune réunion pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reunions->hasPages())
        <div class="card-footer bg-transparent">
            {{ $reunions->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerReunions)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-reunion');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousReunions');
                const barre = document.getElementById('barreActionsGroupeesReunions');
                const compteur = document.getElementById('compteurSelectionReunions');

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
