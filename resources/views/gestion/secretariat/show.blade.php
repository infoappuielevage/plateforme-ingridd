@extends('layouts.gestion')

@section('title', $sousTypeDocument ? "{$type->libelle} — {$sousTypeDocument->libelle}" : $type->libelle)

@section('content')
@php
    $titre = $sousTypeDocument ? "{$type->libelle} — {$sousTypeDocument->libelle}" : $type->libelle;
    $aDesSousTypes = $sousTypesDisponibles->isNotEmpty();
    // Nombre de colonnes du tableau ci-dessous, pour que la ligne "aucun
    // résultat" (colspan) couvre bien toute la largeur quel que soit le jeu
    // de colonnes affiché (dépend des champs activés sur ce type).
    $nombreColonnes = ($aDesSousTypes && ! $sousTypeDocument ? 1 : 0)
        + ($type->champs_diplome_actifs ? 5 : ($type->champs_soutenance_actifs ? 4 : 3))
        + 3;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $titre }}</h1>
        <p class="text-muted small mb-0">
            Espace Secrétariat — documents classés sous « {{ $type->libelle }} »{{ $corbeille ? ' (corbeille)' : '' }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($corbeille)
            <a href="{{ route('gestion.secretariat', ['code' => $type->code, 'sousType' => $sousTypeDocument?->id]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        @else
            <a href="{{ route('gestion.secretariat', ['code' => $type->code, 'sousType' => $sousTypeDocument?->id, 'corbeille' => 1]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-trash3"></i> Corbeille
            </a>
            @if (\App\Support\ResourceAccess::can('documents', 'create'))
                <a href="{{ route('gestion.documents.create', ['type_document_id' => $type->id]) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Ajouter un document
                </a>
            @endif
        @endif
    </div>
</div>

@if ($aDesSousTypes)
    <ul class="nav nav-pills mb-3 flex-wrap">
        <li class="nav-item">
            <a class="nav-link {{ ! $sousTypeDocument ? 'active' : '' }}"
               href="{{ route('gestion.secretariat', ['code' => $type->code, 'corbeille' => $corbeille ?: null]) }}">
                Tous
            </a>
        </li>
        @foreach ($sousTypesDisponibles as $sousType)
            <li class="nav-item">
                <a class="nav-link {{ $sousTypeDocument?->id === $sousType->id ? 'active' : '' }}"
                   href="{{ route('gestion.secretariat', ['code' => $type->code, 'sousType' => $sousType->id, 'corbeille' => $corbeille ?: null]) }}">
                    {{ $sousType->libelle }}
                </a>
            </li>
        @endforeach
    </ul>
@endif

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.secretariat', ['code' => $type->code, 'sousType' => $sousTypeDocument?->id]) }}" class="row g-2 align-items-end">
        @if ($corbeille)
            <input type="hidden" name="corbeille" value="1">
        @endif

        <div class="col-md-6">
            <label class="form-label small">Recherche</label>
            <input type="text" name="recherche" class="form-control" placeholder="Description, nom du fichier, titulaire..."
                   value="{{ request('recherche') }}">
        </div>

        @if ($type->champs_diplome_actifs)
            <div class="col-md-3">
                <label class="form-label small">Statut de retrait</label>
                <select name="statut_retrait" class="form-select">
                    <option value="">— Tous —</option>
                    <option value="non_retire" @selected(request('statut_retrait') === 'non_retire')>Non retiré</option>
                    <option value="retire" @selected(request('statut_retrait') === 'retire')>Retiré</option>
                </select>
            </div>
        @endif

        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary" title="Filtrer"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @if ($aDesSousTypes && ! $sousTypeDocument)
                        <th>Sous-type</th>
                    @endif

                    @if ($type->champs_diplome_actifs)
                        <th>Titulaire</th>
                        <th>Numéro</th>
                        <th>Délivrance</th>
                        <th>Mention</th>
                        <th>Retrait</th>
                    @elseif ($type->champs_soutenance_actifs)
                        <th>Titulaire</th>
                        <th>Thème du mémoire</th>
                        <th>Soutenance</th>
                        <th>Jury</th>
                    @else
                        <th>Filière</th>
                        <th>Niveau{{ $type->champ_semestre_actif ? ' / Semestre' : '' }}</th>
                        <th>Année académique</th>
                    @endif

                    <th>Fichier</th>
                    <th>Ajouté par</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        @if ($aDesSousTypes && ! $sousTypeDocument)
                            <td class="text-muted small">{{ $document->sousTypeDocument?->libelle ?? '—' }}</td>
                        @endif

                        @if ($type->champs_diplome_actifs)
                            <td>{{ $document->nom_titulaire ?? '—' }}</td>
                            <td class="text-muted small">{{ $document->numero_diplome ?? '—' }}</td>
                            <td class="text-muted small">{{ optional($document->date_delivrance)->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-muted small">{{ $document->mention ?? '—' }}</td>
                            <td>
                                @if ($document->statutRetraitLibelle())
                                    <span class="badge {{ $document->estRetire() ? 'text-bg-success' : 'text-bg-warning' }}">
                                        {{ $document->statutRetraitLibelle() }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        @elseif ($type->champs_soutenance_actifs)
                            <td>{{ $document->nom_titulaire ?? '—' }}</td>
                            <td class="text-truncate" style="max-width: 220px;" title="{{ $document->theme_memoire }}">{{ $document->theme_memoire ?? '—' }}</td>
                            <td class="text-muted small">{{ optional($document->date_soutenance)->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-truncate small text-muted" style="max-width: 200px;" title="{{ $document->membres_jury }}">{{ $document->membres_jury ?? '—' }}</td>
                        @else
                            <td class="text-muted small">{{ $document->filiere?->libelle ?? '—' }}</td>
                            <td class="text-muted small">
                                {{ $document->niveau?->libelle ?? '—' }}{{ $document->semestreLibelle() ? ' · '.$document->semestreLibelle() : '' }}
                            </td>
                            <td class="text-muted small">{{ $document->anneeAcademique?->libelle ?? '—' }}</td>
                        @endif

                        <td>
                            <div class="text-truncate" style="max-width: 200px;" title="{{ $document->nom_original }}">
                                <i class="bi bi-file-earmark-text me-1"></i>{{ $document->nom_original }}
                            </div>
                            <div class="text-muted small">{{ $document->tailleFormatee() }} · {{ $document->created_at?->format('d/m/Y') }}</div>
                        </td>
                        <td class="text-muted small">{{ $document->uploadePar?->name ?? '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if (! $corbeille)
                                    <a href="{{ route('documents.telecharger', $document) }}" class="btn btn-outline-secondary" title="Télécharger">
                                        <i class="bi bi-download"></i>
                                    </a>

                                    @if (\App\Support\ResourceAccess::can('documents', 'edit'))
                                        @if ($type->champs_diplome_actifs)
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
                        <td colspan="{{ $nombreColonnes }}">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    {{ $corbeille ? 'La corbeille est vide pour ce type.' : 'Aucun document ne correspond à ces critères.' }}
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
