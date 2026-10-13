@extends('layouts.gestion')

@section('title', ($contrat ? 'Modifier — ' : 'Nouveau — ').$typeContrat->libelle)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $contrat ? 'Modifier le contrat' : 'Nouveau contrat' }} — {{ $typeContrat->libelle }}</h1>
    <a href="{{ $contrat ? route('gestion.contrats.show', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) : route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]) }}" class="btn btn-outline-secondary">
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

@php
    $v = fn (string $champ, $defaut = null) => old($champ, $contrat->{$champ} ?? $defaut);
    $vDate = function (string $champ) use ($v) {
        $valeur = $v($champ);

        return $valeur instanceof \Illuminate\Support\Carbon ? $valeur->format('Y-m-d') : $valeur;
    };
@endphp

<form method="POST"
      action="{{ $contrat ? route('gestion.contrats.update', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) : route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]) }}"
      style="max-width: 860px;">
    @csrf
    @if ($contrat) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-vector-pen"></i> Informations générales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Partenaire / Prestataire <span class="text-danger">*</span></label>
                    <input type="text" name="partenaire_prestataire" class="form-control" maxlength="200"
                           value="{{ $v('partenaire_prestataire') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Statut <span class="text-danger">*</span></label>
                    <select name="statut" class="form-select" required>
                        @foreach ($statuts as $cle => $libelle)
                            <option value="{{ $cle }}" @selected($v('statut', 'actif') === $cle)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Objet <span class="text-danger">*</span></label>
                    <input type="text" name="objet" class="form-control" maxlength="255"
                           value="{{ $v('objet') }}" placeholder="Ex. Entretien des locaux, Partenariat pédagogique..." required>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-calendar-range"></i> Dates et montant</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date de signature</label>
                    <input type="date" name="date_signature" class="form-control" value="{{ $vDate('date_signature') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de début</label>
                    <input type="date" name="date_debut" class="form-control" value="{{ $vDate('date_debut') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin</label>
                    <input type="date" name="date_fin" class="form-control" value="{{ $vDate('date_fin') }}">
                    <div class="form-text">Laisser vide pour une durée indéterminée.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Montant (FCFA)</label>
                    <input type="number" name="montant" class="form-control" min="0" step="1" value="{{ $v('montant') }}">
                    <div class="form-text">Facultatif — un partenariat n'a pas forcément de valeur monétaire.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header header-accent d-flex align-items-center gap-2"><i class="bi bi-person-lines-fill"></i> Référent et contact</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Responsable côté INGRIDD</label>
                    <select name="responsable_id" class="form-select">
                        <option value="">— Aucun —</option>
                        @foreach ($responsablesPossibles as $utilisateur)
                            <option value="{{ $utilisateur->id }}" @selected((string) $v('responsable_id') === (string) $utilisateur->id)>{{ $utilisateur->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"></div>
                <div class="col-md-4">
                    <label class="form-label">Nom du contact</label>
                    <input type="text" name="contact_nom" class="form-control" maxlength="150" value="{{ $v('contact_nom') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email du contact</label>
                    <input type="email" name="contact_email" class="form-control" maxlength="255" value="{{ $v('contact_email') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Téléphone du contact</label>
                    <input type="text" name="contact_telephone" class="form-control" maxlength="30" value="{{ $v('contact_telephone') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header header-success d-flex align-items-center gap-2"><i class="bi bi-card-text"></i> Observations</div>
        <div class="card-body">
            <textarea name="observations" class="form-control" rows="3" maxlength="2000">{{ $v('observations') }}</textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $contrat ? 'Enregistrer' : 'Créer le contrat' }}
    </button>
</form>
@endsection
