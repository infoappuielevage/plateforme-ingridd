@extends('layouts.gestion')

@section('title', 'Dossier étudiant')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $etudiant->nomComplet() }}</h1>
        <p class="text-muted small mb-0">Matricule {{ $etudiant->matricule }}</p>
    </div>
    <div class="d-flex gap-2">
        @if (\App\Support\ResourceAccess::can('etudiants', 'edit'))
            <a href="{{ route('gestion.etudiants.edit', $etudiant->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Modifier l'identité
            </a>
        @endif
        <a href="{{ route('gestion.etudiants.index') }}" class="btn btn-outline-secondary">
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

@php
    $peutModifier = \App\Support\ResourceAccess::can('etudiants', 'edit');
    $peutSupprimer = \App\Support\ResourceAccess::can('etudiants', 'delete');
    // Années déjà utilisées par une inscription de cet étudiant — exclues du
    // formulaire d'ajout ci-dessous (une seule inscription par année, voir
    // le docblock de la migration etudiant_inscriptions) : la validation
    // serveur les refuserait de toute façon, mais autant ne pas les proposer.
    $anneesDisponibles = $anneesAcademiques->whereNotIn('id', $inscriptions->pluck('annee_academique_id'));
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent"><i class="bi bi-calendar-check"></i> Inscriptions</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Année académique</th>
                    <th>Niveau</th>
                    <th>Filière</th>
                    @if ($peutModifier || $peutSupprimer)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($inscriptions as $inscription)
                    @include('gestion.etudiants._inscription', ['inscription' => $inscription, 'etudiant' => $etudiant, 'niveaux' => $niveaux, 'filieres' => $filieres, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer])
                @empty
                    <tr>
                        <td colspan="4" class="text-muted small py-3">Aucune inscription pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            @if ($anneesDisponibles->isNotEmpty())
                <form method="POST" action="{{ route('gestion.etudiants.inscriptions.store', $etudiant->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small">Année académique</label>
                        <select name="annee_academique_id" class="form-select form-select-sm" required>
                            <option value="">— Choisir —</option>
                            @foreach ($anneesDisponibles as $annee)
                                <option value="{{ $annee->id }}">{{ $annee->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Niveau</label>
                        <select name="niveau_id" class="form-select form-select-sm" required>
                            <option value="">— Choisir —</option>
                            @foreach ($niveaux as $niveau)
                                <option value="{{ $niveau->id }}">{{ $niveau->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Filière</label>
                        <select name="filiere_id" class="form-select form-select-sm" required>
                            <option value="">— Choisir —</option>
                            @foreach ($filieres as $filiere)
                                <option value="{{ $filiere->id }}">{{ $filiere->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Ajouter
                        </button>
                    </div>
                </form>
            @else
                <p class="text-muted small mb-0">Toutes les années académiques actives ont déjà une inscription pour cet étudiant.</p>
            @endif
        </div>
    @endif
</div>

@php
    /**
     * Une case = soit un lien de téléchargement + bouton "Retirer" si un
     * fichier est déjà déposé, soit un petit formulaire d'upload sinon —
     * voir gestion/etudiants/_fichier.blade.php (liste fixe pilotée par
     * Référentiels > Types de pièces, voir App\Models\TypePieceEtudiant).
     */
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent"><i class="bi bi-person-vcard"></i> Pièces d'identité</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 30%;">Pièce</th>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($piecesIdentite as $fichier)
                    <tr>
                        <td style="width: 30%;">{{ $fichier->libelle() }}</td>
                        <td>
                            @include('gestion.etudiants._fichier', ['fichier' => $fichier, 'etudiant' => $etudiant, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer])
                        </td>
                        <td>
                            @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-muted small py-3">Aucun type de pièce d'identité configuré — voir Référentiels &gt; Types de pièces (dossier étudiant).</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutModifier)
        <div class="card-footer bg-transparent">
            <div class="form-text mb-0">Taille maximale par fichier : {{ $tailleMaxMo }} Mo.</div>
        </div>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent"><i class="bi bi-calendar3"></i> Pièces par année académique</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 30%;">Pièce</th>
                    <th>Fichier</th>
                    <th style="width: 220px;">Verrouillage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inscriptions as $inscription)
                    @continue ($piecesParAnnee->get($inscription->id, collect())->isEmpty())
                    <tr class="table-light">
                        <td colspan="3">
                            <strong>{{ $inscription->anneeAcademique->libelle }}</strong>
                            <span class="text-muted">— {{ $inscription->niveau->libelle }} — {{ $inscription->filiere->libelle }}</span>
                        </td>
                    </tr>
                    @foreach ($piecesParAnnee->get($inscription->id) as $fichier)
                        <tr>
                            <td style="width: 30%; padding-left: 2rem;">{{ $fichier->libelle() }}</td>
                            <td>
                                @include('gestion.etudiants._fichier', ['fichier' => $fichier, 'etudiant' => $etudiant, 'peutModifier' => $peutModifier, 'peutSupprimer' => $peutSupprimer])
                            </td>
                            <td>
                                @include('gestion.partials._verrou_document', ['document' => $fichier->document])
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="3" class="text-muted small py-3">Aucune inscription pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
