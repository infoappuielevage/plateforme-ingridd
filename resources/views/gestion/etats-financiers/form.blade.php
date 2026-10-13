@extends('layouts.gestion')

@section('title', ($etatFinancier ? 'Modifier — ' : 'Nouveau — ').'État financier')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $etatFinancier ? 'Modifier l\'état financier' : 'Nouvel état financier' }}</h1>
    <a href="{{ $etatFinancier ? route('gestion.etats-financiers.show', $etatFinancier->id) : route('gestion.etats-financiers.index') }}" class="btn btn-outline-secondary">
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

@php $v = fn (string $champ, $defaut = null) => old($champ, $etatFinancier->{$champ} ?? $defaut); @endphp

<form method="POST"
      action="{{ $etatFinancier ? route('gestion.etats-financiers.update', $etatFinancier->id) : route('gestion.etats-financiers.store') }}"
      style="max-width: 680px;">
    @csrf
    @if ($etatFinancier) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Année <span class="text-danger">*</span></label>
                    <input type="number" name="annee" class="form-control" min="2000" max="2100" value="{{ $v('annee') }}" required>
                    <div class="form-text">Année civile (ex. 2023), pas une année académique.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observation</label>
                    <textarea name="observation" class="form-control" rows="3" maxlength="2000">{{ $v('observation') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $etatFinancier ? 'Enregistrer' : 'Créer' }}
    </button>
</form>

@unless ($etatFinancier)
    <div class="form-text mt-2" style="max-width: 680px;">
        Le fichier se dépose dans un second temps, depuis la page de l'état financier.
    </div>
@endunless
@endsection
