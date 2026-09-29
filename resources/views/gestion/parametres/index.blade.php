@extends('layouts.gestion')

@section('title', 'Paramètres')

@section('content')
<h1 class="h3 mb-3">Paramètres</h1>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 780px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('gestion.parametres.update') }}" enctype="multipart/form-data" style="max-width: 780px;">
    @csrf
    @method('PUT')

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-person-vcard"></i> Identité de l'application</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nom affiché <span class="text-danger">*</span></label>
                    <input type="text" name="nom_application" class="form-control" maxlength="150"
                           value="{{ old('nom_application', $parametres->nom_application) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Couleur principale <span class="text-danger">*</span></label>
                    <input type="color" name="couleur_principale" class="form-control form-control-color w-100"
                           value="{{ old('couleur_principale', $parametres->couleur_principale) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Logo</label>
                    @if ($parametres->logo)
                        <div class="mb-2">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($parametres->logo) }}" alt="Logo actuel" style="max-height: 60px;">
                        </div>
                    @endif
                    <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="form-text">Laissez vide pour garder le logo actuel. 1 Mo maximum (PNG, JPEG, WebP ou SVG).</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-file-earmark-check"></i> Types de fichiers acceptés</div>
        <div class="card-body">
            <p class="text-muted small">Familles de formats autorisées à l'ajout d'un document. Décochez une famille pour la refuser sans toucher au code.</p>
            <div class="row g-2">
                @php $famillesActuelles = old('types_fichiers_autorises', $parametres->typesFichiersAutorisesEffectifs()); @endphp
                @foreach ($familles as $cle => $libelle)
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="types_fichiers_autorises[]" id="famille_{{ $cle }}" class="form-check-input" value="{{ $cle }}"
                                   @checked(in_array($cle, $famillesActuelles, true))>
                            <label class="form-check-label" for="famille_{{ $cle }}">{{ $libelle }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Au moins une famille doit rester cochée.</div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-cloud-arrow-up"></i> Limites d'upload</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Taille maximale d'un document <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="taille_max_document_mo" class="form-control" min="1" max="500"
                               value="{{ old('taille_max_document_mo', $parametres->taille_max_document_mo) }}" required>
                        <span class="input-group-text">Mo</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Taille maximale d'une photo de profil <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="taille_max_avatar_mo" class="form-control" min="1" max="20"
                               value="{{ old('taille_max_avatar_mo', $parametres->taille_max_avatar_mo) }}" required>
                        <span class="input-group-text">Mo</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-clock-history"></i> Historique (audit)</div>
        <div class="card-body">
            <label class="form-label">Purge automatique après <span class="text-danger">*</span></label>
            <div class="input-group" style="max-width: 260px;">
                <input type="number" name="retention_audit_jours" class="form-control" min="30"
                       value="{{ old('retention_audit_jours', $parametres->retention_audit_jours) }}" required>
                <span class="input-group-text">jours</span>
            </div>
            <div class="form-text">Les entrées de l'historique plus anciennes que cette durée sont supprimées automatiquement chaque jour.</div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-transparent"><i class="bi bi-archive"></i> Sauvegardes</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fréquence de la sauvegarde automatique <span class="text-danger">*</span></label>
                    @php $frequenceActuelle = old('frequence_sauvegarde_auto', $parametres->frequence_sauvegarde_auto); @endphp
                    <select name="frequence_sauvegarde_auto" class="form-select" required>
                        <option value="quotidienne" @selected($frequenceActuelle === 'quotidienne')>Quotidienne</option>
                        <option value="hebdomadaire" @selected($frequenceActuelle === 'hebdomadaire')>Hebdomadaire (chaque lundi)</option>
                        <option value="mensuelle" @selected($frequenceActuelle === 'mensuelle')>Mensuelle (le 1er du mois)</option>
                    </select>
                    <div class="form-text">Nécessite que le planificateur Laravel tourne réellement sur le serveur — sinon, seule la sauvegarde manuelle fonctionne.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombre de sauvegardes à conserver <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="retention_sauvegardes_nombre" class="form-control" min="1"
                               value="{{ old('retention_sauvegardes_nombre', $parametres->retention_sauvegardes_nombre) }}" required>
                        <span class="input-group-text">sauvegardes</span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Chemin de l'exécutable mysqldump</label>
                    <input type="text" name="mysqldump_chemin" class="form-control" maxlength="255"
                           value="{{ old('mysqldump_chemin', $parametres->mysqldump_chemin) }}"
                           placeholder="ex. C:\laragon\bin\mysql\mysql-8.x\bin\mysqldump.exe">
                    <div class="form-text">Laissez vide pour utiliser "mysqldump" depuis le PATH système. À renseigner uniquement si la sauvegarde échoue avec un message "mysqldump introuvable". Sans effet en SQLite.</div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
@endsection
