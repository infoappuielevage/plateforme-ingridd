@extends('layouts.gestion')

@section('title', ($champ ? 'Modifier — ' : 'Ajouter — ').'un champ supplémentaire')

@section('content')
<h1 class="h3">Champs supplémentaires</h1>
<h2 class="h5">{{ $champ ? 'Modifier' : 'Ajouter' }}</h2>

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
      action="{{ $champ ? route('gestion.champs-supplementaires.update', $champ->id) : route('gestion.champs-supplementaires.store') }}">
    @csrf
    @if ($champ) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Libellé <span class="text-danger">*</span></label>
            <input type="text" name="libelle" class="form-control" maxlength="150"
                   value="{{ old('libelle', $champ->libelle ?? '') }}" required>
            <div class="form-text">Le nom affiché sur l'écran de gestion d'une session (ex. « Certificat de scolarité »).</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Ordre d'affichage <span class="text-danger">*</span></label>
            <input type="number" name="ordre" class="form-control" min="0"
                   value="{{ old('ordre', $champ->ordre ?? 0) }}" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1"
                       @checked(old('actif', $champ->actif ?? true))>
                <label class="form-check-label" for="actif">Actif</label>
            </div>
            <div class="form-text">Un champ inactif n'apparaît plus sur les nouvelles sessions et n'est plus ajouté rétroactivement aux sessions existantes — les cases déjà créées restent inchangées.</div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12">
            <h3 class="h6 mb-2">Documents concernés</h3>
            @error('pour_pv')
                <div class="text-danger small mb-2">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="pour_pv" value="0">
                <input type="checkbox" name="pour_pv" id="pour_pv" class="form-check-input" value="1"
                       @checked(old('pour_pv', $champ->pour_pv ?? false))>
                <label class="form-check-label" for="pour_pv">PV de délibération</label>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="pour_releve" value="0">
                <input type="checkbox" name="pour_releve" id="pour_releve" class="form-check-input" value="1"
                       @checked(old('pour_releve', $champ->pour_releve ?? false))>
                <label class="form-check-label" for="pour_releve">Relevé de notes</label>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="pour_soutenance" value="0">
                <input type="checkbox" name="pour_soutenance" id="pour_soutenance" class="form-check-input" value="1"
                       @checked(old('pour_soutenance', $champ->pour_soutenance ?? false))>
                <label class="form-check-label" for="pour_soutenance">PV de soutenance</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Cycles / niveaux concernés <span class="text-danger">*</span></label>
            @php $cyclesActuels = old('cycles', $champ->cycles ?? []); @endphp
            <div class="row g-2">
                @foreach ($cycles as $valeur => $libelle)
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="cycles[]" id="cycle_{{ $valeur }}" class="form-check-input" value="{{ $valeur }}"
                                   @checked(in_array($valeur, $cyclesActuels, true))>
                            <label class="form-check-label" for="cycle_{{ $valeur }}">{{ $libelle }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Au moins un cycle doit être coché — le champ n'apparaîtra que sur les sessions de ce(s) cycle(s).</div>
        </div>

        <div class="col-12">
            <div class="alert alert-info mb-0 small">
                <i class="bi bi-info-circle"></i>
                Cocher/modifier ces réglages ajoute automatiquement la case correspondante aux sessions déjà existantes qui correspondent (document concerné + cycle), en plus des nouvelles sessions créées ensuite. Les cases déjà créées ne sont jamais retirées automatiquement.
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $champ ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.champs-supplementaires.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>
@endsection
