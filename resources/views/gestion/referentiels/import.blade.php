@extends('layouts.gestion')

@section('title', 'Importer — '.$definition['pluriel'])

@section('content')
<h1 class="h3">{{ $definition['pluriel'] }}</h1>
<h2 class="h5">Importer depuis un fichier Excel</h2>

@if (session('resultatImport'))
    @php $resultat = session('resultatImport'); @endphp
    <div class="alert alert-{{ empty($resultat['erreurs']) ? 'success' : 'warning' }}">
        <p class="mb-1">
            <strong>{{ $resultat['crees'] }}</strong> créé(s),
            <strong>{{ $resultat['mis_a_jour'] }}</strong> mis à jour.
        </p>
        @if (! empty($resultat['erreurs']))
            <p class="mb-1">{{ count($resultat['erreurs']) }} ligne(s) ignorée(s) :</p>
            <ul class="mb-0 small">
                @foreach ($resultat['erreurs'] as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm" style="max-width: 620px;">
    <div class="card-body">
        <p>
            Téléchargez d'abord le modèle, remplissez une ligne par élément (sans toucher à
            l'ordre des colonnes), puis renvoyez le fichier complété ci-dessous.
        </p>
        <p class="text-muted small">
            @if ($definition['a_code'])
                Un code déjà existant met à jour le libellé/l'ordre/l'actif plutôt que de créer un
                doublon — le code lui-même ne change jamais après import, comme à la modification
                manuelle.
            @else
                Un libellé déjà existant met à jour son statut actif plutôt que de créer un
                doublon.
            @endif
            Une ligne invalide est signalée après l'import et simplement ignorée — elle n'empêche
            jamais le reste du fichier d'être importé.
        </p>

        <a href="{{ route('gestion.referentiels.import.modele', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary mb-3">
            <i class="bi bi-download"></i> Télécharger le modèle
        </a>

        <form method="POST" action="{{ route('gestion.referentiels.import', ['referentiel' => $referentiel]) }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Fichier Excel (.xlsx) complété</label>
                <input type="file" name="fichier" class="form-control" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-upload"></i> Importer
            </button>
            <a href="{{ route('gestion.referentiels.index', ['referentiel' => $referentiel]) }}" class="btn btn-outline-secondary">Annuler</a>
        </form>
    </div>
</div>
@endsection
