@extends('layouts.gestion')

@section('title', 'Scolarité '.$recetteScolarite->anneeAcademique?->libelle)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Scolarité — {{ $recetteScolarite->anneeAcademique?->libelle }}</h1>
    </div>
    <a href="{{ route('gestion.recettes.scolarite.index') }}" class="btn btn-outline-secondary">
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
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-wallet2"></i> Relevé Orange Money</div>
    <div class="card-body">
        @include('gestion.recettes.scolarite._case-fixe', ['fichier' => $orangeMoney, 'recetteScolarite' => $recetteScolarite, 'role' => 'orange_money', 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'tailleMaxMo' => $tailleMaxMo])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-accent d-flex align-items-center gap-2"><i class="bi bi-wallet2"></i> Relevé Moov Money</div>
    <div class="card-body">
        @include('gestion.recettes.scolarite._case-fixe', ['fichier' => $moovMoney, 'recetteScolarite' => $recetteScolarite, 'role' => 'moov_money', 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'tailleMaxMo' => $tailleMaxMo])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-wallet2"></i> Relevé Telecel Money</div>
    <div class="card-body">
        @include('gestion.recettes.scolarite._case-fixe', ['fichier' => $telecelMoney, 'recetteScolarite' => $recetteScolarite, 'role' => 'telecel_money', 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'tailleMaxMo' => $tailleMaxMo])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-success d-flex align-items-center gap-2"><i class="bi bi-receipt"></i> Bordereaux de versement</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bordereaux as $fichier)
                    <tr>
                        <td>
                            @include('gestion.recettes.scolarite._document-liste', ['fichier' => $fichier, 'recetteScolarite' => $recetteScolarite, 'role' => 'bordereau', 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted small py-3">Aucun bordereau pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('gestion.recettes.scolarite.liste.store', ['recetteScolarite' => $recetteScolarite->id, 'role' => 'bordereau']) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="fichier" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                    <i class="bi bi-upload"></i> Ajouter un bordereau
                </button>
            </form>
            <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo. Numéroté automatiquement (Bordereau 1, Bordereau 2...).</div>
        </div>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-paperclip"></i> Autres documents</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($autres as $fichier)
                    <tr>
                        <td>
                            @include('gestion.recettes.scolarite._document-liste', ['fichier' => $fichier, 'recetteScolarite' => $recetteScolarite, 'role' => 'autre', 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted small py-3">Aucun autre document pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('gestion.recettes.scolarite.liste.store', ['recetteScolarite' => $recetteScolarite->id, 'role' => 'autre']) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small">Libellé (optionnel)</label>
                    <input type="text" name="libelle" class="form-control form-control-sm" maxlength="150" placeholder="Ex. Reçu complémentaire">
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
