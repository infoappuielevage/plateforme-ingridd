@extends('layouts.gestion')

@section('title', 'Tableau de bord')

@section('content')
<h1 class="h3">Tableau de bord {{ config('app.name') }}</h1>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4 stagger">
    @foreach ($cartes as $carte)
        <div class="col">
            <div class="card stat-card {{ $carte['accent'] ? 'accent' : '' }} h-100">
                <div class="card-body">
                    <span class="stat-icon"><i class="bi {{ $carte['icone'] }}"></i></span>
                    <div class="fs-3 fw-bold text-primary count-up mt-2">{{ $carte['valeur'] }}</div>
                    <div class="small text-muted">{{ $carte['label'] }}</div>
                    @if ($carte['description'])
                        <div class="small text-muted mt-1">{{ $carte['description'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    @if ($derniereSauvegarde)
        <div class="col">
            <div class="card stat-card {{ $derniereSauvegarde['alerte'] ? '' : 'accent' }} h-100">
                <div class="card-body">
                    <span class="stat-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                    <div class="fs-5 fw-bold {{ $derniereSauvegarde['alerte'] ? 'text-danger' : 'text-primary' }} mt-2">{{ $derniereSauvegarde['valeur'] }}</div>
                    <div class="small text-muted">Dernière sauvegarde</div>
                    <div class="small text-muted mt-1">{{ $derniereSauvegarde['description'] }}</div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="section-header">
    <span class="icon-chip accent"><i class="bi bi-clock-history"></i></span>
    <h2>Documents récemment ajoutés</h2>
</div>

@if ($documentsRecents->isEmpty())
    <div class="empty-state"><i class="bi bi-inbox"></i>Aucun document pour le moment.</div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle ingridd-surface mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Sous-type</th>
                    <th>Fichier</th>
                    <th>Ajouté par</th>
                    <th>Ajouté le</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documentsRecents as $document)
                    <tr>
                        <td>{{ $document->typeDocument?->libelle }}</td>
                        <td>{{ $document->sousTypeDocument?->libelle ?? '—' }}</td>
                        <td>{{ $document->nom_original }}</td>
                        <td>{{ $document->uploadePar?->name }}</td>
                        <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
