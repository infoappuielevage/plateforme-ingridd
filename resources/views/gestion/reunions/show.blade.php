@extends('layouts.gestion')

@section('title', 'PV de réunion')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $reunion->typeLibelle() }} — {{ $reunion->date_reunion?->format('d/m/Y') }}</h1>
        <p class="text-muted small mb-0">
            <span class="badge bg-palette-{{ $reunion->anneeAcademique->paletteIndex() }}">{{ $reunion->anneeAcademique->libelle }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        @if (\App\Support\ResourceAccess::can('reunions', 'edit'))
            <a href="{{ route('gestion.reunions.edit', $reunion->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Modifier
            </a>
        @endif
        <a href="{{ route('gestion.reunions.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-card-text"></i> Description</div>
    <div class="card-body">
        <p class="mb-0">{{ $reunion->description ?: '—' }}</p>
    </div>
</div>

@php
    $peutModifier = \App\Support\ResourceAccess::can('reunions', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('reunions', 'delete');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-file-earmark-text"></i> Rapport de la réunion</div>
    <div class="card-body">
        @include('gestion.reunions._rapport', ['rapport' => $rapport, 'reunion' => $reunion, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'tailleMaxMo' => $tailleMaxMo])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-accent d-flex align-items-center gap-2"><i class="bi bi-paperclip"></i> Pièces jointes</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($piecesJointes as $fichier)
                    <tr>
                        <td>
                            @include('gestion.reunions._piece-jointe', ['fichier' => $fichier, 'reunion' => $reunion, 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted small py-3">Aucune pièce jointe pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('gestion.reunions.pieces-jointes.store', $reunion->id) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small">Libellé (optionnel)</label>
                    <input type="text" name="libelle" class="form-control form-control-sm" maxlength="150" placeholder="Ex. Liste de présence">
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Fichier</label>
                    <input type="file" name="fichier" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-upload"></i> Ajouter
                    </button>
                </div>
            </form>
            <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo.</div>
        </div>
    @endif
</div>
@endsection
