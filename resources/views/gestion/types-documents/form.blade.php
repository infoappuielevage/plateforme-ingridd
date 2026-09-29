@extends('layouts.gestion')

@section('title', ($type ? 'Modifier — ' : 'Ajouter — ').'un type de document')

@section('content')
<h1 class="h3">Types de documents</h1>
<h2 class="h5">{{ $type ? 'Modifier' : 'Ajouter' }}</h2>

@if ($errors->any() && ! $errors->hasBag('sousType'))
    <div class="alert alert-danger" style="max-width: 780px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="card shadow-sm" style="max-width: 780px;">
<div class="card-body">
<form method="POST"
      action="{{ $type ? route('gestion.types-documents.update', $type->id) : route('gestion.types-documents.store') }}">
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
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label">Ordre d'affichage <span class="text-danger">*</span></label>
            <input type="number" name="ordre" class="form-control" min="0"
                   value="{{ old('ordre', $type->ordre ?? 0) }}" required>
        </div>

        <div class="col-12">
            <label class="form-label">Libellé <span class="text-danger">*</span></label>
            <input type="text" name="libelle" class="form-control" maxlength="150"
                   value="{{ old('libelle', $type->libelle ?? '') }}" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1"
                       @checked(old('actif', $type->actif ?? true))>
                <label class="form-check-label" for="actif">Actif</label>
            </div>
            <div class="form-text">Un type inactif n'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.</div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12">
            <h3 class="h6 mb-2">Comportement (modifiable sans toucher au code)</h3>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="epingle_secretariat" value="0">
                <input type="checkbox" name="epingle_secretariat" id="epingle_secretariat" class="form-check-input" value="1"
                       @checked(old('epingle_secretariat', $type->epingle_secretariat ?? false))>
                <label class="form-check-label" for="epingle_secretariat">Épingler dans le menu « Secrétariat »</label>
            </div>
            <div class="form-text">Ajoute une entrée dédiée à ce type dans le menu Secrétariat.</div>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="champ_semestre_actif" value="0">
                <input type="checkbox" name="champ_semestre_actif" id="champ_semestre_actif" class="form-check-input" value="1"
                       @checked(old('champ_semestre_actif', $type->champ_semestre_actif ?? false))>
                <label class="form-check-label" for="champ_semestre_actif">Champ « Semestre »</label>
            </div>
            <div class="form-text">Affiche sur le formulaire le champ Semestre (dépendant du niveau sélectionné).</div>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="champs_diplome_actifs" value="0">
                <input type="checkbox" name="champs_diplome_actifs" id="champs_diplome_actifs" class="form-check-input" value="1"
                       @checked(old('champs_diplome_actifs', $type->champs_diplome_actifs ?? false))>
                <label class="form-check-label" for="champs_diplome_actifs">Champs « Diplôme / Attestation »</label>
            </div>
            <div class="form-text">Titulaire, numéro, date de délivrance, mention, statut de retrait.</div>
        </div>

        <div class="col-md-6">
            <div class="form-check form-switch">
                <input type="hidden" name="champs_soutenance_actifs" value="0">
                <input type="checkbox" name="champs_soutenance_actifs" id="champs_soutenance_actifs" class="form-check-input" value="1"
                       @checked(old('champs_soutenance_actifs', $type->champs_soutenance_actifs ?? false))>
                <label class="form-check-label" for="champs_soutenance_actifs">Champs « PV de soutenance »</label>
            </div>
            <div class="form-text">Thème du mémoire, date de soutenance, membres du jury (en plus de titulaire/mention).</div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $type ? 'Enregistrer' : 'Ajouter' }}</button>
        <a href="{{ route('gestion.types-documents.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>

