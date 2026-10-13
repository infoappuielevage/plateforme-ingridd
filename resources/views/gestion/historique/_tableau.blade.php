{{--
    Tableau de résultats de l'historique — extrait de index.blade.php pour
    être réutilisé TEL QUEL dans deux contextes : la page complète
    (gestion.historique.index, @include ci-dessous) ET le fragment chargé en
    arrière-plan par la fenêtre modale partagée (voir App\Http\Controllers\
    AuditLogController::index(), `fragment=1`, et resources/views/layouts/
    gestion.blade.php) — demande explicite du client ("rester sur la même
    page pour voir l'historique d'un document").

    PAS de @extends/layout ici volontairement : ce fichier doit pouvoir être
    injecté tel quel dans le <body> d'une modale (innerHTML), jamais rendu
    comme une page à part entière. Variables attendues : $entrees, $actions,
    $triActuel, $directionActuelle.
--}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-th-triable cle="created_at" libelle="Date" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.historique.index" />
                    <x-th-triable cle="user_name" libelle="Utilisateur" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.historique.index" />
                    <x-th-triable cle="action" libelle="Action" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.historique.index" />
                    <x-th-triable cle="resume" libelle="Détail" :tri-actuel="$triActuel" :direction-actuelle="$directionActuelle" route="gestion.historique.index" />
                </tr>
            </thead>
            <tbody>
                @forelse ($entrees as $entree)
                    <tr>
                        <td class="text-muted small text-nowrap">{{ $entree->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $entree->user_name ?? '—' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $actions[$entree->action] ?? $entree->action }}</span></td>
                        <td>{{ $entree->resume }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-clock-history fs-1 text-muted"></i>
                                <p class="text-muted mb-0">Aucune entrée.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($entrees->hasPages())
        <div class="card-footer bg-transparent">
            {{ $entrees->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
