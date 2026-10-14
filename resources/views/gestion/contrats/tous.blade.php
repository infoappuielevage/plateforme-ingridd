@extends('layouts.gestion')

@section('title', 'Gestion des contrats — Tous')

{{--
    "Tous les contrats" — vue d'ensemble toutes catégories confondues
    (demande explicite du client, "ajoute un autre tableau pour tout les
    contrat"), en plus des listes par catégorie (gestion/contrats/index.blade.php).
    Volontairement en LECTURE/NAVIGATION seule : pas de corbeille ni de
    sélection groupée ici — chaque ligne renvoie vers la fiche du contrat
    dans SA catégorie (bouton "Ouvrir"), où toutes les actions habituelles
    (modifier/mettre à la corbeille/restaurer/supprimer définitivement)
    restent disponibles. Voir le docblock de
    App\Http\Controllers\ContratController::tous() pour le détail de ce
    choix.
--}}
@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Gestion des contrats</h1>
        <p class="text-muted small mb-0">Tous les contrats, toutes catégories confondues.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('gestion.contrats.tous.export', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-excel"></i> Exporter
        </a>
        @if (\App\Support\ResourceAccess::can('contrats', 'create') && $categories->isNotEmpty())
            {{-- Pas de catégorie unique sur cette vue : "Nouveau contrat" ouvre
                 le formulaire pré-rempli sur la première catégorie active, mais
                 le menu déroulant "Type de contrat" du formulaire permet d'en
                 choisir une autre — voir gestion/contrats/form.blade.php. --}}
            <a href="{{ route('gestion.contrats.create', ['typeContrat' => $categories->first()->code]) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nouveau contrat
            </a>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

@include('gestion.contrats._onglets', ['categories' => $categories])

<div class="card shadow-sm p-3 mb-3">
    <form method="GET" action="{{ route('gestion.contrats.tous') }}" class="row g-2 align-items-end" id="formFiltresContratsTous">
        <div class="col-md-3">
            <label class="form-label small">Catégorie</label>
            <select name="categorie" class="form-select filtre-auto">
                <option value="">Toutes</option>
                @foreach ($categories as $categorie)
                    <option value="{{ $categorie->code }}" @selected(request('categorie') == $categorie->code)>{{ $categorie->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Statut</label>
            <select name="statut" class="form-select filtre-auto">
                <option value="">Tous</option>
                @foreach ($statuts as $cle => $libelle)
                    <option value="{{ $cle }}" @selected(request('statut') == $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Recherche (partenaire/prestataire, objet)</label>
            <input type="text" name="recherche" id="rechercheFiltreContratsTous" class="form-control" value="{{ request('recherche') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i> Filtrer</button>
            @if (request()->hasAny(['categorie', 'statut', 'recherche']))
                <a href="{{ route('gestion.contrats.tous') }}" class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('#formFiltresContratsTous .filtre-auto').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('formFiltresContratsTous').submit();
        });
    });

    var champRechercheContratsTous = document.getElementById('rechercheFiltreContratsTous');
    if (champRechercheContratsTous) {
        var delaiRechercheContratsTous;
        champRechercheContratsTous.addEventListener('input', function () {
            clearTimeout(delaiRechercheContratsTous);
            delaiRechercheContratsTous = setTimeout(function () {
                document.getElementById('formFiltresContratsTous').submit();
            }, 600);
        });
        if (champRechercheContratsTous.value) {
            champRechercheContratsTous.focus();
            var fin = champRechercheContratsTous.value.length;
            champRechercheContratsTous.setSelectionRange(fin, fin);
        }
    }
</script>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-th-triable cle="categorie" libelle="Catégorie" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="partenaire_prestataire" libelle="Partenaire / Prestataire" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="objet" libelle="Objet" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="montant" libelle="Montant" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="date_fin" libelle="Échéance" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="statut" libelle="Statut" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <x-th-triable cle="fichiers_deposes" libelle="Fichiers déposés" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.contrats.tous" />
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contrats as $item)
                    <tr>
                        <td>
                            @if ($item->typeContrat)
                                <span class="badge bg-palette-{{ $item->typeContrat->paletteIndex() }}">{{ $item->typeContrat->libelle }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>{{ $item->partenaire_prestataire }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($item->objet, 60) }}</td>
                        <td class="text-muted small">{{ $item->montantFormate() ?? '—' }}</td>
                        <td class="text-muted small">
                            {{ $item->date_fin?->format('d/m/Y') ?? ($item->date_debut ? 'Indéterminée' : '—') }}
                            @if ($item->echeanceDepassee())
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Échéance dépassée — statut encore « Actif »"></i>
                            @endif
                        </td>
                        <td><span class="badge {{ $item->statutBadgeClass() }}">{{ $item->statutLibelle() }}</span></td>
                        <td class="text-muted small">
                            {{ $item->fichiers->whereNotNull('document_id')->count() }} / {{ $item->fichiers->count() }}
                        </td>
                        <td class="text-end">
                            @if ($item->typeContrat)
                                <a href="{{ route('gestion.contrats.show', ['typeContrat' => $item->typeContrat->code, 'contrat' => $item->id]) }}" class="btn btn-sm btn-outline-secondary" title="Ouvrir">
                                    <i class="bi bi-folder2-open"></i> Ouvrir
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-vector-pen fs-1 text-muted"></i>
                                <p class="text-muted mb-0">Aucun contrat pour l'instant.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contrats->hasPages())
        <div class="card-footer bg-transparent">
            {{ $contrats->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