@if ($type)
    {{-- Sous-types : gérés directement ici (pas de page dédiée), comme l'ancien
         SousTypesRelationManager de Filament. Un seul formulaire modal, réutilisé
         pour l'ajout et la modification via un peu de JS (voir plus bas). --}}
    <div class="card shadow-sm mt-4" style="max-width: 780px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Sous-types</h3>
                @if (\App\Support\ResourceAccess::can('types_documents', 'create'))
                    <button type="button" class="btn btn-sm btn-primary" onclick="ouvrirModalSousType()">
                        <i class="bi bi-plus-lg"></i> Ajouter un sous-type
                    </button>
                @endif
            </div>

            @if ($errors->hasBag('sousType'))
                <div class="alert alert-danger">
                    @foreach ($errors->sousType->all() as $erreur)
                        <div>{{ $erreur }}</div>
                    @endforeach
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Ordre</th>
                            <th>Actif</th>
                            <th>Documents</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sousTypes as $sousType)
                            <tr>
                                <td>{{ $sousType->libelle }}</td>
                                <td class="text-muted small">{{ $sousType->ordre }}</td>
                                <td>
                                    @if ($sousType->actif)
                                        <span class="badge text-bg-success">Actif</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactif</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $sousType->documents_count }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if (\App\Support\ResourceAccess::can('types_documents', 'edit'))
                                            <button type="button" class="btn btn-outline-secondary" title="Modifier"
                                                    onclick="ouvrirModalSousType({{ $sousType->id }}, {{ \Illuminate\Support\Js::from($sousType->libelle) }}, {{ $sousType->ordre }}, {{ $sousType->actif ? 'true' : 'false' }})">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endif
                                        @if (\App\Support\ResourceAccess::can('types_documents', 'delete'))
                                            <form method="POST" action="{{ route('gestion.types-documents.sous-types.destroy', ['type' => $type->id, 'sousType' => $sousType->id]) }}" class="d-inline"
                                                  onsubmit="return confirm('Supprimer ce sous-type ? Les documents déjà classés dedans ne seront pas supprimés (leur sous-type redeviendra vide).');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state text-center py-4">
                                        <p class="text-muted mb-0">Aucun sous-type.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modale unique, partagée entre l'ajout et la modification --}}
    <div class="modal fade" id="modalSousType" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="formulaireSousType" action="{{ route('gestion.types-documents.sous-types.store', $type->id) }}">
                    @csrf
                    <div id="methodeSousType"></div>
                    {{-- Rejoué côté serveur (voir le script plus bas) pour savoir,
                         en cas d'échec de validation, s'il faut rouvrir la modale
                         en mode "ajout" ou "modification" (et de quel sous-type). --}}
                    <input type="hidden" name="sous_type_id_cible" id="idCibleSousType" value="{{ old('sous_type_id_cible', '') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="titreModalSousType">Ajouter un sous-type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Noms préfixés `sous_type_...` : voir le docblock de
                             TypeDocumentController::validerSousType() — évite que
                             les anciennes valeurs (old()) d'un sous-type en échec
                             de validation n'écrasent celles du type parent. --}}
                        <div class="mb-3">
                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="sous_type_libelle" id="libelleSousType" class="form-control" maxlength="150"
                                   value="{{ old('sous_type_libelle') }}" required>
                            @error('sous_type_libelle', 'sousType')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ordre d'affichage <span class="text-danger">*</span></label>
                            <input type="number" name="sous_type_ordre" id="ordreSousType" class="form-control" min="0"
                                   value="{{ old('sous_type_ordre', 0) }}" required>
                            @error('sous_type_ordre', 'sousType')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="sous_type_actif" value="0">
                            <input type="checkbox" name="sous_type_actif" id="actifSousType" class="form-check-input" value="1"
                                   @checked(old('sous_type_actif', true))>
                            <label class="form-check-label" for="actifSousType">Actif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modalSousTypeEl = document.getElementById('modalSousType');
        const modalSousType = new bootstrap.Modal(modalSousTypeEl);
        const urlAjoutSousType = @json(route('gestion.types-documents.sous-types.store', $type->id));

        function urlModificationSousType(id) {
            return urlAjoutSousType.replace(/\/sous-types$/, '/sous-types/' + id);
        }

        function ouvrirModalSousType(id, libelle, ordre, actif) {
            const formulaire = document.getElementById('formulaireSousType');
            const zoneMethode = document.getElementById('methodeSousType');

            document.getElementById('idCibleSousType').value = id ?? '';

            if (id) {
                document.getElementById('titreModalSousType').textContent = 'Modifier le sous-type';
                formulaire.action = urlModificationSousType(id);
                zoneMethode.innerHTML = '@method('PUT')';
                document.getElementById('libelleSousType').value = libelle ?? '';
                document.getElementById('ordreSousType').value = ordre ?? 0;
                document.getElementById('actifSousType').checked = !!actif;
            } else {
                document.getElementById('titreModalSousType').textContent = 'Ajouter un sous-type';
                formulaire.action = urlAjoutSousType;
                zoneMethode.innerHTML = '';
                formulaire.reset();
                document.getElementById('idCibleSousType').value = '';
                document.getElementById('actifSousType').checked = true;
            }

            modalSousType.show();
        }

        @if ($errors->hasBag('sousType'))
            {{-- Échec de validation sur la modale (ajout ou modification) : la
                 rouvrir automatiquement plutôt que de laisser l'utilisateur
                 deviner pourquoi rien ne s'est apparemment passé. Les champs
                 texte sont déjà repeuplés côté serveur via old() (voir plus
                 haut) ; il ne reste qu'à remettre le formulaire dans le bon
                 mode (ajout ou modification d'un sous-type précis), grâce au
                 champ caché `sous_type_id_cible` repeuplé lui aussi via old(). --}}
            document.addEventListener('DOMContentLoaded', function () {
                const idCible = @json(old('sous_type_id_cible', ''));

                if (idCible) {
                    document.getElementById('titreModalSousType').textContent = 'Modifier le sous-type';
                    document.getElementById('formulaireSousType').action = urlModificationSousType(idCible);
                    document.getElementById('methodeSousType').innerHTML = '@method('PUT')';
                } else {
                    document.getElementById('titreModalSousType').textContent = 'Ajouter un sous-type';
                    document.getElementById('formulaireSousType').action = urlAjoutSousType;
                }

                modalSousType.show();
            });
        @endif
    </script>
@endif
@endsection
