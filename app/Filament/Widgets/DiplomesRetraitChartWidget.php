<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Support\ResourceAccess;
use App\Support\TypesDiplome;
use Filament\Widgets\ChartWidget;

/**
 * Répartition "Retiré / Non retiré" des diplômes et attestations — voir
 * Document::STATUT_RETRAIT_* et le bouton "Marquer comme retiré" sur
 * DocumentResource::table(). Masqué si le référentiel des types ne contient
 * ni "Diplôme" ni "Attestation" (renommés/supprimés), pour ne jamais
 * afficher un graphique à 0/0 sans queue ni tête.
 */
class DiplomesRetraitChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Diplômes et attestations — retraits';

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return ResourceAccess::can('documents', 'view') && count(TypesDiplome::ids()) > 0;
    }

    protected function getData(): array
    {
        $ids = TypesDiplome::ids();

        $parStatut = Document::query()
            ->whereIn('type_document_id', $ids)
            ->selectRaw('statut_retrait, count(*) as total')
            ->groupBy('statut_retrait')
            ->pluck('total', 'statut_retrait');

        return [
            'datasets' => [
                [
                    'label' => 'Diplômes / attestations',
                    'data' => [
                        (int) ($parStatut[Document::STATUT_RETRAIT_NON_RETIRE] ?? 0),
                        (int) ($parStatut[Document::STATUT_RETRAIT_RETIRE] ?? 0),
                    ],
                    'backgroundColor' => ['#f97316', '#22c55e'],
                ],
            ],
            'labels' => ['Non retiré', 'Retiré'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
