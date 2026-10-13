@extends('layouts.gestion')

@section('title', 'PV de soutenance')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">PV de soutenance</h1>
        <p class="text-muted small mb-0">
            Un enregistrement par étudiant{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.pv-soutenances.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            @if ($corbeillePeutEtreVidee && \App\Support\ResourceAccess::can('pv_soutenances', 'delete'))
                <form method="POST" action="{{ route('gestion.pv-soutenances.corbeille.vider') }}" class="d-inline"
                      onsubmit="return confirm('Tous les PV actuellement dans la corbeille seront supprimés définitivement. Cette action est irréversible. Continuer ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3-fill"></i> Vider la corbeille
                    </button>
                </form>
            @endif
        @else
            @if (\App\Support\ResourceAccess::can('pv_soutenances', 'delete'))
                <a href="{{ route('gestion.pv-soutenances.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            <a href="{{ route('gestion.pv-soutenances.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('pv_soutenances', 'create'))
                <a href="{{ route('gestion.pv-soutenances.import.formulaire') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importer
                </a>
                <a href="{{ route('gestion.pv-soutenances.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau PV
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

{{-- Onglets par niveau — remplace l'ancien filtre "Niveau" à liste
     déroulante, demande explicite du client ("enlever le filtre Niveau et
     faire plutôt trois tableau, BTS, LICENCE, MASTER", DTS et Doctorat
     ajoutés ensuite). Même principe que resources/views/gestion/documents/index.blade.php
     (onglets "famille de documents") : un lien GET par onglet, page
     réinitialisée au changement d'onglet (request()->except('page')),
     "Tous" inclus pour ne jamais cacher un niveau qu'on ne pense pas à
     filtrer explicitement.

     Pastilles pleines (classe nav-tabs-niveaux, voir public/css/style.css) —
     deuxième itération, sur retour du client ("l'affichage des niveaux
     n'est pas joli, change la couleur du texte et le fond, et rendre
     gras") : chaque onglet reprend la couleur de son badge niveau (classe
     "niveau-tab-<clé>", même teinte que la classe "bg-niveau-<clé>" des
     badges), avec un remplissage plein sur l'onglet actif. --}}
@unless ($corbeille)
    <ul class="nav nav-tabs-niveaux mb-3">
        @foreach ($onglets as $cle => $libelle)
            <li class="nav-item">
                {{-- $cle est déjà la valeur de niveau elle-même (clé de PvSoutenance::NIVEAUX) pour tout onglet autre que "tous" — voir App\Models\PvSoutenance::niveauBadgeClasse(). --}}
                <a class="nav-link niveau-tab-{{ $cle }} {{ $niveauActif === $cle ? 'active' : '' }}"
                   href="{{ route('gestion.pv-soutenances.index', array_merge(request()->except('page'), $cle === 'tous' ? ['niveau' => null] : ['niveau' => $cle])) }}">
                    {{ $libelle }}
                </a>
            </li>
        @endforeach
    </ul>
@endunless

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.pv-soutenances.index') }}" class="row g-2 align-items-end" id="formFiltresPvSoutenances">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        {{-- Le niveau actif (onglet) est de la navigation, pas un filtre à
             saisir ici : on le reporte tel quel pour que les autres filtres
             restent dans le même onglet. --}}
        @if ($niveauActif !== 'tous')
            <input type="hidden" name="niveau" value="{{ $niveauActif }}">
        @endif
        <div class="col-md-2">
            <label class="form-label small">N° PV</label>
            <input type="text" name="numero_pv" id="numeroPvFiltrePvSoutenances" class="form-control" value="{{ request('numero_pv') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Filière</label>
            <select name="filiere_id" class="form-select filtre-auto">
                <option value="">Toutes</option>
                @foreach ($filieres as $filiere)
                    <option value="{{ $filiere->id }}" @selected(request('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                @endforeach
            </select>
        </div>
        {{-- Remplace le filtre de l'ancien écran Documents par type (voir
             PvSoutenanceController::index()) : retrouver en un clic les
             diplômes encore à remettre, ou déjà remis. --}}
        <div class="col-md-2">
            <label class="form-label small">Diplôme</label>
            <select name="diplome_retrait" class="form-select filtre-auto">
                <option value="">Tous</option>
                <option value="retire" @selected(request('diplome_retrait') === 'retire')>Retiré</option>
                <option value="non_retire" @selected(request('diplome_retrait') === 'non_retire')>Non retiré</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Recherche (nom, prénoms)</label>
            <input type="text" name="recherche" id="rechercheFiltrePvSoutenances" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise les filtres (pas l'onglet de niveau) — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['filiere_id', 'diplome_retrait', 'recherche', 'numero_pv']))
                <a href="{{ route('gestion.pv-soutenances.index', array_filter(['corbeille' => $corbeille ? 1 : null, 'niveau' => $niveauActif !== 'tous' ? $niveauActif : null])) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    // Les filtres à liste déroulante (Filière, Diplôme) filtrent
    // immédiatement au choix, sans attendre un clic sur "Filtrer" — demande
    // explicite du client.
    document.querySelectorAll('#formFiltresPvSoutenances .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresPvSoutenances').submit();
        });
    });

    // Les champs texte (Recherche, N° PV) filtrent eux aussi automatiquement,
    // mais avec un court délai après la dernière frappe (600 ms) plutôt qu'à
    // chaque caractère : sinon chaque frappe déclencherait un aller-retour
    // serveur. Comme le rechargement de page fait perdre le focus, on le
    // redonne ensuite au champ qui a déclenché le dernier envoi (et lui
    // seul — il y a maintenant deux champs texte auto-filtrants, mémorisé
    // via sessionStorage plutôt qu'en se basant sur "le champ qui a une
    // valeur", ambigu si les deux en ont une).
    function autoFiltrerChampTexte(idChamp) {
        var champ = document.getElementById(idChamp);
        if (!champ) return;

        var delai;
        champ.addEventListener('input', function () {
            try { sessionStorage.setItem('pvSoutenancesChampActif', idChamp); } catch (e) {}
            clearTimeout(delai);
            delai = setTimeout(function () {
                document.getElementById('formFiltresPvSoutenances').submit();
            }, 600);
        });
    }

    autoFiltrerChampTexte('numeroPvFiltrePvSoutenances');
    autoFiltrerChampTexte('rechercheFiltrePvSoutenances');

    try {
        var idDernierChampActif = sessionStorage.getItem('pvSoutenancesChampActif');
        var dernierChampActif = idDernierChampActif ? document.getElementById(idDernierChampActif) : null;
        if (dernierChampActif && dernierChampActif.value) {
            dernierChampActif.focus();
            var fin = dernierChampActif.value.length;
            dernierChampActif.setSelectionRange(fin, fin);
        }
    } catch (e) {}
</script>

@php $peutSupprimerPvSoutenances = \App\Support\ResourceAccess::can('pv_soutenances', 'delete'); @endphp

@if ($peutSupprimerPvSoutenances)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que
         resources/views/gestion/documents/index.blade.php. --}}
    <form id="formSelectionPvSoutenances" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesPvSoutenances" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionPvSoutenances">0</strong> PV sélectionné(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                <button type="submit" form="formSelectionPvSoutenances" formaction="{{ route('gestion.pv-soutenances.corbeille-groupee') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Mettre les PV sélectionnés à la corbeille ?');">
                    <i class="bi bi-trash3"></i> Mettre à la corbeille
                </button>
            @else
                <button type="submit" form="formSelectionPvSoutenances" formaction="{{ route('gestion.pv-soutenances.corbeille.restaurer-groupe') }}"
                        class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                </button>
                <button type="submit" form="formSelectionPvSoutenances" formaction="{{ route('gestion.pv-soutenances.corbeille.force-destroy-groupe') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Supprimer définitivement les PV sélectionnés ? Cette action est irréversible.');">
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
                    @if ($peutSupprimerPvSoutenances)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousPvSoutenances" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="numero_pv" libelle="N° PV" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="nom" libelle="Étudiant" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="niveau" libelle="Niveau" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="filiere" libelle="Filière" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="date_soutenance" libelle="Date de soutenance" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="decision_jury" libelle="Décision" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="diplome_date_retrait" libelle="Diplôme" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <x-th-triable cle="documents_deposes" libelle="Documents déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.pv-soutenances.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pvSoutenances as $item)
                    <tr>
                        @if ($peutSupprimerPvSoutenances)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-pv-soutenance"
                                       name="pvSoutenances[]" value="{{ $item->id }}" form="formSelectionPvSoutenances">
                            </td>
                        @endif
                        <td>{{ $item->numero_pv }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($item->photo)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->photo) }}" alt=""
                                         class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                                @else
                                    <span class="rounded-circle bg-secondary-subtle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                                        <i class="bi bi-person-fill text-secondary small"></i>
                                    </span>
                                @endif
                                {{ $item->nomComplet() }}
                            </div>
                        </td>
                        <td><span class="badge {{ $item->niveauBadgeClasse() }}">{{ $item->niveauLibelle() }}</span></td>
                        <td>{{ $item->filiere->libelle }}</td>
                        <td>{{ $item->date_soutenance?->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge {{ $item->decision_jury === \App\Models\PvSoutenance::DECISION_ADMIS ? 'text-bg-success' : 'text-bg-danger' }}">
                                {{ $item->decisionLibelle() }}
                            </span>
                        </td>
                        <td>
                            @php $caseDiplome = $item->fichiers->firstWhere('role', \App\Models\PvSoutenanceFichier::ROLE_DIPLOME); @endphp
                            @if (! $caseDiplome?->document_id)
                                <span class="text-muted small">Non déposé</span>
                            @elseif ($item->estDiplomeRetire())
                                <span class="badge text-bg-success" title="{{ $item->diplomeRetirantLibelle() }} — {{ $item->diplome_date_retrait?->format('d/m/Y') }}">
                                    Retiré
                                </span>
                            @else
                                <span class="badge text-bg-warning">Non retiré</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $totalFichiersItem = $item->fichiers->count();
                                $deposesItem = $item->fichiers->whereNotNull('document_id')->count();
                                // Repère visuel rapide — demande explicite du client ("il n'y a pas
                                // de point de repère, tout se ressemble") : vert = tout déposé,
                                // orange = en cours, gris = rien déposé / aucune case.
                                $classeDepotItem = $totalFichiersItem === 0 || $deposesItem === 0
                                    ? 'text-bg-secondary'
                                    : ($deposesItem === $totalFichiersItem ? 'text-bg-success' : 'text-bg-warning');
                            @endphp
                            <span class="badge {{ $classeDepotItem }}">{{ $deposesItem }} / {{ $totalFichiersItem }}</span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.pv-soutenances.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('pv_soutenances', 'edit'))
                                        <a href="{{ route('gestion.pv-soutenances.edit', $item->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can('pv_soutenances', 'delete'))
                                        <form method="POST" action="{{ route('gestion.pv-soutenances.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce PV de soutenance à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('pv_soutenances', 'delete'))
                                        <form method="POST" action="{{ route('gestion.pv-soutenances.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.pv-soutenances.force-destroy', $item->id) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerPvSoutenances ? 10 : 9 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-mortarboard fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun PV de soutenance pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pvSoutenances->hasPages())
        <div class="card-footer bg-transparent">
            {{ $pvSoutenances->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerPvSoutenances)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-pv-soutenance');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousPvSoutenances');
                const barre = document.getElementById('barreActionsGroupeesPvSoutenances');
                const compteur = document.getElementById('compteurSelectionPvSoutenances');

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
