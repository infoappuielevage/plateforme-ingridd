@extends('layouts.gestion')

@section('title', 'Tableau de bord')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Tableau de bord {{ config('app.name') }}</h1>
</div>

{{--
    "Mon compte" + calendrier — demande explicite du client : que la personne
    connectée voie tout de suite son nom, son rôle et ses droits d'accès
    réels (plutôt que de devoir demander), et dispose d'un calendrier du mois
    en cours pour s'orienter dans les dates (volontairement sans évènements,
    voir DashboardController::calendrier()).

    Rendu plus "vivant" (demande explicite du client, "rends ça plus vivante
    [...] avec les couleurs et tout ce qu'il faut") : en-têtes de carte
    colorés (header-primary/header-info, même convention que les autres
    écrans — voir public/css/style.css), avatar à initiale (même motif que le
    bouton utilisateur du menu du haut, voir resources/views/layouts/
    gestion.blade.php), droits d'accès résumés par un badge coloré en plus du
    tableau détaillé, et week-ends teintés dans le calendrier.
--}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header header-primary d-flex align-items-center gap-2">
                <i class="bi bi-person-circle"></i> Mon compte
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:48px;height:48px;font-size:1.1rem;">
                        {{ \Illuminate\Support\Str::of($monCompte['nom'])->substr(0, 1) }}
                    </span>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ $monCompte['nom'] }}</span>
                            <span class="badge bg-primary-subtle text-primary-emphasis">{{ $monCompte['roleLibelle'] }}</span>
                        </div>
                        <div class="text-muted small"><i class="bi bi-envelope"></i> {{ $monCompte['email'] }}</div>
                    </div>
                </div>

                <div class="text-muted small mb-3">
                    <i class="bi bi-clock-history"></i>
                    Dernière connexion :
                    {{ $monCompte['derniereConnexionAt']?->format('d/m/Y H:i') ?? 'Jamais' }}
                    @if ($monCompte['derniereConnexionAt'])
                        depuis {{ $monCompte['derniereConnexionIp'] ?? '—' }}
                    @endif
                </div>

                <h2 class="h6 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock"></i> Mes droits d'accès
                    @if ($monCompte['estAdmin'])
                        <span class="badge text-bg-success">Accès complet</span>
                    @else
                        @php
                            $nombreRessourcesAutorisees = collect(\App\Support\ResourceAccess::RESSOURCES)->keys()
                                ->filter(fn ($cle) => collect(data_get($monCompte['permissions'], $cle, []))->contains(true))
                                ->count();
                        @endphp
                        <span class="badge text-bg-info">{{ $nombreRessourcesAutorisees }} / {{ count(\App\Support\ResourceAccess::RESSOURCES) }} ressources</span>
                    @endif
                </h2>
                @if ($monCompte['estAdmin'])
                    <p class="text-muted small mb-0">
                        <i class="bi bi-shield-check text-success"></i>
                        Accès complet à toutes les fonctionnalités de la plateforme.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ressource</th>
                                    @foreach (\App\Support\ResourceAccess::ACTIONS as $libelleAction)
                                        <th class="text-center">{{ $libelleAction }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\App\Support\ResourceAccess::RESSOURCES as $cle => $libelle)
                                    <tr>
                                        <td>
                                            <i class="bi {{ \App\Support\ResourceAccess::ICONES[$cle] ?? 'bi-ui-checks' }} text-muted me-1"></i>
                                            {{ $libelle }}
                                        </td>
                                        @foreach (\App\Support\ResourceAccess::ACTIONS as $action => $libelleAction)
                                            <td class="text-center">
                                                @if ($cle === 'audit_logs' && $action !== 'view')
                                                    <span class="text-muted">—</span>
                                                @elseif (data_get($monCompte['permissions'], "{$cle}.{$action}", false))
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                @else
                                                    <i class="bi bi-dash text-muted"></i>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <a href="{{ route('gestion.profil.edit') }}" class="btn btn-sm btn-outline-primary mt-3">
                    <i class="bi bi-pencil"></i> Modifier mon profil
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header header-info d-flex align-items-center gap-2">
                <i class="bi bi-calendar3"></i> {{ $calendrier['libelleMois'] }}
            </div>
            <div class="card-body">
                <table class="table table-sm text-center mb-0 ingridd-calendrier">
                    <thead>
                        <tr>
                            <th>Lun</th>
                            <th>Mar</th>
                            <th>Mer</th>
                            <th>Jeu</th>
                            <th>Ven</th>
                            <th class="ingridd-calendrier-weekend">Sam</th>
                            <th class="ingridd-calendrier-weekend">Dim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($calendrier['semaines'] as $semaine)
                            <tr>
                                @foreach ($semaine as $index => $jour)
                                    <td class="{{ $jour && $jour['estAujourdhui'] ? 'ingridd-calendrier-aujourdhui' : (in_array($index, [5, 6], true) ? 'ingridd-calendrier-weekend' : '') }}">
                                        {{ $jour['numero'] ?? '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4 stagger">
    @foreach ($cartes as $carte)
        <div class="col">
            <div class="card stat-card {{ $carte['accent'] ? 'accent' : '' }} h-100">
                <div class="card-body">
                    <span class="stat-icon"><i class="bi {{ $carte['icone'] }}"></i></span>
                    <div class="fs-3 fw-bold {{ ($carte['alerte'] ?? false) ? 'text-danger' : 'text-primary' }} count-up mt-2">{{ $carte['valeur'] }}</div>
                    <div class="small text-muted">{{ $carte['label'] }}</div>
                    @if ($carte['description'])
                        <div class="small text-muted mt-1">{{ $carte['description'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    @if ($derniereSauvegarde)
        <div class="col">
            <div class="card stat-card {{ $derniereSauvegarde['alerte'] ? '' : 'accent' }} h-100">
                <div class="card-body">
                    <span class="stat-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                    <div class="fs-5 fw-bold {{ $derniereSauvegarde['alerte'] ? 'text-danger' : 'text-primary' }} mt-2">{{ $derniereSauvegarde['valeur'] }}</div>
                    <div class="small text-muted">Dernière sauvegarde</div>
                    <div class="small text-muted mt-1">{{ $derniereSauvegarde['description'] }}</div>
                </div>
            </div>
        </div>
    @endif
</div>

@if (\App\Support\ResourceAccess::can('documents', 'view') && count($graphiques) > 0)
    <div class="row g-3 mb-4">
        @foreach ($graphiques as $cle => $graphique)
            <div class="col-12 {{ $cle === 'parMois' ? '' : 'col-lg-6' }}">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ $graphique['titre'] }}</h2>
                        @if (count($graphique['donnees']) > 0 && array_sum($graphique['donnees']) > 0)
                            <div style="height: {{ $cle === 'parMois' ? 220 : 260 }}px;">
                                <canvas id="graphique-{{ $cle }}"></canvas>
                            </div>
                        @else
                            <div class="empty-state text-center py-4">
                                <i class="bi bi-bar-chart text-muted"></i>
                                <p class="text-muted small mb-0">Pas encore de données.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="section-header">
    <span class="icon-chip accent"><i class="bi bi-clock-history"></i></span>
    <h2>Documents récemment ajoutés</h2>
</div>

@if ($documentsRecents->isEmpty())
    <div class="empty-state"><i class="bi bi-inbox"></i>Aucun document pour le moment.</div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle ingridd-surface mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Sous-type</th>
                    <th>Fichier</th>
                    <th>Ajouté par</th>
                    <th>Ajouté le</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documentsRecents as $document)
                    <tr>
                        <td>{{ $document->typeDocument?->libelle }}</td>
                        <td>{{ $document->sousTypeDocument?->libelle ?? '—' }}</td>
                        <td>{{ $document->nom_original }}</td>
                        <td>{{ $document->uploadePar?->name }}</td>
                        <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection

@if (\App\Support\ResourceAccess::can('documents', 'view') && count($graphiques) > 0)
    @section('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const graphiques = @json($graphiques);
                const estSombre = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
                const couleurTexte = () => estSombre() ? '#e5e7eb' : '#374151';
                const couleurGrille = () => estSombre() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

                Object.entries(graphiques).forEach(function ([cle, graphique]) {
                    if (! graphique.donnees.length || graphique.donnees.reduce((a, b) => a + b, 0) === 0) {
                        return;
                    }

                    const canevas = document.getElementById('graphique-' + cle);
                    if (! canevas) return;

                    new Chart(canevas, {
                        type: graphique.type,
                        data: {
                            labels: graphique.labels,
                            datasets: [{
                                label: graphique.titre,
                                data: graphique.donnees,
                                backgroundColor: graphique.couleurs,
                                borderColor: graphique.type === 'line' ? graphique.couleurs : undefined,
                                fill: graphique.type === 'line',
                                tension: graphique.type === 'line' ? 0.3 : undefined,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: graphique.type !== 'line' && graphique.type !== 'bar',
                                    labels: { color: couleurTexte() },
                                },
                            },
                            scales: (graphique.type === 'line' || graphique.type === 'bar') ? {
                                x: { ticks: { color: couleurTexte() }, grid: { color: couleurGrille() } },
                                y: { ticks: { color: couleurTexte() }, grid: { color: couleurGrille() }, beginAtZero: true },
                            } : undefined,
                        },
                    });
                });
            })();
        </script>
    @endsection
@endif
