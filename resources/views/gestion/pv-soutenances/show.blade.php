@extends('layouts.gestion')

@section('title', 'PV de soutenance')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
        @if ($pvSoutenance->photo)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pvSoutenance->photo) }}" alt="Photo de {{ $pvSoutenance->nomComplet() }}"
                 class="rounded-circle" style="width: 56px; height: 56px; object-fit: cover;">
        @else
            <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                <i class="bi bi-person-fill fs-4 text-secondary"></i>
            </div>
        @endif
        <div>
            <h1 class="h3 mb-0">{{ $pvSoutenance->nomComplet() }}</h1>
            <p class="text-muted small mb-0 d-flex align-items-center gap-2">
                <span class="badge {{ $pvSoutenance->niveauBadgeClasse() }}">{{ $pvSoutenance->niveauLibelle() }}</span>
                {{ $pvSoutenance->filiere->libelle }} — N° PV {{ $pvSoutenance->numero_pv }}
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        @if (\App\Support\ResourceAccess::can('pv_soutenances', 'edit'))
            <a href="{{ route('gestion.pv-soutenances.edit', $pvSoutenance->id) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Modifier
            </a>
        @endif
        <a href="{{ route('gestion.pv-soutenances.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2">
        <i class="bi bi-info-circle"></i> Détails
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">N° d'ordre du registre</div>
                <div>{{ $pvSoutenance->numero_ordre_registre }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date de soutenance</div>
                <div>{{ $pvSoutenance->date_soutenance?->format('d/m/Y') }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date et lieu de naissance</div>
                <div>{{ $pvSoutenance->date_naissance?->format('d/m/Y') }} — {{ $pvSoutenance->lieu_naissance }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Décision du jury</div>
                <div>
                    <span class="badge {{ $pvSoutenance->decision_jury === \App\Models\PvSoutenance::DECISION_ADMIS ? 'text-bg-success' : 'text-bg-danger' }}">
                        {{ $pvSoutenance->decisionLibelle() }}
                    </span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Moyenne</div>
                <div>{{ $pvSoutenance->moyenne }}/20</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Mention</div>
                <div>{{ $pvSoutenance->mentionLibelle() }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Président du jury</div>
                <div>{{ $pvSoutenance->president_jury }}</div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Thème</div>
                <div>{{ $pvSoutenance->theme }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Directeur de mémoire</div>
                <div>{{ $pvSoutenance->directeur_memoire ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Rapporteur</div>
                <div>{{ $pvSoutenance->rapporteur ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Maître de stage</div>
                <div>{{ $pvSoutenance->maitre_stage ?: '—' }}</div>
            </div>
        </div>
    </div>
</div>

@php
    $peutModifier = \App\Support\ResourceAccess::can('pv_soutenances', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('pv_soutenances', 'delete');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-arrow-up"></i> Documents
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 40%;">Pièce</th>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fixes as $fichier)
                    <tr>
                        <td style="width: 40%;">{{ $fichier->libelle() }}</td>
                        <td>
                            @include('gestion.pv-soutenances._case', ['fichier' => $fichier, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'pvSoutenance' => $pvSoutenance])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Retrait PHYSIQUE du diplôme — demande explicite du client : pas
     seulement "retiré ou non", mais QUI est venu le chercher (l'étudiant
     lui-même, ou une autre personne — avec son nom) et À QUELLE DATE. Voir
     le docblock d'App\Models\PvSoutenance pour la distinction entre la date
     de retrait (saisie à la main) et la date d'enregistrement (automatique,
     "qui/quand dans l'application"). Rattaché au PV lui-même, pas au fichier
     déposé : uniquement le diplôme est concerné, jamais l'image du registre
     ni les champs supplémentaires. --}}
<div class="card shadow-sm mb-3">
    <div class="card-header header-accent d-flex align-items-center gap-2">
        <i class="bi bi-person-check"></i> Retrait du diplôme
    </div>
    <div class="card-body">
        @if ($pvSoutenance->estDiplomeRetire())
            <div class="row g-3 align-items-start">
                <div class="col-md-3">
                    <div class="text-muted small">Statut</div>
                    <div><span class="badge text-bg-success">Retiré</span></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Retiré par</div>
                    <div>{{ $pvSoutenance->diplomeRetirantLibelle() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Date de retrait</div>
                    <div>{{ $pvSoutenance->diplome_date_retrait?->format('d/m/Y') }}</div>
                </div>
                <div class="col-md-12">
                    <span class="text-muted small">
                        Enregistré par {{ $pvSoutenance->diplomeRetraitEnregistrePar?->name ?? '—' }}
                        le {{ $pvSoutenance->diplome_retrait_enregistre_le?->format('d/m/Y à H:i') }}
                    </span>
                </div>
            </div>
            @if ($peutModifier)
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#formRetraitDiplome">
                        <i class="bi bi-pencil"></i> Corriger
                    </button>
                    <form method="POST" action="{{ route('gestion.pv-soutenances.retrait-diplome.destroy', $pvSoutenance->id) }}"
                          onsubmit="return confirm('Annuler le retrait de ce diplôme ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-arrow-counterclockwise"></i> Annuler le retrait
                        </button>
                    </form>
                </div>
            @endif
        @else
            <span class="badge text-bg-warning">Non retiré</span>
            @if ($peutModifier)
                <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="collapse" data-bs-target="#formRetraitDiplome">
                    <i class="bi bi-check2-circle"></i> Enregistrer le retrait
                </button>
            @endif
        @endif

        @if ($peutModifier)
            <div class="collapse {{ $errors->has('diplome_retirant') || $errors->has('diplome_nom_retirant') || $errors->has('diplome_date_retrait') ? 'show' : '' }} mt-3" id="formRetraitDiplome">
                <form method="POST" action="{{ route('gestion.pv-soutenances.retrait-diplome.store', $pvSoutenance->id) }}" class="row g-3 border-top pt-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Qui est venu le chercher <span class="text-danger">*</span></label>
                        <select name="diplome_retirant" id="diplome_retirant" class="form-select" required>
                            <option value="">— Choisir —</option>
                            @foreach (\App\Models\PvSoutenance::RETIRANTS as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected(old('diplome_retirant', $pvSoutenance->diplome_retirant) === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4" id="groupe-nom-retirant">
                        <label class="form-label">Préciser (nom) <span class="text-danger">*</span></label>
                        <input type="text" name="diplome_nom_retirant" class="form-control" maxlength="150"
                               value="{{ old('diplome_nom_retirant', $pvSoutenance->diplome_nom_retirant) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de retrait <span class="text-danger">*</span></label>
                        <input type="date" name="diplome_date_retrait" class="form-control"
                               value="{{ old('diplome_date_retrait', $pvSoutenance->diplome_date_retrait?->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
            <script>
                (function () {
                    const select = document.getElementById('diplome_retirant');
                    const groupe = document.getElementById('groupe-nom-retirant');
                    if (!select || !groupe) return;

                    function actualiser() {
                        groupe.style.display = select.value === '{{ \App\Models\PvSoutenance::RETIRANT_AUTRE }}' ? '' : 'none';
                    }

                    select.addEventListener('change', actualiser);
                    actualiser();
                })();
            </script>
        @endif
    </div>
</div>

{{-- Champs ajoutés depuis Référentiels > Champs supplémentaires (voir
     App\Models\ChampSupplementaire) — n'apparaît que si au moins un champ
     s'applique à ce niveau. --}}
@if ($supplementaires->isNotEmpty())
    <div class="card shadow-sm mb-3">
        <div class="card-header header-success d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-plus"></i> Documents supplémentaires
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40%;">Pièce</th>
                        <th>Fichier</th>
                        <th style="width: 220px;">Verrouillage</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($supplementaires as $fichier)
                        <tr>
                            <td style="width: 40%;">{{ $fichier->libelle() }}</td>
                            <td>
                                @include('gestion.pv-soutenances._case', ['fichier' => $fichier, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'pvSoutenance' => $pvSoutenance])
                            </td>
                            <td>
                                @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
