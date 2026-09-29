<?php

namespace App\Filament\Widgets;

use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use Filament\Widgets\ChartWidget;

class DocumentsParTypeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Documents par type';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return ResourceAccess::can('documents', 'view');
    }

    protected function getData(): array
    {
        $types = TypeDocument::query()
            ->withCount('documents')
            ->orderBy('ordre')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => $types->pluck('documents_count')->all(),
                    'backgroundColor' => ['#6366f1', '#22c55e', '#f97316', '#ef4444', '#0ea5e9', '#a855f7'],
                ],
            ],
            'labels' => $types->pluck('libelle')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
