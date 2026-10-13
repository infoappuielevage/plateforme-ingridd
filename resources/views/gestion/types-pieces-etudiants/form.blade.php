@extends('layouts.gestion')

@section('title', ($type ? 'Modifier — ' : 'Ajouter — ').'un type de pièce')

@section('content')
<h1 class="h3">Types de pièces (dossier étudiant)</h1>
<h2 class="h5">{{ $type ? 'Modifier' : 'Ajouter' }}</h2>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 780px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm" style="max-width: 780px;">
<div class="card-body">
<form method="POST"
      action="{{ $type ? route('gestion.types-pieces-etudiants.update', $type->id) : route('gestion.types-pieces-etudiants.store') }}">
    @csrf
    @if ($type) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control" maxlength="50"
                   value="{{ old('code', $type->code ?? '') }}"
                   {{ $type ? 'disabled' : 'required' }}>
            @if ($type)
                <div class="form-text">Le code ne peut plus être modifié après création.</div>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label">Ordre d'affichage <span class="text-danger">*</span></label>
            <input type="number" name="ordre" class="form-control" min="0"
                   value="{{ old('ordre', $type->ordre ?? 0) }}" required>
        </div>

        <div class="col-md-8">
            <label class="form-label">Libellé <span class="text-danger">*</span></label>
            <input type="text" name="libelle" class="form-control" maxlength="150"
                   value="{{ old('libelle', $type->libelle ?? '') }}" required>
            <div class="form-text">Le nom affiché sur le dossier d'un étudiant (ex. « CNIB », « Relevés de notes »).</div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12">
            <label class="form-label">Portée <span class="text-danger">*</span></label>
            <div class="form-check">
                <input type="radio" name="recurrente" id="recurrente_non" class="form-check-input" value="0"
                       @checked(! old('recurrente', $type->recurrente ?? false)) {{ $type ? 'disabled' : '' }} required>
                <label class="form-check-label" for="recurrente_non">
                    Par dossier — une pièce d'identité valable pour tout le parcours de l'étudiant (ex. CNIB, acte de naissance).
                </label>
            </div>
            <div class="form-check">
                <input type="radio" name="recurrente" id="recurrente_oui" class="form-check-input" value="1"
                       @checked(old('recurrente', $type->recurrente ?? false)) {{ $type ? 'disabled' : '' }}>
                <label class="form-check-label" for="recurrente_oui">
                    Par année académique — une pièce à redéposer chaque année (ex. fiche d'inscription, relevé de notes).
                </label>
            </div>
            @if ($type)
                <div class="form-text">La portée ne peut plus être modifiée après création (mélangerait des cases par dossier et par année pour un même type).</div>
            @endif
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1"
                       @checked(old('actif', $type->actif ?? true))>
                <label class="form-check-label" for="actif">Actif</label>
            </div>
            <div class="form-text">Un type inactif n'apparaît plus sur les nouveaux dossiers/inscriptions et n'est plus ajouté rétroactivement aux dossiers existants — les cases déjà créées restent inchangées.</div>
        </div>

        <div class="col-12">
            <div class="alert alert-info mb-0 small">
                <i class="bi bi-info-circle"></i>
                Ajouter (ou réactiver) un type crée automatiquement la case correspondante sur tous les dossiers étudiant déjà existants, en plus des nouveaux dossiers créés ensuite. Les cases déjà créées ne sont jamais retirées automatiquement.
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $type ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.types-pieces-etudiants.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>
@endsection
