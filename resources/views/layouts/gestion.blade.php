{{--
    Layout principal (sidebar + topbar) de l'espace de gestion — remplace le
    panneau Filament, sur demande explicite du client ("je ne veux plus
    utiliser Filament, trop complexe à modifier"), par du Blade + Bootstrap
    classique, plus simple à reprendre soi-même.

    Repris à l'identique (mêmes classes CSS, même style.css — voir
    public/css/style.css, copié tel quel) du projet "bibliothèque numérique"
    du client (ingridd-biblio), qu'il a désigné comme référence visuelle :
    même sidebar bleu marine/orange, même topbar, mêmes cartes statistiques.
    Seuls les liens du menu changent, pour refléter les écrans propres à
    cette plateforme (documents d'archives, pas un catalogue de bibliothèque).

    La visibilité de chaque lien suit exactement les mêmes droits que
    l'ancien panneau Filament (voir App\Support\ResourceAccess) : rien ne
    change côté permissions, seul l'habillage change.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tableau de bord') - {{ $parametresGlobaux->nom_application }}</title>
    <script>
        (function () {
            const theme = localStorage.getItem('ingridd-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @stack('styles')
</head>
<body>

@php
    $utilisateurConnecte = auth()->user();

    // Cloche de notifications (voir App\Http\Controllers\NotificationController
    // et resources/views/gestion/notifications/index.blade.php) — demande
    // explicite du client. Les 8 plus récentes seulement ici (même limite
    // que "Documents récemment ajoutés" sur le tableau de bord) : la liste
    // complète, paginée, reste à "Voir toutes mes notifications" plus bas
    // dans le menu déroulant.
    $notificationsRecentes = $utilisateurConnecte->notifications()->latest()->limit(8)->get();
    $nombreNotificationsNonLues = $utilisateurConnecte->unreadNotifications()->count();

    // Menu "Secrétariat" : une entrée par type de document "épinglé" (voir
    // App\Models\TypeDocument::$epingle_secretariat) — remplace l'ancien
    // menu Filament construit dans AdminPanelProvider, désormais piloté
    // entièrement par ce référentiel plutôt que par du code. Recalculé à
    // chaque requête (pas de cache), même choix que l'ancienne version : ce
    // menu change rarement, et un administrateur qui épingle un nouveau
    // type doit le voir apparaître immédiatement, sans redéploiement.
    //
    // Un type qui a des sous-types actifs ET les champs "Diplôme" (Attestation,
    // aujourd'hui) reçoit une entrée PAR sous-type plutôt qu'une entrée
    // globale — mêmes raisons que l'ancien menu Filament ("il y'a plusieurs
    // types d'attestation").
    $elementsSecretariat = collect();

    if (\App\Support\ResourceAccess::can('documents', 'view')) {
        // `actif` en plus de `epingle_secretariat` (même filtre que
        // App\Support\MenuDocuments pour le sous-menu "Types de documents")
        // : désactiver un type depuis Administration > Référentiels > Types
        // de documents doit aussi faire disparaître son raccourci
        // Secrétariat, pas seulement l'empêcher d'apparaître pour un NOUVEAU
        // document — sinon le raccourci resterait affiché pour un type que
        // le client a justement dit ne plus vouloir voir "pour le moment".
        foreach (\App\Models\TypeDocument::query()->where('epingle_secretariat', true)->where('actif', true)->orderBy('ordre')->get() as $typeEpingle) {
            $sousTypesActifs = $typeEpingle->champs_diplome_actifs
                ? $typeEpingle->sousTypes()->where('actif', true)->get()
                : collect();

            if ($sousTypesActifs->isNotEmpty()) {
                foreach ($sousTypesActifs as $sousType) {
                    $elementsSecretariat->push([
                        'libelle' => "{$typeEpingle->libelle} — {$sousType->libelle}",
                        'url' => route('gestion.secretariat', ['code' => $typeEpingle->code, 'sousType' => $sousType->id]),
                        'actif' => request()->routeIs('gestion.secretariat')
                            && request()->route('code') === $typeEpingle->code
                            && (int) request()->route('sousType') === $sousType->id,
                    ]);
                }

                continue;
            }

            $elementsSecretariat->push([
                'libelle' => $typeEpingle->libelle,
                'url' => route('gestion.secretariat', ['code' => $typeEpingle->code]),
                'actif' => request()->routeIs('gestion.secretariat')
                    && request()->route('code') === $typeEpingle->code
                    && request()->route('sousType') === null,
            ]);
        }
    }

    // Référentiels : "Types de documents" a son propre contrôleur/ses propres
    // routes (voir App\Http\Controllers\TypeDocumentController), les trois
    // autres partagent App\Http\Controllers\ReferentielController — d'où le
    // slug de route ("referentiel") parfois différent de la clé
    // ResourceAccess ("annees-academiques" vs "annees_academiques").
    // Sous-menu "Types de documents" (sous "Documents" ci-dessous) : voir
    // App\Support\MenuDocuments pour la construction complète (regroupements
    // visuels type "PV", seau "Autres types"...). Différent du menu
    // "Secrétariat" ci-dessus (qui ne montre que les types épinglés, et ne
    // déplie que ceux ayant les champs "Diplôme" actifs) : ici, TOUS les
    // types actifs apparaissent — deux usages différents (accès rapide
    // "guichet" vs navigation complète du référentiel), pas une duplication.
    $menuTypesDocuments = \App\Support\ResourceAccess::can('documents', 'view')
        ? \App\Support\MenuDocuments::elements()
        : collect();

    $elementsReferentiels = collect([
        ['cle' => 'types_documents', 'icone' => 'bi-tags-fill', 'label' => 'Types de documents', 'url' => route('gestion.types-documents.index'), 'actif' => request()->routeIs('gestion.types-documents.*')],
        ['cle' => 'filieres', 'icone' => 'bi-diagram-3-fill', 'label' => 'Filières', 'url' => route('gestion.referentiels.index', ['referentiel' => 'filieres']), 'actif' => request()->routeIs('gestion.referentiels.*') && request()->route('referentiel') === 'filieres'],
        ['cle' => 'niveaux', 'icone' => 'bi-bar-chart-steps', 'label' => 'Niveaux', 'url' => route('gestion.referentiels.index', ['referentiel' => 'niveaux']), 'actif' => request()->routeIs('gestion.referentiels.*') && request()->route('referentiel') === 'niveaux'],
        ['cle' => 'annees_academiques', 'icone' => 'bi-calendar-range-fill', 'label' => 'Années académiques', 'url' => route('gestion.referentiels.index', ['referentiel' => 'annees-academiques']), 'actif' => request()->routeIs('gestion.referentiels.*') && request()->route('referentiel') === 'annees-academiques'],
        ['cle' => 'champs_supplementaires', 'icone' => 'bi-file-earmark-plus-fill', 'label' => 'Champs supplémentaires', 'url' => route('gestion.champs-supplementaires.index'), 'actif' => request()->routeIs('gestion.champs-supplementaires.*')],
        ['cle' => 'types_pieces_etudiants', 'icone' => 'bi-folder2-open', 'label' => 'Types de pièces (étudiant)', 'url' => route('gestion.types-pieces-etudiants.index'), 'actif' => request()->routeIs('gestion.types-pieces-etudiants.*')],
        ['cle' => 'types_depenses', 'icone' => 'bi-credit-card-2-front-fill', 'label' => 'Catégories de dépenses', 'url' => route('gestion.types-depenses.index'), 'actif' => request()->routeIs('gestion.types-depenses.*')],
        ['cle' => 'types_contrats', 'icone' => 'bi-vector-pen', 'label' => 'Catégories de contrats', 'url' => route('gestion.types-contrats.index'), 'actif' => request()->routeIs('gestion.types-contrats.*')],
    ])->filter(fn (array $ref) => \App\Support\ResourceAccess::can($ref['cle'], 'view'));

    // Lien direct de la sidebar vers "Gestion des dépenses" (voir plus bas) :
    // mène directement à la première catégorie active plutôt qu'à la route
    // d'accueil "/depenses" (simple redirection) — même raison que le lien
    // "Gestion des recettes" ci-dessous (évite un aller-retour HTTP inutile).
    // null si aucune catégorie active n'existe encore (base pas encore
    // seedée) : le lien est alors simplement masqué (voir plus bas).
    $premiereCategorieDepense = \App\Support\ResourceAccess::can('depenses', 'view')
        ? \App\Models\TypeDepense::query()->where('actif', true)->orderBy('ordre')->first()
        : null;

    // Lien direct de la sidebar vers "Gestion des contrats" — même principe
    // que $premiereCategorieDepense ci-dessus.
    $premiereCategorieContrat = \App\Support\ResourceAccess::can('contrats', 'view')
        ? \App\Models\TypeContrat::query()->where('actif', true)->orderBy('ordre')->first()
        : null;
@endphp

<div class="app-shell">

    {{-- ============ SIDEBAR ============ --}}
    <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar">
        <div class="sidebar-brand">
            <span class="brand-badge"><i class="bi bi-archive-fill"></i></span>
            <span class="sidebar-brand-text">Gestion<span style="color:var(--ingridd-accent)">INGRIDD</span></span>
            <button type="button" class="btn-close d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar"></button>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-group-label">Général</div>
            <a href="{{ route('gestion.dashboard') }}" class="sidebar-link {{ request()->routeIs('gestion.dashboard') ? 'active' : '' }}" title="Tableau de bord">
                <i class="bi bi-grid-1x2-fill"></i><span class="sidebar-label">Tableau de bord</span>
            </a>

            @if (\App\Support\ResourceAccess::can('documents', 'view'))
                <div class="sidebar-group-label">Documents</div>
                {{--
                    `documents.voir`/`documents.telecharger` (voir routes/web.php)
                    vivent HORS du préfixe "gestion." (accessibles depuis
                    n'importe quel module) — sans ce `orWhere`, ouvrir l'aperçu
                    d'un fichier ne laissait plus AUCUNE entrée active dans ce
                    menu, perte de repère signalée par le client ("on ne sait
                    plus sur quel menu on se trouve"). "Tous les documents" sert
                    de repère par défaut faute de pouvoir remonter jusqu'au
                    module d'origine depuis cette page partagée.
                --}}
                <a href="{{ route('gestion.documents.index') }}" class="sidebar-link {{ (request()->routeIs('gestion.documents.*') && ! request()->boolean('corbeille')) || request()->routeIs('documents.voir') ? 'active' : '' }}" title="Tous les documents">
                    <i class="bi bi-file-earmark-text-fill"></i><span class="sidebar-label">Tous les documents</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('documents', 'delete'))
                <a href="{{ route('gestion.documents.index', ['corbeille' => 1]) }}" class="sidebar-link {{ request()->routeIs('gestion.documents.*') && request()->boolean('corbeille') ? 'active' : '' }}" title="Corbeille">
                    <i class="bi bi-trash3-fill"></i><span class="sidebar-label">Corbeille</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('sessions_deliberation', 'view'))
                <a href="{{ route('gestion.sessions-deliberation.index') }}" class="sidebar-link {{ request()->routeIs('gestion.sessions-deliberation.*') ? 'active' : '' }}" title="PV de délibération">
                    <i class="bi bi-clipboard2-check-fill"></i><span class="sidebar-label">PV de délibération</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('sessions_releves', 'view'))
                <a href="{{ route('gestion.sessions-releves.index') }}" class="sidebar-link {{ request()->routeIs('gestion.sessions-releves.*') ? 'active' : '' }}" title="Relevés de notes">
                    <i class="bi bi-journal-text"></i><span class="sidebar-label">Relevés de notes</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('pv_soutenances', 'view'))
                <a href="{{ route('gestion.pv-soutenances.index') }}" class="sidebar-link {{ request()->routeIs('gestion.pv-soutenances.*') ? 'active' : '' }}" title="PV de soutenance">
                    <i class="bi bi-mortarboard-fill"></i><span class="sidebar-label">PV de soutenance</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('reunions', 'view'))
                <a href="{{ route('gestion.reunions.index') }}" class="sidebar-link {{ request()->routeIs('gestion.reunions.*') ? 'active' : '' }}" title="PV de réunion">
                    <i class="bi bi-people-fill"></i><span class="sidebar-label">PV de réunion</span>
                </a>
            @endif

            @if (\App\Support\ResourceAccess::can('etudiants', 'view'))
                <a href="{{ route('gestion.etudiants.index') }}" class="sidebar-link {{ request()->routeIs('gestion.etudiants.*') ? 'active' : '' }}" title="Dossier étudiant">
                    <i class="bi bi-person-vcard-fill"></i><span class="sidebar-label">Dossier étudiant</span>
                </a>
            @endif

            {{-- Gestion des contrats — demande explicite du client
                 ("intègre à présent la gestion des contrat"), voir le
                 docblock de App\Http\Controllers\ContratController. Lien
                 direct vers la première catégorie active, même raison que
                 "Gestion des dépenses"/"Gestion des recettes" plus bas
                 (évite un aller-retour HTTP inutile) — masqué si aucune
                 catégorie active n'existe encore. --}}
            @if ($premiereCategorieContrat)
                <a href="{{ route('gestion.contrats.index', ['typeContrat' => $premiereCategorieContrat->code]) }}" class="sidebar-link {{ request()->routeIs('gestion.contrats.*') ? 'active' : '' }}" title="Gestion des contrats">
                    <i class="bi bi-vector-pen"></i><span class="sidebar-label">Gestion des contrats</span>
                </a>
            @endif

            {{-- Finances — États financiers (demande explicite du client,
                 "intègre la gestion des États financières"), Gestion des
                 Recettes ("intègre la Gestion des Recette"), Gestion des
                 Dépenses ("intègre la GESTION des DEPENSES") et Liste des
                 étudiants (finances) ("intègre également la GESTION de la
                 listes des etudiant... dans la partie Finance"), voir
                 App\Http\Controllers\EtatFinancierController/
                 RecetteScolariteController/RecetteDossierController/
                 DepenseController/ListeFinanceEtudiantController. Les liens
                 "Gestion des recettes"/"Gestion des dépenses" mènent
                 directement au premier onglet plutôt qu'à leur route
                 d'accueil respective (simple redirection) — évite un
                 aller-retour HTTP inutile pour le visiteur qui clique depuis
                 la sidebar. --}}
            @if (\App\Support\ResourceAccess::can('etats_financiers', 'view') || \App\Support\ResourceAccess::can('recettes', 'view') || $premiereCategorieDepense || \App\Support\ResourceAccess::can('listes_finances_etudiants', 'view'))
                <div class="sidebar-group-label">Finances</div>

                @if (\App\Support\ResourceAccess::can('etats_financiers', 'view'))
                    <a href="{{ route('gestion.etats-financiers.index') }}" class="sidebar-link {{ request()->routeIs('gestion.etats-financiers.*') ? 'active' : '' }}" title="États financiers">
                        <i class="bi bi-bar-chart-line-fill"></i><span class="sidebar-label">États financiers</span>
                    </a>
                @endif

                @if (\App\Support\ResourceAccess::can('recettes', 'view'))
                    <a href="{{ route('gestion.recettes.scolarite.index') }}" class="sidebar-link {{ request()->routeIs('gestion.recettes.*') ? 'active' : '' }}" title="Gestion des recettes">
                        <i class="bi bi-cash-coin"></i><span class="sidebar-label">Gestion des recettes</span>
                    </a>
                @endif

                @if ($premiereCategorieDepense)
                    <a href="{{ route('gestion.depenses.index', ['typeDepense' => $premiereCategorieDepense->code]) }}" class="sidebar-link {{ request()->routeIs('gestion.depenses.*') ? 'active' : '' }}" title="Gestion des dépenses">
                        <i class="bi bi-credit-card-2-front-fill"></i><span class="sidebar-label">Gestion des dépenses</span>
                    </a>
                @endif

                @if (\App\Support\ResourceAccess::can('listes_finances_etudiants', 'view'))
                    <a href="{{ route('gestion.listes-finances-etudiants.index') }}" class="sidebar-link {{ request()->routeIs('gestion.listes-finances-etudiants.*') ? 'active' : '' }}" title="Liste des étudiants (finances)">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">Liste des étudiants (finances)</span>
                    </a>
                @endif
            @endif

            @foreach ($menuTypesDocuments as $groupe)
                <a href="#sidebarMenu{{ $groupe['cle'] }}" class="sidebar-link sidebar-link-toggle {{ $groupe['actifType'] ? 'active' : '' }}"
                   data-bs-toggle="collapse" role="button"
                   aria-expanded="{{ $groupe['actifType'] ? 'true' : 'false' }}" aria-controls="sidebarMenu{{ $groupe['cle'] }}"
                   title="{{ $groupe['libelle'] }}">
                    <i class="bi {{ $groupe['icone'] }}"></i>
                    <span class="sidebar-label">{{ $groupe['libelle'] }}</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse {{ $groupe['actifType'] ? 'show' : '' }}" id="sidebarMenu{{ $groupe['cle'] }}">
                    @if ($groupe['urlType'])
                        <a href="{{ $groupe['urlType'] }}" class="sidebar-link sidebar-sublink {{ $groupe['actifType'] && ! request()->filled('sous_type_document_id') ? 'active' : '' }}" title="Tous — {{ $groupe['libelle'] }}">
                            <span class="sidebar-label">Tous</span>
                        </a>
                    @endif
                    @foreach ($groupe['enfants'] as $enfant)
                        <a href="{{ $enfant['url'] }}" class="sidebar-link sidebar-sublink {{ $enfant['actif'] ? 'active' : '' }}" title="{{ $enfant['libelle'] }}">
                            <span class="sidebar-label">{{ $enfant['libelle'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach

            @if ($elementsSecretariat->isNotEmpty())
                <div class="sidebar-group-label">Secrétariat</div>
                @foreach ($elementsSecretariat as $element)
                    <a href="{{ $element['url'] }}" class="sidebar-link {{ $element['actif'] ? 'active' : '' }}" title="{{ $element['libelle'] }}">
                        <i class="bi bi-journal-bookmark-fill"></i><span class="sidebar-label">{{ $element['libelle'] }}</span>
                    </a>
                @endforeach
            @endif

            @if ($elementsReferentiels->isNotEmpty())
                <div class="sidebar-group-label">Référentiels</div>
                @foreach ($elementsReferentiels as $ref)
                    <a href="{{ $ref['url'] }}" class="sidebar-link {{ $ref['actif'] ? 'active' : '' }}" title="{{ $ref['label'] }}">
                        <i class="bi {{ $ref['icone'] }}"></i><span class="sidebar-label">{{ $ref['label'] }}</span>
                    </a>
                @endforeach
            @endif

            {{-- Historique passe par ResourceAccess (un rôle non-admin peut s'en voir accorder la lecture) ; les trois autres restent réservés en dur à l'admin — voir chaque contrôleur. --}}
            @if ($utilisateurConnecte->role === \App\Models\User::ROLE_ADMIN || \App\Support\ResourceAccess::can('audit_logs', 'view'))
                <div class="sidebar-group-label">Administration</div>

                @if (\App\Support\ResourceAccess::can('audit_logs', 'view'))
                    <a href="{{ route('gestion.historique.index') }}" class="sidebar-link {{ request()->routeIs('gestion.historique.*') ? 'active' : '' }}" title="Historique">
                        <i class="bi bi-journal-text"></i><span class="sidebar-label">Historique</span>
                    </a>
                @endif

                @if ($utilisateurConnecte->role === \App\Models\User::ROLE_ADMIN)
                    <a href="{{ route('gestion.roles.index') }}" class="sidebar-link {{ request()->routeIs('gestion.roles.*') ? 'active' : '' }}" title="Rôles et permissions">
                        <i class="bi bi-person-badge-fill"></i><span class="sidebar-label">Rôles &amp; permissions</span>
                    </a>
                    <a href="{{ route('gestion.utilisateurs.index') }}" class="sidebar-link {{ request()->routeIs('gestion.utilisateurs.*') ? 'active' : '' }}" title="Utilisateurs">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">Utilisateurs</span>
                    </a>
                    <a href="{{ route('gestion.sauvegardes.index') }}" class="sidebar-link {{ request()->routeIs('gestion.sauvegardes.*') ? 'active' : '' }}" title="Sauvegardes">
                        <i class="bi bi-cloud-arrow-up-fill"></i><span class="sidebar-label">Sauvegardes</span>
                    </a>
                    <a href="{{ route('gestion.corbeilles.index') }}" class="sidebar-link {{ request()->routeIs('gestion.corbeilles.*') ? 'active' : '' }}" title="Corbeilles">
                        <i class="bi bi-trash3-fill"></i><span class="sidebar-label">Corbeilles</span>
                    </a>
                    <a href="{{ route('gestion.annonces.create') }}" class="sidebar-link {{ request()->routeIs('gestion.annonces.*') ? 'active' : '' }}" title="Annonces">
                        <i class="bi bi-megaphone-fill"></i><span class="sidebar-label">Annonces</span>
                    </a>

                    {{--
                        Sous-menu "Paramètres" — demande explicite du client
                        ("créer des sous-menu directement sur le menu")
                        plutôt que de passer par la page Paramètres pour
                        choisir une section : même principe de collapse que
                        $menuTypesDocuments ci-dessus (sidebar-link-toggle +
                        sidebar-sublink), une entrée par section de
                        App\Http\Controllers\ParametresController::SECTIONS.
                    --}}
                    @php
                        $sectionParametresActive = request()->routeIs('gestion.parametres.*');
                        $sectionParametresActuelle = request()->route('section') ?? array_key_first(\App\Http\Controllers\ParametresController::SECTIONS);
                    @endphp
                    <a href="#sidebarMenuParametres" class="sidebar-link sidebar-link-toggle {{ $sectionParametresActive ? 'active' : '' }}"
                       data-bs-toggle="collapse" role="button"
                       aria-expanded="{{ $sectionParametresActive ? 'true' : 'false' }}" aria-controls="sidebarMenuParametres"
                       title="Paramètres">
                        <i class="bi bi-gear-fill"></i>
                        <span class="sidebar-label">Paramètres</span>
                        <i class="bi bi-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse {{ $sectionParametresActive ? 'show' : '' }}" id="sidebarMenuParametres">
                        @foreach (\App\Http\Controllers\ParametresController::SECTIONS as $cle => $libelle)
                            <a href="{{ route('gestion.parametres.index', ['section' => $cle]) }}" class="sidebar-link sidebar-sublink {{ $sectionParametresActive && $sectionParametresActuelle === $cle ? 'active' : '' }}" title="{{ $libelle }}">
                                <span class="sidebar-label">{{ $libelle }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </nav>

        <div class="sidebar-footer d-none d-lg-block">
            <button type="button" id="sidebarCollapseBtn" class="sidebar-collapse-btn">
                <i class="bi bi-layout-sidebar-inset" id="sidebarCollapseIcon"></i>
                <span id="sidebarCollapseLabel">Réduire</span>
            </button>
        </div>
    </aside>

    {{-- ============ ZONE PRINCIPALE ============ --}}
    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="topbar-icon-btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appSidebar">
                <i class="bi bi-list"></i>
            </button>

            <button type="button" class="topbar-icon-btn d-none d-lg-inline-flex" id="sidebarCollapseBtnTop" title="Réduire/agrandir le menu">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>

            <form action="{{ route('gestion.documents.index') }}" method="GET" class="topbar-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="recherche" class="form-control" placeholder="Rechercher un document...">
                </div>
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" id="themeToggle" class="topbar-icon-btn" title="Changer de thème">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>

                <div class="dropdown">
                    <button type="button" class="topbar-icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                        <i class="bi bi-bell-fill"></i>
                        @if ($nombreNotificationsNonLues > 0)
                            <span class="notif-dot">{{ $nombreNotificationsNonLues > 9 ? '9+' : $nombreNotificationsNonLues }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="width: 340px; max-width: 90vw;">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <span class="fw-semibold small">Notifications</span>
                            @if ($nombreNotificationsNonLues > 0)
                                <form method="POST" action="{{ route('gestion.notifications.tout-marquer-comme-lu') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0 small">Tout marquer comme lu</button>
                                </form>
                            @endif
                        </div>
                        <div style="max-height: 360px; overflow-y: auto;">
                            @forelse ($notificationsRecentes as $notification)
                                <form method="POST" action="{{ route('gestion.notifications.marquer-comme-lue', $notification->id) }}"
                                      class="border-bottom {{ $notification->read_at ? '' : 'bg-primary-subtle bg-opacity-25' }}">
                                    @csrf
                                    <button type="submit" class="btn w-100 text-start px-3 py-2 rounded-0 d-flex gap-2 align-items-start">
                                        <i class="bi {{ $notification->data['icone'] ?? 'bi-bell-fill' }} mt-1"></i>
                                        <span class="flex-grow-1">
                                            <span class="d-block small fw-semibold">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                                            <span class="d-block small text-muted text-wrap">{{ \Illuminate\Support\Str::limit($notification->data['message'] ?? '', 80) }}</span>
                                            <span class="d-block text-muted" style="font-size: .72rem;">{{ $notification->created_at->diffForHumans() }}</span>
                                        </span>
                                    </button>
                                </form>
                            @empty
                                <div class="text-center text-muted small py-4">
                                    <i class="bi bi-bell"></i><br>Aucune notification pour le moment.
                                </div>
                            @endforelse
                        </div>
                        <a href="{{ route('gestion.notifications.index') }}" class="dropdown-item text-center small border-top py-2">Voir toutes mes notifications</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button type="button" class="btn btn-sm d-flex align-items-center gap-2 border" data-bs-toggle="dropdown">
                        <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:28px;height:28px;font-size:.75rem;">
                            {{ \Illuminate\Support\Str::of($utilisateurConnecte->name)->substr(0, 1) }}
                        </span>
                        <span class="d-none d-md-inline">{{ $utilisateurConnecte->name }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                        <span class="dropdown-item-text small text-muted">{{ \App\Models\User::libellesRoles()[$utilisateurConnecte->role] ?? $utilisateurConnecte->role }}</span>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('gestion.profil.edit') }}" class="dropdown-item"><i class="bi bi-person-circle me-2"></i>Mon profil</a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('gestion.deconnexion') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="app-content fade-in-up">
            @if (session('succes'))
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('succes') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>
                        @foreach ($errors->all() as $erreur)
                            {{ $erreur }}<br>
                        @endforeach
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="text-center text-muted small py-3">
            &copy; {{ date('Y') }} {{ $parametresGlobaux->nom_application }}.
        </footer>
    </div>
</div>

{{--
    Fenêtre modale PARTAGÉE pour "Voir l'historique" — une seule instance
    pour toute l'application, réutilisée par CHAQUE bouton
    resources/views/gestion/partials/_lien_historique.blade.php (document,
    dépense, dossier de recette, PV...) plutôt qu'une modale par ligne —
    demande explicite du client ("rester sur la même page pour voir
    l'historique du fichier") : avant cette modale, ce raccourci naviguait
    vers l'écran Historique complet, perdant le contexte de la page
    d'origine. Le contenu (juste le tableau, voir
    gestion.historique._tableau) est chargé en arrière-plan au moment de
    l'ouverture (voir le script plus bas), à partir de l'URL indiquée par le
    bouton qui a déclenché l'ouverture (`data-historique-url`).
--}}
<div class="modal fade" id="modalHistorique" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history"></i> Historique
                    <span class="text-muted fs-6" id="modalHistoriqueSousTitre"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="modalHistoriqueCorps">
                <div class="d-flex align-items-center gap-2 text-muted py-4 justify-content-center">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    Chargement…
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalHistoriqueLienComplet" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list-ul"></i> Voir tout l'historique
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('submit', function (e) {
        // e.defaultPrevented : un formulaire avec onsubmit="return confirm(...)"
        // (voir "Créer une sauvegarde"/"Supprimer" un peu partout) déclenche
        // quand même cet événement "submit" même quand l'utilisateur clique sur
        // "Annuler" — seule la soumission réelle est bloquée (return false),
        // pas l'événement lui-même, qui continue de remonter jusqu'ici. Sans
        // cette vérification, le bouton passait en "Veuillez patienter..." et
        // y restait indéfiniment (aucune navigation ne survient jamais pour le
        // réinitialiser) alors que rien n'avait été envoyé au serveur.
        if (e.defaultPrevented) return;

        const form = e.target;
        if (form.method && form.method.toLowerCase() === 'get') return;
        const btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.classList.contains('is-loading')) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.classList.add('is-loading');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Veuillez patienter...';
        }
    });

    document.querySelectorAll('.count-up').forEach(function (el) {
        const cible = parseInt(el.textContent.replace(/\D/g, ''), 10) || 0;
        if (cible === 0) return;
        const duree = 700;
        const t0 = performance.now();
        function pas(t) {
            const progression = Math.min((t - t0) / duree, 1);
            el.textContent = Math.floor(progression * cible);
            if (progression < 1) requestAnimationFrame(pas);
            else el.textContent = cible;
        }
        requestAnimationFrame(pas);
    });

    (function () {
        const html = document.documentElement;
        const btn = document.getElementById('themeToggle');
        const icon = document.getElementById('themeIcon');
        function actualiserIcone() {
            const sombre = html.getAttribute('data-bs-theme') === 'dark';
            icon.className = sombre ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        actualiserIcone();
        btn.addEventListener('click', function () {
            const nouveauTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nouveauTheme);
            localStorage.setItem('ingridd-theme', nouveauTheme);
            actualiserIcone();
        });
    })();

    (function () {
        const sidebar = document.getElementById('appSidebar');
        const btns = [document.getElementById('sidebarCollapseBtn'), document.getElementById('sidebarCollapseBtnTop')];
        const label = document.getElementById('sidebarCollapseLabel');

        function appliquer(etat) {
            sidebar.classList.toggle('is-collapsed', etat);
            if (label) label.textContent = etat ? 'Agrandir' : 'Réduire';
        }

        appliquer(localStorage.getItem('ingridd-sidebar-collapsed') === '1');

        btns.forEach(function (btn) {
            if (!btn) return;
            btn.addEventListener('click', function () {
                const nouvelEtat = !sidebar.classList.contains('is-collapsed');
                appliquer(nouvelEtat);
                localStorage.setItem('ingridd-sidebar-collapsed', nouvelEtat ? '1' : '0');
            });
        });
    })();

    (function () {
        const lienActif = document.querySelector('.sidebar-nav .sidebar-link.active');
        if (lienActif) {
            lienActif.scrollIntoView({ block: 'center' });
        }
    })();

    // Fenêtre modale partagée "Voir l'historique" (voir l'élément #modalHistorique
    // plus haut et resources/views/gestion/partials/_lien_historique.blade.php) :
    // charge le fragment (juste le tableau, `fragment=1`) par fetch à chaque
    // ouverture, à partir du bouton qui l'a déclenchée (`event.relatedTarget`,
    // fourni nativement par l'évènement "show.bs.modal" de Bootstrap).
    (function () {
        const modale = document.getElementById('modalHistorique');
        if (!modale) return;

        const corps = document.getElementById('modalHistoriqueCorps');
        const sousTitre = document.getElementById('modalHistoriqueSousTitre');
        const lienComplet = document.getElementById('modalHistoriqueLienComplet');
        const contenuChargement = corps.innerHTML;

        modale.addEventListener('show.bs.modal', function (evenement) {
            const bouton = evenement.relatedTarget;
            if (!bouton) return;

            const url = bouton.getAttribute('data-historique-url');
            sousTitre.textContent = bouton.getAttribute('data-historique-titre') ? '— ' + bouton.getAttribute('data-historique-titre') : '';
            lienComplet.href = bouton.getAttribute('data-historique-url-complet') || '#';
            corps.innerHTML = contenuChargement;

            if (!url) return;

            fetch(url, { credentials: 'same-origin' })
                .then(function (reponse) {
                    if (!reponse.ok) throw new Error('Échec du chargement (' + reponse.status + ')');
                    return reponse.text();
                })
                .then(function (html) {
                    corps.innerHTML = html;
                })
                .catch(function (erreur) {
                    console.error(erreur);
                    corps.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> Impossible de charger l\'historique.</div>';
                });
        });
    })();

    // Bouton "afficher/masquer" sur les champs mot de passe (voir
    // resources/views/components/champ-mot-de-passe.blade.php) — demande
    // explicite du client, pour tous les mots de passe de la plateforme
    // (connexion, création/modification d'un compte, "Mon profil",
    // configuration mail SMTP...). Délégué sur `document` plutôt qu'un
    // addEventListener par bouton : fonctionne pour tous les champs,
    // présents dès le chargement de la page comme ajoutés dynamiquement
    // ensuite, sans rien à répéter sur chaque écran.
    document.addEventListener('click', function (evenement) {
        const bouton = evenement.target.closest('.toggle-mot-de-passe');
        if (!bouton) return;

        const champ = bouton.closest('.input-group')?.querySelector('input');
        if (!champ) return;

        const futureVisible = champ.type === 'password';
        champ.type = futureVisible ? 'text' : 'password';

        const icone = bouton.querySelector('i');
        if (icone) {
            icone.classList.toggle('bi-eye', !futureVisible);
            icone.classList.toggle('bi-eye-slash', futureVisible);
        }
        bouton.setAttribute('aria-label', futureVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
</script>
@yield('scripts')
</body>
</html>
