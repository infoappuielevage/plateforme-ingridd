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
        foreach (\App\Models\TypeDocument::query()->where('epingle_secretariat', true)->orderBy('ordre')->get() as $typeEpingle) {
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

            @if ($elementsSecretariat->isNotEmpty())
                <div class="sidebar-group-label">Secrétariat</div>
                @foreach ($elementsSecretariat as $element)
                    <a href="{{ $element['url'] }}" class="sidebar-link {{ $element['actif'] ? 'active' : '' }}" title="{{ $element['libelle'] }}">
                        <i class="bi bi-journal-bookmark-fill"></i><span class="sidebar-label">{{ $element['libelle'] }}</span>
                    </a>
                @endforeach
            @endif

            <div class="sidebar-group-label">Référentiels</div>
            @foreach ([
                ['cle' => 'types_documents', 'icone' => 'bi-tags-fill', 'label' => 'Types de documents'],
                ['cle' => 'filieres', 'icone' => 'bi-diagram-3-fill', 'label' => 'Filières'],
                ['cle' => 'niveaux', 'icone' => 'bi-bar-chart-steps', 'label' => 'Niveaux'],
                ['cle' => 'annees_academiques', 'icone' => 'bi-calendar-range-fill', 'label' => 'Années académiques'],
            ] as $ref)
                @if (\App\Support\ResourceAccess::can($ref['cle'], 'view'))
                    <a href="#" class="sidebar-link disabled" title="{{ $ref['label'] }}">
                        <i class="bi {{ $ref['icone'] }}"></i><span class="sidebar-label">{{ $ref['label'] }}</span>
                        <span class="badge bg-secondary ms-auto" style="font-size:.6rem;">bientôt</span>
                    </a>
                @endif
            @endforeach

            @if ($utilisateurConnecte->role === \App\Models\User::ROLE_ADMIN)
                <div class="sidebar-group-label">Administration</div>
                <a href="#" class="sidebar-link disabled" title="Rôles et permissions">
                    <i class="bi bi-person-badge-fill"></i><span class="sidebar-label">Rôles &amp; permissions</span>
                    <span class="badge bg-secondary ms-auto" style="font-size:.6rem;">bientôt</span>
                </a>
                <a href="#" class="sidebar-link disabled" title="Historique">
                    <i class="bi bi-journal-text"></i><span class="sidebar-label">Historique</span>
                    <span class="badge bg-secondary ms-auto" style="font-size:.6rem;">bientôt</span>
                </a>
                <a href="#" class="sidebar-link disabled" title="Sauvegardes">
                    <i class="bi bi-cloud-arrow-up-fill"></i><span class="sidebar-label">Sauvegardes</span>
                    <span class="badge bg-secondary ms-auto" style="font-size:.6rem;">bientôt</span>
                </a>
                <a href="#" class="sidebar-link disabled" title="Paramètres">
                    <i class="bi bi-gear-fill"></i><span class="sidebar-label">Paramètres</span>
                    <span class="badge bg-secondary ms-auto" style="font-size:.6rem;">bientôt</span>
                </a>
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
