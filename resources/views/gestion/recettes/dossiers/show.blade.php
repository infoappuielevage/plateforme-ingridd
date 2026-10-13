@extends('layouts.gestion')

@section('title', $typeLibelle.' '.$dossier->anneeAcademique?->libelle)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $typeLibelle }} — {{ $dossier->anneeAcademique?->libelle }}</h1>
    </div>
    <a href="{{ route('gestion.recettes.dossiers.index', ['type' => $typeUrl]) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

@if (session('succes'))
    <div class="alert alert-success">{{ session('succes') }}</div>
@endif

@php
    $peutModifier = \App\Support\ResourceAccess::can('recettes', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('recettes', 'delete');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-file-earmark-spreadsheet"></i> Fichiers</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($fichiers as $fichier)
                    <tr>
                        <td>
                            @include('gestion.recettes.dossiers._fichier', ['fichier' => $fichier, 'typeUrl' => $typeUrl, 'dossier' => $dossier, 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted small py-3">Aucun fichier pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('gestion.recettes.dossiers.fichiers.store', ['type' => $typeUrl, 'dossier' => $dossier->id]) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small">Libellé (optionnel)</label>
                    <input type="text" name="libelle" class="form-control form-control-sm" maxlength="150" placeholder="Ex. Liste des étudiants ayant payé">
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Fichier (Excel, PDF...)</label>
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
