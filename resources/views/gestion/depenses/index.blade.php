@extends('layouts.gestion')

@section('title', 'Dépenses — '.$typeDepense->libelle)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Gestion des dépenses</h1>
    </div>
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

@include('gestion.depenses._onglets', ['categories' => $categories, 'typeDepense' => $typeDepense])

@php
    $peutModifier = \App\Support\ResourceAccess::can('depenses', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('depenses', 'delete');
@endphp

<div class="card shadow-sm">
    <div class="card-header header-primary d-flex align-items-center gap-2">
        <i class="bi bi-cash-stack"></i> {{ $typeDepense->libelle }}
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <x-th-triable cle="annee_academique" libelle="Année académique" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.depenses.index" :parametres="['typeDepense' => $typeDepense->code]" />
                    <th>Bordereau (PDF)</th>
                    <th>Fichier Excel</th>
                    <th>Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($depenses as $depense)
                    <tr>
                        <td class="fw-semibold">{{ $depense->anneeAcademique?->libelle }}</td>
                        <td>
                            @include('gestion.depenses._case', [
                                'document' => $depense->bordereauDocument,
                                'depense' => $depense,
                                'typeDepense' => $typeDepense,
                                'champ' => 'bordereau',
                                'peutModifier' => $peutModifier,
                                'peutSupprimer' => $peutSupprimer,
                                'tailleMaxMo' => $tailleMaxMo,
                            ])
                        </td>
                        <td>
                            @include('gestion.depenses._case', [
                                'document' => $depense->excelDocument,
                                'depense' => $depense,
                                'typeDepense' => $typeDepense,
                                'champ' => 'excel',
                                'peutModifier' => $peutModifier,
                                'peutSupprimer' => $peutSupprimer,
                                'tailleMaxMo' => $tailleMaxMo,
                            ])
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div>
                                    <span class="text-muted small me-1">Bordereau :</span>
                                    @include('gestion.partials._verrou_document', ['document' => $depense->bordereauDocument])
                                </div>
                                <div>
                                    <span class="text-muted small me-1">Excel :</span>
                                    @include('gestion.partials._verrou_document', ['document' => $depense->excelDocument])
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-calendar-range fs-1 text-muted"></i>
                                <p class="text-muted mb-0">
                                    Aucune année académique pour l'instant (Administration &gt; Référentiels &gt; Années académiques).
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
