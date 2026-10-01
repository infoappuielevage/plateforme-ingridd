@extends('layouts.gestion')

@section('title', ($reunion ? 'Modifier — ' : 'Nouveau — ').'PV de réunion')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $reunion ? 'Modifier la réunion' : 'Nouvelle réunion' }}</h1>
    <a href="{{ $reunion ? route('gestion.reunions.show', $reunion->id) : route('gestion.reunions.index') }}" class="btn btn-outline-secondary">
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

@php $v = fn (string $champ, $defaut = null) => old($champ, $reunion->{$champ} ?? $defaut); @endphp

<form method="POST"
      action="{{ $reunion ? route('gestion.reunions.update', $reunion->id) : route('gestion.reunions.store') }}"
      style="max-width: 780px;">
    @csrf
    @if ($reunion) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Année académique <span class="text-danger">*</span></label>
                    <select name="annee_academique_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($anneesAcademiques as $annee)
                            <option value="{{ $annee->id }}" @selected($v('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date de la réunion <span class="text-danger">*</span></label>
                    <input type="date" name="date_reunion" class="form-control"
                           value="{{ $v('date_reunion') instanceof \Illuminate\Support\Carbon ? $v('date_reunion')->format('Y-m-d') : $v('date_reunion') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Type de réunion <span class="text-danger">*</span></label>
                    <select name="sous_type_document_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        @foreach ($typesReunion as $type)
                            <option value="{{ $type->id }}" @selected($v('sous_type_document_id') == $type->id)>{{ $type->libelle }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        La liste se complète depuis Administration &gt; Référentiels &gt; Types de documents &gt;
                        PV de réunion &gt; Sous-types.
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ $v('description') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $reunion ? 'Enregistrer' : 'Créer la réunion' }}
    </button>
</form>
@endsection
