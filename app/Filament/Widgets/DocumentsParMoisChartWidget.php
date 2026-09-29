<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Support\ResourceAccess;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Tendance d'ajout de documents sur les 12 derniers mois — répond au besoin
 * de "prendre en compte la date de création d'un document" en donnant une
 * vue d'ensemble, en plus du filtre par date déjà disponible sur la liste
 * (voir DocumentResource::table(), filtre "ajoute_entre").
 *
 * Les comptages sont faits en PHP (une seule requête qui ramène les dates,
 * regroupées ensuite avec Carbon) plutôt qu'avec une fonction SQL de date
 * (DATE_FORMAT en MySQL, strftime en SQLite : pas la même syntaxe d'un
 * moteur à l'autre, et les tests tournent sur SQLite en mémoire alors que
 * l'application tourne en production sur MySQL — voir config/database.php).
 */
class DocumentsParMoisChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Documents ajoutés par mois';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return ResourceAccess::can('documents', 'view');
    }

    protected function getData(): array
    {
        $depuis = now()->subMonths(11)->startOfMonth();

        $dates = Document::query()
            ->withTrashed()
            ->where('created_at', '>=', $depuis)
            ->pluck('created_at');

        $mois = collect(range(0, 11))
            ->map(fn (int $i) => now()->subMonths(11 - $i)->startOfMonth());

        $comptes = $mois->map(
            fn (Carbon $mois) => $dates->filter(
                fn ($date) => $date->isSameMonth($mois) && $date->isSameYear($mois)
            )->count()
        );

        return [
            'datasets' => [
                [
                    'label' => 'Documents ajoutés',
                    'data' => $comptes->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $mois->map(fn (Carbon $mois) => ucfirst($mois->translatedFormat('M Y')))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
