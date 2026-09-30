@extends('layouts.gestion')

@section('title', ($pvSoutenance ? 'Modifier — ' : 'Nouveau — ').'PV de soutenance')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $pvSoutenance ? 'Modifier le PV de soutenance' : 'Nouveau PV de soutenance' }}</h1>
    <a href="{{ $pvSoutenance ? route('gestion.pv-soutenances.show', $pvSoutenance->id) : route('gestion.pv-soutenances.index') }}" class="btn btn-outline-secondary">
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

@php $v = fn (string $champ, $defaut = null) => old($champ, $pvSoutenance->{$champ} ?? $defaut); @endphp

<form method="POST"
      action="{{ $pvSoutenance ? route('gestion.pv-soutenances.update', $pvSoutenance->id) : route('gestion.pv-soutenances.store') }}"
      style="max-width: 900px;">
    @csrf
    @if ($pvSoutenance) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-journal-bookmark"></i> Registre</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Niveau <span class="text-danger">*</span></label>
                    <select name="niveau" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($niveaux as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($v('niveau') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° d'ordre du registre <span class="text-danger">*</span></label>
                    <input type="text" name="numero_ordre_registre" class="form-control" maxlength="50"
                           value="{{ $v('numero_ordre_registre') }}" required>
                    <div class="form-text">Unique pour ce niveau — renseigné à la main depuis le registre papier.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de soutenance <span class="text-danger">*</span></label>
                    <input type="date" name="date_soutenance" class="form-control"
                           value="{{ $v('date_soutenance') instanceof \Illuminate\Support\Carbon ? $v('date_soutenance')->format('Y-m-d') : $v('date_soutenance') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° du PV <span class="text-danger">*</span></label>
                    <input type="text" name="numero_pv" class="form-control" maxlength="50"
                           value="{{ $v('numero_pv') }}" required>
                    <div class="form-text">Unique pour toute la plateforme — renseigné à la main.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-person-badge"></i> Étudiant</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" maxlength="150" value="{{ $v('nom') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                    <input type="text" name="prenoms" class="form-control" maxlength="150" value="{{ $v('prenoms') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                    <input type="date" name="date_naissance" class="form-control"
                           value="{{ $v('date_naissance') instanceof \Illuminate\Support\Carbon ? $v('date_naissance')->format('Y-m-d') : $v('date_naissance') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                    <input type="text" name="lieu_naissance" class="form-control" maxlength="150" value="{{ $v('lieu_naissance') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Filière <span class="text-danger">*</span></label>
                    <select name="filiere_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($filieres as $filiere)
                            <option value="{{ $filiere->id }}" @selected($v('filiere_id') == $filiere->id)>{{ $filiere->libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-award"></i> Soutenance</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Thème <span class="text-danger">*</span></label>
                    <textarea name="theme" class="form-control" rows="2" maxlength="2000" required>{{ $v('theme') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Décision du jury <span class="text-danger">*</span></label>
                    <select name="decision_jury" class="form-select" required>
                        @foreach ($decisions as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($v('decision_jury', \App\Models\PvSoutenance::DECISION_ADMIS) === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Moyenne <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" max="20" name="moyenne" class="form-control" value="{{ $v('moyenne') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mention <span class="text-danger">*</span></label>
                    <select name="mention" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($mentions as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($v('mention') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-people"></i> Jury</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Président du jury <span class="text-danger">*</span></label>
                    <input type="text" name="president_jury" class="form-control" maxlength="150" value="{{ $v('president_jury') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Directeur de mémoire</label>
                    <input type="text" name="directeur_memoire" class="form-control" maxlength="150" value="{{ $v('directeur_memoire') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rapporteur</label>
                    <input type="text" name="rapporteur" class="form-control" maxlength="150" value="{{ $v('rapporteur') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Maître de stage</label>
                    <input type="text" name="maitre_stage" class="form-control" maxlength="150" value="{{ $v('maitre_stage') }}">
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $pvSoutenance ? 'Enregistrer' : 'Créer le PV' }}
    </button>
</form>
@endsection
