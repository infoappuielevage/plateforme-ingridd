<?php

namespace App\Filament\Widgets;

use App\Models\Filiere;
use App\Support\ResourceAccess;
use Filament\Widgets\ChartWidget;

class DocumentsParFiliereChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Documents par filière';

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return ResourceAccess::can('documents', 'view');
    }

    protected function getData(): array
    {
        $filieres = Filiere::query()
            ->withCount('documents')
            ->orderBy('ordre')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => $filieres->pluck('documents_count')->all(),
                    'backgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $filieres->pluck('libelle')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
