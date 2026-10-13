@extends('layouts.gestion')

@section('title', ($item ? 'Modifier — ' : 'Ajouter — ').$definition['pluriel'])

@section('content')
<h1 class="h3">{{ $definition['pluriel'] }}</h1>
<h2 class="h5">{{ $item ? 'Modifier' : 'Ajouter' }}</h2>

{{-- Ce formulaire n'affichait jusqu'ici AUCUNE erreur de validation (ni ici,
     ni via @error sur les champs) : une erreur retournait silencieusement à
     ce même formulaire sans rien expliquer, ex. unicité du libellé pour les
     années académiques (voir ReferentielController::valider()). --}}
@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 620px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm" style="max-width: 620px;">
<div class="card-body">
<form method="POST"
      action="{{ $item ? route('gestion.referentiels.update', ['referentiel' => $referentiel, 'id' => $item->id]) : route('gestion.referentiels.store', ['referentiel' => $referentiel]) }}">
    @csrf
    @if ($item) @method('PUT') @endif

    <div class="row g-3">
        @if ($definition['a_code'])
            <div class="col-md-6">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" maxlength="50"
                       value="{{ old('code', $item->code ?? '') }}"
                       {{ $item ? 'disabled' : 'required' }}>
                @if ($item)
                    <div class="form-text">Le code ne peut plus être modifié après création.</div>
                @endif
            </div>

            <div class="col-md-6">
                <label class="form-label">Ordre d'affichage <span class="text-danger">*</span></label>
                <input type="number" name="ordre" class="form-control" min="0"
                       value="{{ old('ordre', $item->ordre ?? 0) }}" required>
            </div>
        @endif

        <div class="col-12">
            <label class="form-label">Libellé <span class="text-danger">*</span></label>
            <input type="text" name="libelle" class="form-control" maxlength="150"
                   value="{{ old('libelle', $item->libelle ?? '') }}"
                   @if (! $definition['a_code']) placeholder="2025-2026" @endif
                   required>
            @if (! $definition['a_code'])
                <div class="form-text">Format AAAA-AAAA (ex. 2025-2026).</div>
            @endif
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1"
                       @checked(old('actif', $item->actif ?? true))>
                <label class="form-check-label" for="actif">Actif</label>
            </div>
            <div class="form-text">{{ $definition['aide_actif'] }}</div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $item ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>
@endsection
