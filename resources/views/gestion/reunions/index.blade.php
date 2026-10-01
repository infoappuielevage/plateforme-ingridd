@extends('layouts.gestion')

@section('title', 'PV de réunion')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
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

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.reunions.index') }}" class="row g-2 align-items-end" id="formFiltresReunions">
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
        <div class="col-md-3">
            <label class="form-label small">Type de réunion</label>
            <select name="sous_type_document_id" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($typesReunion as $type)
                    <option value="{{ $type->id }}" @selected(request('sous_type_document_id') == $type->id)>{{ $type->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Recherche (description)</label>
            <input type="text" name="recherche" id="rechercheFiltreReunions" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise tous les filtres d'un coup — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['annee_academique_id', 'sous_type_document_id', 'recherche']))
                <a href="{{ route('gestion.reunions.index', $corbeille ? ['corbeille' => 1] : []) }}"
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

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Année académique</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Fichiers déposés</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reunions as $item)
                    <tr>
                        <td>{{ $item->date_reunion?->format('d/m/Y') }}</td>
                        <td>{{ $item->anneeAcademique->libelle }}</td>
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
                        <td colspan="6">
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
