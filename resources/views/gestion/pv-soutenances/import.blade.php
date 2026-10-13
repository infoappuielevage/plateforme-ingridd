{{--
    Import Excel groupé — demande explicite du client ("il faudrait
    également qu'on puisse importer la liste des étudiants avec toutes les
    informations à travers un fichier excel, après on pourrait importer les
    fichiers comme ce qui est déjà en place"), MÊME GABARIT que
    resources/views/gestion/etudiants/import.blade.php. Voir le docblock de
    App\Http\Controllers\PvSoutenanceController::import() pour le détail du
    traitement (dont "N° PV" comme clé de création/mise à jour).

    Particularité propre à cet écran : "Niveau"/"Décision du jury"/"Mention"
    sont des constantes PHP fixes (pas un référentiel consultable ailleurs
    dans l'application) — leurs codes valides sont donc rappelés
    explicitement ici, contrairement à "Filière" (référentiel existant,
    simplement listé pour rappel pratique).
--}}
@extends('layouts.gestion')

@section('title', 'Importer des PV de soutenance')

@section('content')
<h1 class="h3">PV de soutenance</h1>
<h2 class="h5">Importer depuis un fichier Excel</h2>

@if (session('resultatImport'))
    @php $resultat = session('resultatImport'); @endphp
    <div class="alert alert-{{ empty($resultat['erreurs']) ? 'success' : 'warning' }}">
        <p class="mb-1">
            <strong>{{ $resultat['crees'] }}</strong> PV créé(s),
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

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <p>
                    Téléchargez d'abord le modèle, remplissez une ligne par étudiant (sans toucher à
                    l'ordre des colonnes), puis renvoyez le fichier complété ci-dessous.
                </p>
                <p class="text-muted small">
                    Le « N° PV » sert de repère : un numéro déjà connu met à jour le PV correspondant
                    (ses cases/documents déjà déposés sont conservés), un numéro inconnu crée un
                    nouveau PV avec ses cases vides (diplôme, image du registre...) — exactement comme
                    si vous l'aviez saisi un par un. Les dates se saisissent au format jj/mm/aaaa. Une
                    ligne invalide (code inconnu, numéro déjà pris pour ce niveau...) est signalée après
                    l'import et simplement ignorée — elle n'empêche jamais le reste du fichier d'être
                    importé.
                </p>
                <p class="text-muted small mb-3">
                    Une fois la liste importée, déposez les fichiers (diplôme, image du registre,
                    photo...) depuis la fiche de chaque étudiant, comme d'habitude.
                </p>

                <a href="{{ route('gestion.pv-soutenances.import.modele') }}" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-download"></i> Télécharger le modèle
                </a>

                <form method="POST" action="{{ route('gestion.pv-soutenances.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Fichier Excel (.xlsx) complété</label>
                        <input type="file" name="fichier" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importer
                    </button>
                    <a href="{{ route('gestion.pv-soutenances.index') }}" class="btn btn-outline-secondary">Annuler</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header header-info d-flex align-items-center gap-2">
                <i class="bi bi-info-circle"></i> Codes acceptés
            </div>
            <div class="card-body small">
                <p class="mb-1"><strong>Niveau</strong></p>
                <ul class="mb-3">
                    @foreach (\App\Models\PvSoutenance::NIVEAUX as $code => $libelle)
                        <li><code>{{ $code }}</code> — {{ $libelle }}</li>
                    @endforeach
                </ul>
                <p class="mb-1"><strong>Décision du jury</strong></p>
                <ul class="mb-3">
                    @foreach (\App\Models\PvSoutenance::DECISIONS as $code => $libelle)
                        <li><code>{{ $code }}</code> — {{ $libelle }}</li>
                    @endforeach
                </ul>
                <p class="mb-1"><strong>Mention</strong></p>
                <ul class="mb-3">
                    @foreach (\App\Models\PvSoutenance::MENTIONS as $code => $libelle)
                        <li><code>{{ $code }}</code> — {{ $libelle }}</li>
                    @endforeach
                </ul>
                <p class="mb-1"><strong>Filière</strong></p>
                @if ($filieres->isEmpty())
                    <p class="text-muted mb-0">Aucune filière active pour le moment (Référentiels &gt; Filières).</p>
                @else
                    <ul class="mb-0">
                        @foreach ($filieres as $filiere)
                            <li><code>{{ $filiere->code }}</code> — {{ $filiere->libelle }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
