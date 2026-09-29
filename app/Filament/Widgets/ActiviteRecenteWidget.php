<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use App\Support\ResourceAccess;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Aperçu des dernières actions (qui a ajouté/modifié/supprimé quoi, et
 * quand) directement sur le tableau de bord — l'historique complet reste
 * consultable dans Administration > Historique (AuditLogResource) pour le
 * détail avant/après.
 */
class ActiviteRecenteWidget extends BaseWidget
{
    protected static ?string $heading = 'Activité récente';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return ResourceAccess::can('audit_logs', 'view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->latest('created_at'))
            ->heading('Activité récente')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('resume')
                    ->label('Action')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('user_name')
                    ->label('Par')
                    ->default('Système')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Quand')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->tooltip(fn (AuditLog $record) => $record->created_at?->diffForHumans()),
            ]);
    }
}
