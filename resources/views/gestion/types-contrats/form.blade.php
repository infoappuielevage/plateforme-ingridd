@extends('layouts.gestion')

@section('title', ($type ? 'Modifier — ' : 'Ajouter — ').'une catégorie de contrat')

@section('content')
<h1 class="h3">Catégories de contrats</h1>
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
      action="{{ $type ? route('gestion.types-contrats.update', $type->id) : route('gestion.types-contrats.store') }}">
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
            @else
                <div class="form-text">Identifiant technique (ex. « PARTENARIAT »), utilisé dans l'adresse de l'onglet.</div>
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
            <div class="form-text">Le nom affiché sur l'onglet (ex. « Partenariats », « Prestations de service »).</div>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1"
                       @checked(old('actif', $type->actif ?? true))>
                <label class="form-check-label" for="actif">Actif</label>
            </div>
            <div class="form-text">Une catégorie inactive n'apparaît plus en onglet sur l'écran Gestion des contrats. Les contrats déjà saisis dans cette catégorie restent inchangés.</div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $type ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.types-contrats.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>
@endsection
