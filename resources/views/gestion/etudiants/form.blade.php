@extends('layouts.gestion')

@section('title', ($etudiant ? 'Modifier — ' : 'Nouveau — ').'Dossier étudiant')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $etudiant ? 'Modifier le dossier étudiant' : 'Nouveau dossier étudiant' }}</h1>
    <a href="{{ $etudiant ? route('gestion.etudiants.show', $etudiant->id) : route('gestion.etudiants.index') }}" class="btn btn-outline-secondary">
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

@php $v = fn (string $champ, $defaut = null) => old($champ, $etudiant->{$champ} ?? $defaut); @endphp

<form method="POST"
      action="{{ $etudiant ? route('gestion.etudiants.update', $etudiant->id) : route('gestion.etudiants.store') }}"
      style="max-width: 780px;">
    @csrf
    @if ($etudiant) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-person-vcard"></i> Identité</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Matricule <span class="text-danger">*</span></label>
                    <input type="text" name="matricule" class="form-control" maxlength="50" value="{{ $v('matricule') }}" required>
                    <div class="form-text">Unique pour tout le parcours de l'étudiant à INGRIDD.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" maxlength="150" value="{{ $v('nom') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                    <input type="text" name="prenoms" class="form-control" maxlength="150" value="{{ $v('prenoms') }}" required>
                </div>
            </div>
        </div>
    </div>

    @unless ($etudiant)
        {{-- Uniquement à la création : la première inscription de l'étudiant
             (voir EtudiantController::store()) — les inscriptions des années
             suivantes s'ajoutent depuis la fiche du dossier (show.blade.php),
             jamais depuis ce formulaire. --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent"><i class="bi bi-calendar-check"></i> Inscription</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Année académique <span class="text-danger">*</span></label>
                        <select name="annee_academique_id" class="form-select" required>
                            <option value="">— Choisir —</option>
                            @foreach ($anneesAcademiques as $annee)
                                <option value="{{ $annee->id }}" @selected(old('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Niveau <span class="text-danger">*</span></label>
                        <select name="niveau_id" class="form-select" required>
                            <option value="">— Choisir —</option>
                            @foreach ($niveaux as $niveau)
                                <option value="{{ $niveau->id }}" @selected(old('niveau_id') == $niveau->id)>{{ $niveau->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filière <span class="text-danger">*</span></label>
                        <select name="filiere_id" class="form-select" required>
                            <option value="">— Choisir —</option>
                            @foreach ($filieres as $filiere)
                                <option value="{{ $filiere->id }}" @selected(old('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    @endunless

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $etudiant ? 'Enregistrer' : 'Créer le dossier' }}
    </button>
</form>
@endsection
