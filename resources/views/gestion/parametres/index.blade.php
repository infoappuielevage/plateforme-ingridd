@extends('layouts.gestion')

@section('title', 'Paramètres')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Paramètres</h1>
</div>

@if ($errors->any())
    <div class="alert alert-danger" style="max-width: 980px;">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

{{--
    Demande explicite du client ("je ne veux pas que tout soit sur la même
    page, séparer la gestion avec des sous-menus") : chaque section a sa
    propre URL (gestion.parametres.index/{section}) et son propre
    formulaire/enregistrement (voir App\Http\Controllers\ParametresController
    ::SECTIONS et ::update()) — plutôt qu'un unique formulaire géant.

    La navigation entre sections se fait désormais directement depuis le
    sous-menu "Paramètres" du menu latéral principal (voir
    resources/views/layouts/gestion.blade.php) — demande explicite du client
    ("créer des sous-menu directement sur le menu") — plutôt que par la liste
    interne qui occupait auparavant cette colonne de gauche.
--}}
<div style="max-width: 760px;">
        @switch($section)
            @case('identite')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'identite']) }}" enctype="multipart/form-data">
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
                                    <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                                    <div class="form-text">Laissez vide pour garder le logo actuel. 1 Mo maximum (PNG, JPEG ou WebP).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('fichiers')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'fichiers']) }}">
                    @csrf
                    @method('PUT')

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

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('doctorat')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'doctorat']) }}">
                    @csrf
                    @method('PUT')

                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-transparent"><i class="bi bi-mortarboard"></i> Doctorat</div>
                        <div class="card-body">
                            <label class="form-label">Nombre d'années de thèse <span class="text-danger">*</span></label>
                            <div class="input-group" style="max-width: 260px;">
                                <input type="number" name="nombre_annees_these_doctorat" class="form-control" min="1" max="10"
                                       value="{{ old('nombre_annees_these_doctorat', $parametres->nombre_annees_these_doctorat ?? 3) }}" required>
                                <span class="input-group-text">années</span>
                            </div>
                            <div class="form-text">Détermine combien de cases "Année de thèse" sont créées à l'ouverture d'une session Doctorat — aussi bien pour PV de délibération que pour Relevés de notes.</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('historique')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'historique']) }}">
                    @csrf
                    @method('PUT')

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

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('corbeille')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'corbeille']) }}">
                    @csrf
                    @method('PUT')

                    <div class="card shadow-sm mb-3">
                        <div class="card-header header-danger d-flex align-items-center gap-2"><i class="bi bi-trash3-fill"></i> Corbeilles (purge automatique)</div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                Une fois purgé automatiquement, un élément de la corbeille (document, contrat,
                                réunion, étudiant...) est supprimé <strong>définitivement</strong> — exactement comme
                                le bouton « Vider la corbeille » sur chaque écran, mais déclenché chaque jour sans
                                intervention. Laissez le champ ci-dessous <strong>vide</strong> pour ne jamais purger
                                automatiquement — c'est le réglage par défaut, et la mise à la corbeille reste alors
                                sans limite de durée tant que personne ne vide la corbeille manuellement.
                            </div>
                            <label class="form-label">Purger automatiquement après</label>
                            <div class="input-group" style="max-width: 260px;">
                                <input type="number" name="purge_corbeille_jours" class="form-control" min="7"
                                       value="{{ old('purge_corbeille_jours', $parametres->purge_corbeille_jours) }}" placeholder="désactivée">
                                <span class="input-group-text">jours</span>
                            </div>
                            <div class="form-text">
                                Les éléments de la corbeille mis à la corbeille depuis plus longtemps que cette durée
                                sont supprimés définitivement chaque jour, catégorie par catégorie — un référentiel
                                (type de contrat, filière...) encore utilisé par un élément actif n'est jamais purgé,
                                même après l'échéance. Voir <a href="{{ route('gestion.corbeilles.index') }}">Corbeilles</a>
                                pour vider manuellement sans attendre.
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('verrouillage')
                {{--
                    Demande explicite du client ("améliore-la, de part ses
                    fonctionnalités et son design") :
                    - fonctionnalité : compteurs ci-dessous (voir
                      ParametresController::statistiquesVerrouillage()),
                      calculés côté serveur sans charger aucun document, pour
                      que l'administration voie d'un coup d'œil l'effet des
                      seuils AVANT de les modifier ;
                    - design : en-tête de carte coloré et tuiles statistiques
                      (même convention que resources/views/gestion/
                      dashboard.blade.php — voir public/css/style.css,
                      .stat-card/.stat-icon et .card-header.header-*).
                --}}
                <div class="row row-cols-2 row-cols-sm-4 g-2 mb-3">
                    <div class="col">
                        <div class="card stat-card h-100">
                            <div class="card-body text-center">
                                <span class="stat-icon mb-2"><i class="bi bi-files"></i></span>
                                <div class="fs-4 fw-bold text-primary">{{ $statistiquesVerrouillage['total'] }}</div>
                                <div class="small text-muted">Documents au total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card accent h-100">
                            <div class="card-body text-center">
                                <span class="stat-icon mb-2"><i class="bi bi-lock-fill"></i></span>
                                <div class="fs-4 fw-bold" style="color: var(--ingridd-accent-dark);">{{ $statistiquesVerrouillage['manuel'] }}</div>
                                <div class="small text-muted">Verrouillés manuellement</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card info h-100">
                            <div class="card-body text-center">
                                <span class="stat-icon mb-2"><i class="bi bi-calendar-x"></i></span>
                                <div class="fs-4 fw-bold" style="color: #0f766e;">{{ $statistiquesVerrouillage['automatique'] }}</div>
                                <div class="small text-muted">Verrouillés par les seuils</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card success h-100">
                            <div class="card-body text-center">
                                <span class="stat-icon mb-2"><i class="bi bi-unlock-fill"></i></span>
                                <div class="fs-4 fw-bold" style="color: #198754;">{{ $statistiquesVerrouillage['derogations'] }}</div>
                                <div class="small text-muted">Dérogations accordées</div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'verrouillage']) }}">
                    @csrf
                    @method('PUT')

                    <div class="card shadow-sm mb-3">
                        <div class="card-header header-primary d-flex align-items-center gap-2"><i class="bi bi-lock"></i> Verrouillage des documents</div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Une fois verrouillé, un document ne peut plus être remplacé ni supprimé — il doit d'abord
                                être déverrouillé explicitement depuis la colonne « Verrouillage » de son tableau (action
                                journalisée dans l'historique). La consultation/le téléchargement restent toujours
                                possibles. Qui peut verrouiller/déverrouiller se règle dans
                                <a href="{{ route('gestion.roles.index') }}">Administration &gt; Rôles &amp; permissions</a>
                                (droit « Verrouillage des documents »).
                            </p>
                            <p class="text-muted small mb-4">
                                Les seuils ci-dessous verrouillent AUTOMATIQUEMENT les documents concernés et sont
                                indépendants l'un de l'autre : laissez un champ vide pour désactiver cette règle-là
                                seulement. Un document peut aussi être verrouillé MANUELLEMENT, au cas par cas, depuis le
                                bouton <i class="bi bi-lock"></i> de la colonne « Verrouillage » — même en dehors de ces
                                seuils.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Verrouiller jusqu'à l'année académique</label>
                                    @php $seuilAnnee = old('verrouillage_annee_academique_id', $parametres->verrouillage_annee_academique_id); @endphp
                                    <select name="verrouillage_annee_academique_id" class="form-select">
                                        <option value="">— Aucune —</option>
                                        @foreach ($anneesAcademiques as $annee)
                                            <option value="{{ $annee->id }}" @selected((string) $seuilAnnee === (string) $annee->id)>{{ $annee->libelle }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Verrouille tout document rattaché à cette année académique ou à une année antérieure.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Verrouiller avant le</label>
                                    <input type="date" name="verrouillage_date" class="form-control"
                                           value="{{ old('verrouillage_date', $parametres->verrouillage_date?->format('Y-m-d')) }}">
                                    <div class="form-text">Verrouille tout document déposé avant cette date, même sans année académique renseignée.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break

            @case('sauvegardes')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'sauvegardes']) }}">
                    @csrf
                    @method('PUT')

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
                                        <option value="annuelle" @selected($frequenceActuelle === 'annuelle')>Annuelle (le 1er janvier)</option>
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
            @break

            @case('mail')
                <form method="POST" action="{{ route('gestion.parametres.update', ['section' => 'mail']) }}">
                    @csrf
                    @method('PUT')

                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-transparent"><i class="bi bi-envelope"></i> Configuration mail (SMTP)</div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Remplace les réglages du fichier <code>.env</code> (pratique pour renouveler un mot de passe
                                d'application Gmail, ou changer d'hébergeur, sans toucher au serveur). Laissez "Hôte SMTP" vide
                                pour continuer à utiliser la configuration du fichier <code>.env</code>.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Hôte SMTP</label>
                                    <input type="text" name="mail_host" class="form-control" maxlength="255"
                                           value="{{ old('mail_host', $parametres->mail_host) }}" placeholder="ex. smtp.gmail.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="mail_port" class="form-control" min="1" max="65535"
                                           value="{{ old('mail_port', $parametres->mail_port) }}" placeholder="587">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom d'utilisateur</label>
                                    <input type="text" name="mail_username" class="form-control" maxlength="255"
                                           value="{{ old('mail_username', $parametres->mail_username) }}" placeholder="ex. votre.adresse@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mot de passe</label>
                                    <x-champ-mot-de-passe name="mail_password" maxlength="255"
                                           autocomplete="new-password" placeholder="{{ $parametres->mail_password ? '••••••••' : '' }}" />
                                    <div class="form-text">
                                        Laissez vide pour garder le mot de passe déjà enregistré.
                                        Pour Gmail : utilisez un "mot de passe d'application" (compte Google > Sécurité >
                                        Validation en deux étapes > Mots de passe des applications), pas le mot de passe de
                                        connexion habituel — Google le refuse pour l'envoi automatisé dès que la validation en
                                        deux étapes est activée.
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Chiffrement</label>
                                    @php $chiffrementActuel = old('mail_encryption', $parametres->mail_encryption); @endphp
                                    <select name="mail_encryption" class="form-select">
                                        <option value="" @selected(! $chiffrementActuel)>Aucun</option>
                                        <option value="tls" @selected($chiffrementActuel === 'tls')>TLS</option>
                                        <option value="ssl" @selected($chiffrementActuel === 'ssl')>SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Adresse d'expédition</label>
                                    <input type="email" name="mail_from_address" class="form-control" maxlength="255"
                                           value="{{ old('mail_from_address', $parametres->mail_from_address) }}" placeholder="ex. votre.adresse@gmail.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nom d'expédition</label>
                                    <input type="text" name="mail_from_name" class="form-control" maxlength="150"
                                           value="{{ old('mail_from_name', $parametres->mail_from_name) }}" placeholder="ex. INGRIDD Gestion Archives">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            @break
        @endswitch
</div>
@endsection
