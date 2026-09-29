@extends('layouts.gestion')

@section('title', $corbeille ? 'Corbeille des documents' : 'Documents')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $corbeille ? 'Corbeille des documents' : 'Documents' }}</h1>
        <p class="text-muted small mb-0">
            {{ $corbeille ? 'Documents supprimés, restaurables ou à effacer définitivement.' : 'Tous les documents archivés.' }}
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.documents.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour aux documents
            </a>
        @else
            <a href="{{ route('gestion.documents.index', ['corbeille' => 1]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-trash3"></i> Corbeille
            </a>
            @if (\App\Support\ResourceAccess::can('documents', 'create'))
                <a href="{{ route('gestion.documents.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Ajouter un document
                </a>
            @endif
        @endif
    </div>
</div>

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.documents.index') }}" class="row g-2 align-items-end">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif

        <div class="col-md-3">
            <label class="form-label small">Recherche</label>
            <input type="text" name="recherche" class="form-control" placeholder="Description, nom du fichier, titulaire..."
                   value="{{ request('recherche') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label small">Type</label>
            <select name="type_document_id" class="form-select">
                <option value="">— Tous —</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected(request('type_document_id') == $type->id)>{{ $type->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small">Filière</label>
            <select name="filiere_id" class="form-select">
                <option value="">— Toutes —</option>
                @foreach ($filieres as $filiere)
                    <option value="{{ $filiere->id }}" @selected(request('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small">Niveau</label>
            <select name="niveau_id" class="form-select">
                <option value="">— Tous —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected(request('niveau_id') == $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small">Année académique</label>
            <select name="annee_academique_id" class="form-select">
                <option value="">— Toutes —</option>
                @foreach ($anneesAcademiques as $annee)
                    <option value="{{ $annee->id }}" @selected(request('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Filière / Niveau / Année</th>
                    <th>Fichier</th>
                    <th>Ajouté par</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $document->typeDocument?->libelle ?? '—' }}</div>
                            @if ($document->sousTypeDocument)
                                <div class="text-muted small">{{ $document->sousTypeDocument->libelle }}</div>
                            @endif
                            @if ($document->description)
                                <div class="text-muted small text-truncate" style="max-width: 260px;">{{ $document->description }}</div>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $document->filiere?->libelle ?? '—' }}<br>
                            {{ $document->niveau?->libelle ?? '—' }}{{ $document->semestreLibelle() ? ' · '.$document->semestreLibelle() : '' }}<br>
                            {{ $document->anneeAcademique?->libelle ?? '—' }}
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 220px;" title="{{ $document->nom_original }}">
                                <i class="bi bi-file-earmark-text me-1"></i>{{ $document->nom_original }}
                            </div>
                            <div class="text-muted small">{{ $document->tailleFormatee() }} · {{ $document->created_at?->format('d/m/Y') }}</div>
                        </td>
                        <td class="text-muted small">{{ $document->uploadePar?->name ?? '—' }}</td>
                        <td>
                            @if ($document->statutRetraitLibelle())
                                <span class="badge {{ $document->estRetire() ? 'text-bg-success' : 'text-bg-warning' }}">
                                    {{ $document->statutRetraitLibelle() }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('documents.telecharger', $document) }}" class="btn btn-outline-secondary" title="Télécharger">
                                        <i class="bi bi-download"></i>
                                    </a>

                                    @if (\App\Support\ResourceAccess::can('documents', 'edit'))
                                        @if ($document->statutRetraitLibelle())
                                            <form method="POST" action="{{ route('gestion.documents.basculer-retrait', $document) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary" title="{{ $document->estRetire() ? 'Annuler le retrait' : 'Marquer comme retiré' }}">
                                                    <i class="bi {{ $document->estRetire() ? 'bi-arrow-counterclockwise' : 'bi-check2-circle' }}"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('gestion.documents.edit', $document) }}" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif

                                    @if (\App\Support\ResourceAccess::can('documents', 'delete'))
                                        <form method="POST" action="{{ route('gestion.documents.destroy', $document) }}" class="d-inline"
                                              onsubmit="return confirm('Mettre ce document à la corbeille ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Mettre à la corbeille">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if (\App\Support\ResourceAccess::can('documents', 'delete'))
                                        <form method="POST" action="{{ route('gestion.documents.restore', $document->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('gestion.documents.force-destroy', $document->id) }}" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement ce document ? Cette action est irréversible.');">
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
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide.' : 'Aucun document ne correspond à ces critères.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($documents->hasPages())
        <div class="card-footer bg-transparent">
            {{ $documents->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
