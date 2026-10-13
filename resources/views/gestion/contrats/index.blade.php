@extends('layouts.gestion')

@section('title', 'Gestion des contrats — '.$typeContrat->libelle)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Gestion des contrats</h1>
        <p class="text-muted small mb-0">
            {{ $typeContrat->libelle }}{{ $corbeille ? ' — corbeille' : '' }}
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('contrats', 'delete'))
                <a href="{{ route('gestion.contrats.index', ['typeContrat' => $typeContrat->code, 'corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            <a href="{{ route('gestion.contrats.export', array_merge(['typeContrat' => $typeContrat->code], request()->query())) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('contrats', 'create'))
                <a href="{{ route('gestion.contrats.create', ['typeContrat' => $typeContrat->code]) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau contrat
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

@unless ($corbeille)
    @include('gestion.contrats._onglets', ['categories' => $categories, 'typeContrat' => $typeContrat])
@endunless

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]) }}" class="row g-2 align-items-end" id="formFiltresContrats">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        <div class="col-md-5">
            <label class="form-label small">Statut</label>
            <select name="statut" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($statuts as $cle => $libelle)
                    <option value="{{ $cle }}" @selected(request('statut') == $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Recherche (partenaire/prestataire, objet)</label>
            <input type="text" name="recherche" id="rechercheFiltreContrats" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            @if (request()->hasAny(['statut', 'recherche']))
                <a href="{{ route('gestion.contrats.index', ['typeContrat' => $typeContrat->code, 'corbeille' => $corbeille ? 1 : null]) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('#formFiltresContrats .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresContrats').submit();
        });
    });

    var champRechercheContrats = document.getElementById('rechercheFiltreContrats');
    if (champRechercheContrats) {
        var delaiRechercheContrats;
        champRechercheContrats.addEventListener('input', function () {
            clearTimeout(delaiRechercheContrats);
            delaiRechercheContrats = setTimeout(function () {
                document.getElementById('formFiltresContrats').submit();
            }, 600);
        });
        if (champRechercheContrats.value) {
            champRechercheContrats.focus();
            var fin = champRechercheContrats.value.length;
            champRechercheContrats.setSelectionRange(fin, fin);
        }
    }
</script>

@php $peutSupprimerContrats = \App\Support\ResourceAccess::can('contrats', 'delete'); @endphp

@if ($peutSupprimerContrats)
    <form id="formSelectionContrats" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesContrats" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionContrats">0</strong> contrat(s) sélectionné(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                <button type="submit" form="formSelectionContrats" formaction="{{ route('gestion.contrats.corbeille-groupee', ['typeContrat' => $typeContrat->code]) }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Mettre les contrats sélectionnés à la corbeille ?');">
                    <i class="bi bi-trash3"></i> Mettre à la corbeille
                </button>
            @else
                <button type="submit" form="formSelectionContrats" formaction="{{ route('gestion.contrats.corbeille.restaurer-groupe', ['typeContrat' => $typeContrat->code]) }}"
                        class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                </button>
                <button type="submit" form="formSelectionContrats" formaction="{{ route('gestion.contrats.corbeille.force-destroy-groupe', ['typeContrat' => $typeContrat->code]) }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Supprimer définitivement les contrats sélectionnés ? Cette action est irréversible.');">
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
                    @if ($peutSupprimerContrats)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousContrats" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="partenaire_prestataire" libelle="Partenaire / Prestataire" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <x-th-triable cle="objet" libelle="Objet" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <x-th-triable cle="montant" libelle="Montant" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <x-th-triable cle="date_fin" libelle="Échéance" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <x-th-triable cle="statut" libelle="Statut" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <x-th-triable cle="fichiers_deposes" libelle="Fichiers déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.index" :parametres="['typeContrat' => $typeContrat->code]" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contrats as $item)
                    <tr>
                        @if ($peutSupprimerContrats)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-contrat"
                                       name="contrats[]" value="{{ $item->id }}" form="formSelectionContrats">
                            </td>
                        @endif
                        <td>{{ $item->partenaire_prestataire }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($item->objet, 60) }}</td>
                        <td class="text-muted small">{{ $item->montantFormate() ?? '—' }}</td>
                        <td class="text-muted small">
                            {{ $item->date_fin?->format('d/m/Y') ?? ($item->date_debut ? 'Indéterminée' : '—') }}
                            @if ($item->echeanceDepassee())
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Échéance dépassée — statut encore « Actif »"></i>
                            @endif
                        </td>
                        <td><span class="badge {{ $item->statutBadgeClass() }}">{{ $item->statutLibelle() }}</span></td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.contrats.show', ['typeContrat' => $typeContrat->code, 'contrat' => $item->id]) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('contrats', 'edit'))
                                        <a href="{{ route('gestion.contrats.edit', ['typeContrat' => $typeContrat->code, 'contrat' => $item->id]) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if ($peutSupprimerContrats)
                                        <form method="POST" action="{{ route('gestion.contrats.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $item->id]) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce contrat à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($peutSupprimerContrats)
                                        <form method="POST" action="{{ route('gestion.contrats.restore', ['typeContrat' => $typeContrat->code, 'contrat' => $item->id]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.contrats.force-destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $item->id]) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerContrats ? 8 : 7 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-vector-pen fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun contrat pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contrats->hasPages())
        <div class="card-footer bg-transparent">
            {{ $contrats->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerContrats)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-contrat');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousContrats');
                const barre = document.getElementById('barreActionsGroupeesContrats');
                const compteur = document.getElementById('compteurSelectionContrats');

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
