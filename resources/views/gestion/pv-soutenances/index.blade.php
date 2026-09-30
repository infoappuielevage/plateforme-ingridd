@extends('layouts.gestion')

@section('title', 'PV de soutenance')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
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
        @else
            @if (\App\Support\ResourceAccess::can('pv_soutenances', 'delete'))
                <a href="{{ route('gestion.pv-soutenances.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('pv_soutenances', 'create'))
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

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.pv-soutenances.index') }}" class="row g-2 align-items-end" id="formFiltresPvSoutenances">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif
        <div class="col-md-2">
            <label class="form-label small">Niveau</label>
            <select name="niveau" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($niveaux as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('niveau') === $valeur)>{{ $libelle }}</option>
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
            <label class="form-label small">Recherche (nom, prénoms, n° PV)</label>
            <input type="text" name="recherche" id="rechercheFiltrePvSoutenances" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise tous les filtres d'un coup — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['niveau', 'filiere_id', 'diplome_retrait', 'recherche']))
                <a href="{{ route('gestion.pv-soutenances.index', $corbeille ? ['corbeille' => 1] : []) }}"
                   class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    // Les filtres à liste déroulante (Niveau, Filière, Diplôme) filtrent
    // immédiatement au choix, sans attendre un clic sur "Filtrer" — demande
    // explicite du client.
    document.querySelectorAll('#formFiltresPvSoutenances .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresPvSoutenances').submit();
        });
    });

    // La recherche texte filtre elle aussi automatiquement, mais avec un
    // court délai après la dernière frappe (600 ms) plutôt qu'à chaque
    // caractère : sinon chaque frappe déclencherait un aller-retour serveur.
    // Comme le rechargement de page fait perdre le focus du champ, on le lui
    // redonne ensuite (curseur replacé à la fin) pour pouvoir continuer à
    // taper sans recliquer dedans.
    var champRecherchePvSoutenances = document.getElementById('rechercheFiltrePvSoutenances');
    if (champRecherchePvSoutenances) {
        var delaiRecherchePvSoutenances;
        champRecherchePvSoutenances.addEventListener('input', function () {
            clearTimeout(delaiRecherchePvSoutenances);
            delaiRecherchePvSoutenances = setTimeout(function () {
                document.getElementById('formFiltresPvSoutenances').submit();
            }, 600);
        });
        if (champRecherchePvSoutenances.value) {
            champRecherchePvSoutenances.focus();
            var fin = champRecherchePvSoutenances.value.length;
            champRecherchePvSoutenances.setSelectionRange(fin, fin);
        }
    }
</script>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>N° PV</th>
                    <th>Étudiant</th>
                    <th>Niveau</th>
                    <th>Filière</th>
                    <th>Date de soutenance</th>
                    <th>Décision</th>
                    <th>Diplôme</th>
                    <th>Documents déposés</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pvSoutenances as $item)
                    <tr>
                        <td>{{ $item->numero_pv }}</td>
                        <td>{{ $item->nomComplet() }}</td>
                        <td>{{ $item->niveauLibelle() }}</td>
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
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
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
                        <td colspan="9">
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
