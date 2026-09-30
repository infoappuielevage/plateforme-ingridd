@extends('layouts.gestion')

@section('title', 'Nouvelle session de délibération')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Nouvelle session de délibération</h1>
    <a href="{{ route('gestion.sessions-deliberation.index') }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('gestion.sessions-deliberation.store') }}" style="max-width: 780px;">
    @csrf

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Année académique <span class="text-danger">*</span></label>
                    <select name="annee_academique_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($anneesAcademiques as $annee)
                            <option value="{{ $annee->id }}" @selected(old('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session <span class="text-danger">*</span></label>
                    <select name="session" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($sessions as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(old('session') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Niveau (cycle) <span class="text-danger">*</span></label>
                    <select name="cycle" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($cycles as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(old('cycle') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Détermine les cases créées automatiquement (semestres pour Licence/Master, années de thèse pour Doctorat).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Filière <span class="text-danger">*</span></label>
                    <select name="filiere_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($filieres as $filiere)
                            <option value="{{ $filiere->id }}" @selected(old('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> Créer la session
    </button>
</form>
@endsection
