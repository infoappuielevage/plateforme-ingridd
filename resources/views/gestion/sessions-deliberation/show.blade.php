@extends('layouts.gestion')

@section('title', 'Session de délibération')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $session->anneeAcademique->libelle }} — {{ $session->sessionLibelle() }}</h1>
        <p class="text-muted small mb-0 d-flex align-items-center gap-2">
            <span class="badge {{ $session->cycleBadgeClasse() }}">{{ $session->cycleLibelle() }}</span>
            {{ $session->filiere->libelle }}
        </p>
    </div>
    <a href="{{ route('gestion.sessions-deliberation.index') }}" class="btn btn-outline-secondary">
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

<div class="card shadow-sm mb-3">
    <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-card-text"></i> Description</div>
    <div class="card-body">
        @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'edit'))
            <form method="POST" action="{{ route('gestion.sessions-deliberation.update', $session->id) }}" class="d-flex flex-column gap-2">
                @csrf
                @method('PUT')
                <textarea name="description" class="form-control" rows="2" maxlength="2000">{{ old('description', $session->description) }}</textarea>
                <div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Enregistrer la description</button>
                </div>
            </form>
        @else
            <p class="mb-0">{{ $session->description ?: '—' }}</p>
        @endif
    </div>
</div>

@php
    /**
     * Une case = soit un lien de téléchargement + bouton "Retirer" si un
     * fichier est déjà déposé, soit un petit formulaire d'upload sinon — le
     * même partiel sert pour les deux notes d'information et pour chaque
     * case numérotée, voir la boucle ci-dessous.
     */
    $peutModifier = \App\Support\ResourceAccess::can('sessions_deliberation', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('sessions_deliberation', 'delete');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-info d-flex align-items-center gap-2"><i class="bi bi-info-circle"></i> Notes d'information</div>
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
                @foreach ($notes as $fichier)
                    <tr>
                        <td style="width: 40%;">{{ $fichier->libelle() }}</td>
                        <td>
                            @include('gestion.sessions-deliberation._case', ['fichier' => $fichier, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'session' => $session])
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

{{-- Champs ajoutés depuis Référentiels > Champs supplémentaires (voir
     App\Models\ChampSupplementaire) — n'apparaît que si au moins un champ
     s'applique à cette session (PV coché + ce cycle). --}}
@if ($supplementaires->isNotEmpty())
    <div class="card shadow-sm mb-3">
        <div class="card-header header-accent d-flex align-items-center gap-2"><i class="bi bi-file-earmark-plus"></i> Documents supplémentaires</div>
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
                                @include('gestion.sessions-deliberation._case', ['fichier' => $fichier, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'session' => $session])
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

@php
    /**
     * Regroupées par "Licence 1"/"Licence 2"/"Licence 3" (ou "Master 1"/
     * "Master 2") — demande explicite du client : une simple liste plate
     * "Semestre 1 à 6" ne montrait pas clairement que Semestre 1 et 2
     * appartiennent tous les deux à Licence 1, etc. Voir
     * SessionDeliberation::groupePourNumero(). groupBy() préserve l'ordre
     * de première rencontre : comme $numerotees est déjà trié par numéro
     * croissant, les groupes ressortent naturellement dans l'ordre
     * Licence 1, 2, 3 (ou Master 1, 2). Pour le Doctorat, groupePourNumero()
     * renvoie toujours null : un seul groupe sans en-tête, chaque "Année de
     * thèse N" étant déjà son propre regroupement.
     */
    $groupesNumerotees = $numerotees->groupBy(fn ($f) => $session->groupePourNumero($f->numero ?? 0) ?? '');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header header-success d-flex align-items-center gap-2">
        <i class="bi bi-files"></i>
        {{ $session->cycle === \App\Models\SessionDeliberation::CYCLE_DOCTORAT ? 'PV par année de thèse' : 'PV par semestre' }}
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
                @forelse ($groupesNumerotees as $groupeLibelle => $items)
                    @if ($groupeLibelle !== '')
                        <tr class="table-light">
                            <td colspan="3"><strong>{{ $groupeLibelle }}</strong></td>
                        </tr>
                    @endif
                    @foreach ($items as $fichier)
                        <tr>
                            <td style="width: 40%; padding-left: {{ $groupeLibelle !== '' ? '2rem' : '1rem' }};">{{ $fichier->libelle() }}</td>
                            <td>
                                @include('gestion.sessions-deliberation._case', ['fichier' => $fichier, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer, 'session' => $session])
                            </td>
                            <td>
                                @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="3" class="text-muted small py-3">Aucune case prévue pour ce cycle.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
