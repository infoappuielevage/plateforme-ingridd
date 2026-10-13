@extends('layouts.gestion')

@section('title', 'Dossier étudiant')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Dossier étudiant</h1>
        <p class="text-muted small mb-0">
            Un enregistrement par étudiant{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.etudiants.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('etudiants', 'delete'))
                <a href="{{ route('gestion.etudiants.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            <a href="{{ route('gestion.etudiants.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
            @if (\App\Support\ResourceAccess::can('etudiants', 'create'))
                <a href="{{ route('gestion.etudiants.import.formulaire') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importer
                </a>
                <a href="{{ route('gestion.etudiants.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau dossier
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
    <form method="GET" action="{{ route('gestion.etudiants.index') }}" class="row g-2 align-items-end" id="formFiltresEtudiants">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        <div class="col-md-3">
            <label class="form-label small">Année académique</label>
            <select name="annee_academique_id" class="form-select filtre-auto">
                <option value="">Toutes</option>
                @foreach ($anneesAcademiques as $annee)
                    <option value="{{ $annee->id }}" @selected(request('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Niveau</label>
            <select name="niveau_id" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected(request('niveau_id') == $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
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
        <div class="col-md-4">
            <label class="form-label small">Recherche (matricule, nom, prénoms)</label>
            <input type="text" name="recherche" id="rechercheFiltreEtudiants" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise tous les filtres d'un coup — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['annee_academique_id', 'niveau_id', 'filiere_id', 'recherche']))
                <a href="{{ route('gestion.etudiants.index', $corbeille ? ['corbeille' => 1] : []) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    // Les filtres à liste déroulante (Année académique, Niveau, Filière)
    // filtrent immédiatement au choix, sans attendre un clic sur "Filtrer" —
    // même comportement que les autres écrans dédiés.
    document.querySelectorAll('#formFiltresEtudiants .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresEtudiants').submit();
        });
    });

    // La recherche texte filtre elle aussi automatiquement, mais avec un
    // court délai après la dernière frappe (600 ms) plutôt qu'à chaque
    // caractère : sinon chaque frappe déclencherait un aller-retour serveur.
    var champRechercheEtudiants = document.getElementById('rechercheFiltreEtudiants');
    if (champRechercheEtudiants) {
        var delaiRechercheEtudiants;
        champRechercheEtudiants.addEventListener('input', function () {
            clearTimeout(delaiRechercheEtudiants);
            delaiRechercheEtudiants = setTimeout(function () {
                document.getElementById('formFiltresEtudiants').submit();
            }, 600);
        });
        if (champRechercheEtudiants.value) {
            champRechercheEtudiants.focus();
            var fin = champRechercheEtudiants.value.length;
            champRechercheEtudiants.setSelectionRange(fin, fin);
        }
    }
</script>

@php $peutSupprimerEtudiants = \App\Support\ResourceAccess::can('etudiants', 'delete'); @endphp

@if ($peutSupprimerEtudiants)
    {{-- Formulaire "porteur" de la sélection groupée — même principe que
         resources/views/gestion/documents/index.blade.php : les cases à
         cocher et les boutons de la barre ci-dessous s'y rattachent via
         l'attribut HTML `form="..."` plutôt que d'être imbriqués dedans. --}}
    <form id="formSelectionEtudiants" method="POST" class="d-none">
        @csrf
    </form>

    <div id="barreActionsGroupeesEtudiants" class="alert alert-secondary d-none align-items-center justify-content-between gap-2 mb-3">
        <span><strong id="compteurSelectionEtudiants">0</strong> dossier(s) sélectionné(s)</span>
        <div class="d-flex gap-2">
            @if (! $corbeille)
                <button type="submit" form="formSelectionEtudiants" formaction="{{ route('gestion.etudiants.corbeille-groupee') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Mettre les dossiers sélectionnés à la corbeille ?');">
                    <i class="bi bi-trash3"></i> Mettre à la corbeille
                </button>
            @else
                <button type="submit" form="formSelectionEtudiants" formaction="{{ route('gestion.etudiants.corbeille.restaurer-groupe') }}"
                        class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer la sélection
                </button>
                <button type="submit" form="formSelectionEtudiants" formaction="{{ route('gestion.etudiants.corbeille.force-destroy-groupe') }}"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Supprimer définitivement les dossiers sélectionnés ? Cette action est irréversible.');">
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
                    @if ($peutSupprimerEtudiants)
                        <th style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="selectionnerTousEtudiants" title="Tout sélectionner">
                        </th>
                    @endif
                    <x-th-triable cle="matricule" libelle="Matricule" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etudiants.index" />
                    <x-th-triable cle="nom" libelle="Nom et prénoms" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etudiants.index" />
                    <th>Niveau actuel</th>
                    <th>Filière actuelle</th>
                    <th>Année académique</th>
                    <x-th-triable cle="pieces_deposees" libelle="Pièces déposées" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.etudiants.index" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($etudiants as $item)
                    @php $inscription = $item->inscriptionActuelle(); @endphp
                    <tr>
                        @if ($peutSupprimerEtudiants)
                            <td>
                                <input type="checkbox" class="form-check-input case-selection-etudiant"
                                       name="etudiants[]" value="{{ $item->id }}" form="formSelectionEtudiants">
                            </td>
                        @endif
                        <td>{{ $item->matricule }}</td>
                        <td>{{ $item->nomComplet() }}</td>
                        <td>{{ $inscription?->niveau?->libelle ?? '—' }}</td>
                        <td>{{ $inscription?->filiere?->libelle ?? '—' }}</td>
                        <td>{{ $inscription?->anneeAcademique?->libelle ?? '—' }}</td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.etudiants.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('etudiants', 'edit'))
                                        <a href="{{ route('gestion.etudiants.edit', $item->id) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\ResourceAccess::can('etudiants', 'delete'))
                                        <form method="POST" action="{{ route('gestion.etudiants.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce dossier étudiant à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('etudiants', 'delete'))
                                        <form method="POST" action="{{ route('gestion.etudiants.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.etudiants.force-destroy', $item->id) }}" class="d-inline"
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
                        <td colspan="{{ $peutSupprimerEtudiants ? 8 : 7 }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-person-vcard fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun dossier étudiant pour l\'instant.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($etudiants->hasPages())
        <div class="card-footer bg-transparent">
            {{ $etudiants->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@if ($peutSupprimerEtudiants)
    @section('scripts')
        <script>
            (function () {
                const casesLigne = document.querySelectorAll('.case-selection-etudiant');
                if (! casesLigne.length) return;

                const caseTout = document.getElementById('selectionnerTousEtudiants');
                const barre = document.getElementById('barreActionsGroupeesEtudiants');
                const compteur = document.getElementById('compteurSelectionEtudiants');

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
