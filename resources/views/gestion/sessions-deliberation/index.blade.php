@extends('layouts.gestion')

@section('title', 'PV de délibération')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">PV de délibération</h1>
        <p class="text-muted small mb-0">
            Sessions{{ $corbeille ? ' — corbeille' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.sessions-deliberation.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'delete'))
                <a href="{{ route('gestion.sessions-deliberation.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash3"></i> Corbeille
                </a>
            @endif
            @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'create'))
                <a href="{{ route('gestion.sessions-deliberation.create') }}" class="btn btn-primary">
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

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.sessions-deliberation.index') }}" class="row g-2 align-items-end" id="formFiltresSessionsDeliberation">
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
            <label class="form-label small">Cycle</label>
            <select name="cycle" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($cycles as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('cycle') === $valeur)>{{ $libelle }}</option>
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
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            {{-- Réinitialise tous les filtres d'un coup — n'apparaît que si au moins un filtre est actif. --}}
            @if (request()->hasAny(['annee_academique_id', 'cycle', 'filiere_id']))
                <a href="{{ route('gestion.sessions-deliberation.index', $corbeille ? ['corbeille' => 1] : []) }}"
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
    document.querySelectorAll('#formFiltresSessionsDeliberation .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresSessionsDeliberation').submit();
        });
    });
</script>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Année académique</th>
                    <th>Session</th>
                    <th>Cycle</th>
                    <th>Filière</th>
                    <th>Fichiers déposés</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $item)
                    <tr>
                        <td>{{ $item->anneeAcademique->libelle }}</td>
                        <td>{{ $item->sessionLibelle() }}</td>
                        <td>{{ $item->cycleLibelle() }}</td>
                        <td>{{ $item->filiere->libelle }}</td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('gestion.sessions-deliberation.show', $item->id) }}" class="btn btn-outline-secondary" title="Ouvrir">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                    @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'delete'))
                                        <form method="POST" action="{{ route('gestion.sessions-deliberation.destroy', $item->id) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre cette session de délibération à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'delete'))
                                        <form method="POST" action="{{ route('gestion.sessions-deliberation.restore', $item->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('gestion.sessions-deliberation.force-destroy', $item->id) }}" class="d-inline"
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
                                <i class="bi bi-clipboard-check fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucune session de délibération pour l\'instant.' }}
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
