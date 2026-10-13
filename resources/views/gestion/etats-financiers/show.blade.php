@extends('layouts.gestion')

@section('title', 'État financier '.$etatFinancier->annee)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">État financier {{ $etatFinancier->annee }}</h1>
    </div>
    <div class="d-flex gap-2">
        @if (\App\Support\ResourceAccess::can('etats_financiers', 'edit'))
            <a href="{{ route('gestion.etats-financiers.edit', $etatFinancier->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Modifier
            </a>
        @endif
        <a href="{{ route('gestion.etats-financiers.index') }}" class="btn btn-outline-secondary">
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

@if (session('succes'))
    <div class="alert alert-success">{{ session('succes') }}</div>
@endif

@php
    $peutModifier = \App\Support\ResourceAccess::can('etats_financiers', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('etats_financiers', 'delete');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-file-earmark-text"></i> Fichier</div>
    <div class="card-body">
        @if ($etatFinancier->estRempli())
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('documents.voir', $etatFinancier->document_id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
                    <i class="bi bi-file-earmark-text"></i>
                    {{ $etatFinancier->document->nom_original }}
                </a>
                <span class="text-muted small">({{ $etatFinancier->document->tailleFormatee() }})</span>
                <a href="{{ route('documents.telecharger', $etatFinancier->document_id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
                    <i class="bi bi-download"></i>
                </a>
                @include('gestion.partials._lien_historique', ['type' => \App\Models\Document::class, 'id' => $etatFinancier->document_id])
                @include('gestion.partials._verrou_document', ['document' => $etatFinancier->document])
                @unless ($etatFinancier->document->estVerrouille())
                    @if ($peutModifier)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remplacerFichierEtatFinancier">
                            Remplacer
                        </button>
                    @endif
                    @if ($peutSupprimer)
                        <form method="POST" action="{{ route('gestion.etats-financiers.fichier.destroy', $etatFinancier->id) }}"
                              onsubmit="return confirm('Retirer le fichier ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    @endif
                @endunless
            </div>
            @if ($peutModifier && ! $etatFinancier->document->estVerrouille())
                <div class="collapse mt-2" id="remplacerFichierEtatFinancier">
                    <form method="POST" action="{{ route('gestion.etats-financiers.fichier.store', $etatFinancier->id) }}" enctype="multipart/form-data" class="d-flex gap-2">
                        @csrf
                        <input type="file" name="fichier" class="form-control form-control-sm" required>
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Remplacer</button>
                    </form>
                </div>
            @endif
        @else
            @if ($peutModifier)
                <form method="POST" action="{{ route('gestion.etats-financiers.fichier.store', $etatFinancier->id) }}" enctype="multipart/form-data" class="d-flex gap-2">
                    @csrf
                    <input type="file" name="fichier" class="form-control form-control-sm" required>
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                        <i class="bi bi-upload"></i> Déposer
                    </button>
                </form>
                <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo.</div>
            @else
                <span class="text-muted small">Aucun fichier déposé.</span>
            @endif
        @endif
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-card-text"></i> Observation</div>
    <div class="card-body">
        <p class="mb-0">{{ $etatFinancier->observation ?: '—' }}</p>
    </div>
</div>
@endsection
