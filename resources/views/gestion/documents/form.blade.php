@extends('layouts.gestion')

@section('title', $document ? 'Modifier le document' : 'Ajouter un document')

@section('content')
<h1 class="h3">Gestion des documents</h1>
<h2 class="h5">{{ $document ? 'Modifier le document' : 'Ajouter un document' }}</h2>

<div class="card shadow-sm" style="max-width: 820px;">
<div class="card-body">
<form method="POST"
      action="{{ $document ? route('gestion.documents.update', $document) : route('gestion.documents.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($document) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Type de document <span class="text-danger">*</span></label>
            <select name="type_document_id" id="type_document_id" class="form-select" required>
                <option value="">— Choisir —</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected(old('type_document_id', $document->type_document_id ?? $typePreselectionneId ?? '') == $type->id)>
                        {{ $type->libelle }}{{ ! $type->actif ? ' (désactivé)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6" id="groupe-sous-type">
            <label class="form-label">Sous-type</label>
            <select name="sous_type_document_id" id="sous_type_document_id" class="form-select"></select>
            <div class="form-text">N'apparaît que si le type sélectionné a des sous-types configurés.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Filière</label>
            <select name="filiere_id" class="form-select">
                <option value="">— Aucune —</option>
                @foreach ($filieres as $filiere)
                    <option value="{{ $filiere->id }}" @selected(old('filiere_id', $document->filiere_id ?? '') == $filiere->id)>
                        {{ $filiere->libelle }}{{ ! $filiere->actif ? ' (désactivée)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Niveau</label>
            <select name="niveau_id" id="niveau_id" class="form-select">
                <option value="">— Aucun —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected(old('niveau_id', $document->niveau_id ?? '') == $niveau->id)>
                        {{ $niveau->libelle }}{{ ! $niveau->actif ? ' (désactivé)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Année académique</label>
            <select name="annee_academique_id" class="form-select">
                <option value="">— Aucune —</option>
                @foreach ($anneesAcademiques as $annee)
                    <option value="{{ $annee->id }}" @selected(old('annee_academique_id', $document->annee_academique_id ?? '') == $annee->id)>
                        {{ $annee->libelle }}{{ ! $annee->actif ? ' (désactivée)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4" id="groupe-semestre">
            <label class="form-label">Semestre</label>
            <select name="semestre" id="semestre" class="form-select" disabled></select>
            <div class="form-text">Sélectionnez d'abord un niveau.</div>
        </div>

        <div class="col-12" id="groupe-titulaire-mention">
            <hr>
        </div>

        <div class="col-md-6" id="groupe-titulaire" style="display:none;">
            <label class="form-label">Nom du titulaire / de l'étudiant</label>
            <input type="text" name="nom_titulaire" id="nom_titulaire" class="form-control" maxlength="255"
                   value="{{ old('nom_titulaire', $document->nom_titulaire ?? '') }}">
        </div>

        <div class="col-md-6" id="groupe-mention" style="display:none;">
            <label class="form-label">Mention</label>
            <input type="text" name="mention" class="form-control" maxlength="100" placeholder="Ex. Bien, Très bien..."
                   value="{{ old('mention', $document->mention ?? '') }}">
        </div>

        <div class="col-md-6" id="groupe-numero-diplome" style="display:none;">
            <label class="form-label">Numéro de diplôme / d'enregistrement</label>
            <input type="text" name="numero_diplome" class="form-control" maxlength="100"
                   value="{{ old('numero_diplome', $document->numero_diplome ?? '') }}">
        </div>

        <div class="col-md-6" id="groupe-date-delivrance" style="display:none;">
            <label class="form-label">Date de délivrance</label>
            <input type="date" name="date_delivrance" class="form-control"
                   value="{{ old('date_delivrance', optional($document->date_delivrance ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-6" id="groupe-statut-retrait" style="display:none;">
            <label class="form-label">Statut de retrait</label>
            <select name="statut_retrait" id="statut_retrait" class="form-select">
                <option value="non_retire" @selected(old('statut_retrait', $document->statut_retrait ?? 'non_retire') === 'non_retire')>Non retiré</option>
                <option value="retire" @selected(old('statut_retrait', $document->statut_retrait ?? '') === 'retire')>Retiré</option>
            </select>
        </div>

        <div class="col-md-6" id="groupe-date-retrait" style="display:none;">
            <label class="form-label">Date de retrait</label>
            <input type="date" name="date_retrait" id="date_retrait" class="form-control"
                   value="{{ old('date_retrait', optional($document->date_retrait ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-6" id="groupe-theme-memoire" style="display:none;">
            <label class="form-label">Thème du mémoire</label>
            <input type="text" name="theme_memoire" class="form-control" maxlength="255"
                   value="{{ old('theme_memoire', $document->theme_memoire ?? '') }}">
        </div>

        <div class="col-md-6" id="groupe-date-soutenance" style="display:none;">
            <label class="form-label">Date de soutenance</label>
            <input type="date" name="date_soutenance" class="form-control"
                   value="{{ old('date_soutenance', optional($document->date_soutenance ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-12" id="groupe-membres-jury" style="display:none;">
            <label class="form-label">Membres du jury</label>
            <textarea name="membres_jury" class="form-control" rows="3" placeholder="Un nom par ligne, avec le rôle si besoin (ex. Président, Rapporteur, Membre).">{{ old('membres_jury', $document->membres_jury ?? '') }}</textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description', $document->description ?? '') }}</textarea>
        </div>

        <div class="col-12">
            <label class="form-label">
                Fichier {{ $document ? '(laisser vide pour conserver le fichier actuel)' : '' }}
                @if (! $document) <span class="text-danger">*</span> @endif
            </label>
            <input type="file" name="fichier" class="form-control" {{ $document ? '' : 'required' }}>
            @if ($document)
                <div class="form-text">Fichier actuel : {{ $document->nom_original }} ({{ $document->tailleFormatee() }}).</div>
            @endif
            <div class="form-text">{{ $tailleMaxMo }} Mo maximum.</div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $document ? 'Enregistrer' : 'Ajouter le document' }}</button>
        <a href="{{ route('gestion.documents.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>
</div>

@push('scripts')
<script>
    const sousTypesParType = @json($sousTypes->map(fn ($groupe) => $groupe->map(fn ($st) => ['id' => $st->id, 'libelle' => $st->libelle])->values()));
    const semestresParNiveau = @json($semestresParNiveau);
    const typeDiplomeAttestationIds = @json(array_map('intval', $typeDiplomeAttestationIds));
    const typePvSoutenanceIds = @json(array_map('intval', $typePvSoutenanceIds));
    const typesAvecSemestreIds = @json(array_map('intval', $typesAvecSemestreIds));
    const sousTypeInitial = @json(old('sous_type_document_id', $document->sous_type_document_id ?? null));
    const semestreInitial = @json(old('semestre', $document->semestre ?? null));

    const typeSelect = document.getElementById('type_document_id');
    const niveauSelect = document.getElementById('niveau_id');

    function peuplerSousTypes(typeId, valeurAConserver) {
        const select = document.getElementById('sous_type_document_id');
        const options = sousTypesParType[typeId] || [];
        select.innerHTML = '<option value="">— Aucun —</option>';
        options.forEach(function (o) {
            const opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.libelle;
            if (valeurAConserver && String(valeurAConserver) === String(o.id)) opt.selected = true;
            select.appendChild(opt);
        });
        document.getElementById('groupe-sous-type').style.display = options.length ? '' : 'none';
    }

    function peuplerSemestres(niveauId, valeurAConserver) {
        const select = document.getElementById('semestre');
        const options = semestresParNiveau[niveauId] || {};
        const entrees = Object.entries(options);
        select.innerHTML = '';
        entrees.forEach(function ([valeur, libelle]) {
            const opt = document.createElement('option');
            opt.value = valeur;
            opt.textContent = libelle;
            if (valeurAConserver && String(valeurAConserver) === String(valeur)) opt.selected = true;
            select.appendChild(opt);
        });
        select.disabled = entrees.length === 0;
    }

    function afficher(id, visible) {
        document.getElementById(id).style.display = visible ? '' : 'none';
    }

    function actualiserAffichage() {
        const typeId = parseInt(typeSelect.value, 10) || null;
        const estDiplomeAttestation = typeDiplomeAttestationIds.includes(typeId);
        const estPv = typePvSoutenanceIds.includes(typeId);
        const aTitulaire = estDiplomeAttestation || estPv;
        const aSemestre = typesAvecSemestreIds.includes(typeId);

        afficher('groupe-titulaire', aTitulaire);
        afficher('groupe-mention', aTitulaire);
        document.getElementById('nom_titulaire').required = aTitulaire;

        afficher('groupe-numero-diplome', estDiplomeAttestation);
        afficher('groupe-date-delivrance', estDiplomeAttestation);
        afficher('groupe-statut-retrait', estDiplomeAttestation);
        afficher('groupe-date-retrait', estDiplomeAttestation);

        afficher('groupe-theme-memoire', estPv);
        afficher('groupe-date-soutenance', estPv);
        afficher('groupe-membres-jury', estPv);

        afficher('groupe-semestre', aSemestre);
    }

    typeSelect.addEventListener('change', function () {
        peuplerSousTypes(parseInt(this.value, 10) || null, null);
        actualiserAffichage();
    });

    niveauSelect.addEventListener('change', function () {
        peuplerSemestres(parseInt(this.value, 10) || null, null);
    });

    document.getElementById('statut_retrait')?.addEventListener('change', function () {
        if (this.value === 'retire') {
            const dateRetrait = document.getElementById('date_retrait');
            if (!dateRetrait.value) dateRetrait.value = new Date().toISOString().slice(0, 10);
        }
    });

    // État initial (chargement de la page / modification) : on CONSERVE les
    // valeurs déjà enregistrées (sous-type, semestre) plutôt que de les
    // réinitialiser comme le ferait un changement volontaire de type/niveau.
    peuplerSousTypes(parseInt(typeSelect.value, 10) || null, sousTypeInitial);
    peuplerSemestres(parseInt(niveauSelect.value, 10) || null, semestreInitial);
    actualiserAffichage();
</script>
@endpush
@endsection
