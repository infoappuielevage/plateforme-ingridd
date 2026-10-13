{{--
    Layout simple (barre de navigation classique, sans sidebar) pour les
    pages accessibles avant connexion — ici, uniquement la connexion : cette
    plateforme n'a pas d'inscription libre (les comptes sont créés par un
    administrateur, voir App\Filament\Resources\UserResource /
    App\Models\User::ROLE_*), contrairement à ingridd-biblio dont ce layout
    est repris.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Connexion') - {{ $parametresGlobaux->nom_application }}</title>
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
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark ingridd-navbar sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('login') }}">
            <i class="bi bi-archive-fill me-1"></i> Gestion<span class="text-warning">INGRIDD</span>
        </a>
        <ul class="navbar-nav ms-auto align-items-lg-center">
            <li class="nav-item">
                <button type="button" id="themeToggle" class="btn btn-sm btn-outline-light my-1 my-lg-0" title="Changer de thème">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>
            </li>
        </ul>
    </div>
</nav>

<div class="container my-4 fade-in-up flex-grow-1">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ session('status') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</div>

<footer class="text-center text-muted small py-4">
    &copy; {{ date('Y') }} {{ $parametresGlobaux->nom_application }}.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

    // Bouton "afficher/masquer" sur les champs mot de passe (voir
    // resources/views/components/champ-mot-de-passe.blade.php et le même
    // script dans layouts/gestion.blade.php, pour les écrans après
    // connexion) — demande explicite du client.
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
</body>
</html>
