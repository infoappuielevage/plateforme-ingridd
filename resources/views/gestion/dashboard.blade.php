@extends('layouts.gestion')

@section('title', 'Tableau de bord')

@section('content')
<h1 class="h3">Tableau de bord {{ config('app.name') }}</h1>

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
