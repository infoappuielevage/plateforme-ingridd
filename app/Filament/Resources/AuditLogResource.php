<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Historique — lecture seule pour tout le monde (voir la note dans
 * App\Support\ResourceAccess::RESSOURCES) : aucune action de création,
 * modification ou suppression n'est exposée ici. Un administrateur peut
 * purger les entrées trop anciennes via une commande artisan planifiée
 * (voir routes/console.php), jamais depuis cette interface.
 */
class AuditLogResource extends Resource
{
    use HasRoleBasedAccess;

    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Historique';

    protected static ?string $modelLabel = 'entrée d\'historique';

    protected static ?string $pluralModelLabel = 'historique';

    public static function resourceAccessKey(): string
    {
        return 'audit_logs';
    }

    // Ni création, ni modification, ni suppression manuelle — voir docblock ci-dessus.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user_name')->label('Utilisateur')->searchable()
                    ->default('—'),
                TextColumn::make('action')->label('Action')->badge()->searchable(),
                TextColumn::make('resume')->label('Détail')->searchable()->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'creation' => 'Création',
                        'modification' => 'Modification',
                        'suppression' => 'Suppression définitive',
                        'mise_en_corbeille' => 'Mise à la corbeille',
                        'restauration' => 'Restauration',
                        'connexion' => 'Connexion',
                        'deconnexion' => 'Déconnexion',
                        'connexion_echouee' => 'Connexion échouée',
                        'connexion_verrouillee' => 'Connexion verrouillée',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
