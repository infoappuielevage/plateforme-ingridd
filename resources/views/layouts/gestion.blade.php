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
    ])->filter(fn (array $ref) => \App\Support\ResourceAccess::can($ref['cle'], 'view'));
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
                <a href="{{ route('gestion.documents.index') }}" class="sidebar-link {{ request()->routeIs('gestion.documents.*') && ! request()->boolean('corbeille') ? 'active' : '' }}" title="Tous les documents">
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
                    <a href="{{ route('gestion.parametres.index') }}" class="sidebar-link {{ request()->routeIs('gestion.parametres.*') ? 'active' : '' }}" title="Paramètres">
                        <i class="bi bi-gear-fill"></i><span class="sidebar-label">Paramètres</span>
                    </a>
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
</script>
@yield('scripts')
</body>
</html>
