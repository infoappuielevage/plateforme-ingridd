@extends('layouts.gestion')

@section('title', $contrat->partenaire_prestataire.' — '.$typeContrat->libelle)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $contrat->partenaire_prestataire }}</h1>
        <p class="text-muted small mb-0">
            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $typeContrat->libelle }}</span>
            <span class="badge {{ $contrat->statutBadgeClass() }}">{{ $contrat->statutLibelle() }}</span>
            @if ($contrat->echeanceDepassee())
                <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> Échéance dépassée</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @if (\App\Support\ResourceAccess::can('contrats', 'edit'))
            <a href="{{ route('gestion.contrats.edit', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Modifier
            </a>
        @endif
        <a href="{{ route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]) }}" class="btn btn-outline-secondary">
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

@php
    $peutModifier = \App\Support\ResourceAccess::can('contrats', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('contrats', 'delete');
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-card-text"></i> Objet et observations</div>
            <div class="card-body">
                <p class="mb-3">{{ $contrat->objet }}</p>
                <h2 class="h6">Observations</h2>
                <p class="mb-0">{{ $contrat->observations ?: '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-calendar-range"></i> Dates et montant</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Période</dt>
                    <dd class="col-7">{{ $contrat->periodeLibelle() }}</dd>
                    <dt class="col-5">Date de signature</dt>
                    <dd class="col-7">{{ $contrat->date_signature?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-5">Montant</dt>
                    <dd class="col-7">{{ $contrat->montantFormate() ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-accent d-flex align-items-center gap-2"><i class="bi bi-person-lines-fill"></i> Référent et contact</div>
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-md-4">
                <div class="text-muted">Responsable côté INGRIDD</div>
                <div>{{ $contrat->responsable?->name ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">Contact</div>
                <div>{{ $contrat->contact_nom ?: '—' }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Email</div>
                <div>{{ $contrat->contact_email ?: '—' }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Téléphone</div>
                <div>{{ $contrat->contact_telephone ?: '—' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-success d-flex align-items-center gap-2"><i class="bi bi-file-earmark-text"></i> Contrat signé</div>
    <div class="card-body">
        @include('gestion.contrats._contrat-signe', ['contratSigne' => $contratSigne, 'contrat' => $contrat, 'typeContrat' => $typeContrat, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'tailleMaxMo' => $tailleMaxMo])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-paperclip"></i> Avenants</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($avenants as $fichier)
                    <tr>
                        <td>
                            @include('gestion.contrats._avenant', ['fichier' => $fichier, 'contrat' => $contrat, 'typeContrat' => $typeContrat, 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted small py-3">Aucun avenant pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('gestion.contrats.avenants.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small">Libellé (optionnel)</label>
                    <input type="text" name="libelle" class="form-control form-control-sm" maxlength="150" placeholder="Ex. Avenant n°1 — prolongation">
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
