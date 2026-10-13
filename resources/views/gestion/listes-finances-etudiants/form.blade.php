@extends('layouts.gestion')

@section('title', 'Nouveau dossier — Liste des étudiants (finances)')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Nouveau dossier — Liste des étudiants (finances)</h1>
    <a href="{{ route('gestion.listes-finances-etudiants.index') }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('gestion.listes-finances-etudiants.store') }}" style="max-width: 480px;">
    @csrf

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label">Année académique <span class="text-danger">*</span></label>
            @if ($anneesAcademiques->isEmpty())
                <div class="form-text text-danger">
                    Toutes les années académiques actives ont déjà leur dossier, ou aucune n'est encore
                    définie (Administration &gt; Référentiels &gt; Années académiques).
                </div>
            @else
                <select name="annee_academique_id" class="form-select" required>
                    <option value="">— Choisir —</option>
                    @foreach ($anneesAcademiques as $annee)
                        <option value="{{ $annee->id }}" @selected(old('annee_academique_id') == $annee->id)>{{ $annee->libelle }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    @unless ($anneesAcademiques->isEmpty())
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Créer le dossier
        </button>
    @endunless
</form>
@endsection
