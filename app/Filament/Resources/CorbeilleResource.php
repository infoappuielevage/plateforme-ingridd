<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorbeilleResource\Pages;
use App\Models\Document;
use App\Support\ResourceAccess;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Accès direct à la corbeille des documents depuis le menu — sur demande du
 * client, plutôt que le filtre "Corbeille" de DocumentResource (qui reste
 * disponible, inchangé, pour qui préfère l'utiliser depuis l'écran
 * Documents habituel). Même modèle (Document), mais une Resource séparée :
 * uniquement une liste, sans création ni modification possible ici — voir
 * getEloquentQuery() (uniquement les documents corbeillés) et getPages()
 * (un seul écran, 'index').
 *
 * Réservée à qui peut supprimer des documents (`ResourceAccess::can('documents',
 * 'delete')`, le même droit que les actions Restaurer/Supprimer
 * définitivement sur DocumentResource) plutôt qu'au droit "Voir" habituel :
 * voir la corbeille n'a de sens que pour qui peut agir dessus. Ne réutilise
 * donc pas App\Filament\Concerns\HasRoleBasedAccess (qui lie canViewAny()
 * au droit "Voir").
 *
 * "Vider la corbeille" (suppression définitive de tout, en un clic) est sur
 * la page de liste — voir Pages\ListCorbeille::getHeaderActions().
 */
class CorbeilleResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Corbeille';

    protected static ?string $navigationLabel = 'Corbeille';

    protected static ?string $slug = 'corbeille';

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'documents';

    public static function shouldRegisterNavigation(): bool
    {
        return ResourceAccess::can('documents', 'delete');
    }

    public static function canViewAny(): bool
    {
        return ResourceAccess::can('documents', 'delete');
    }

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
        return ResourceAccess::can('documents', 'delete');
    }

    public static function getEloquentQuery(): Builder
    {
        // Tout l'intérêt de cet écran séparé : uniquement les documents déjà
        // corbeillés, pas besoin ici du filtre "Corbeille" de DocumentResource.
        return parent::getEloquentQuery()->onlyTrashed();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('typeDocument.libelle')->label('Type')->sortable(),
                TextColumn::make('sousTypeDocument.libelle')->label('Sous-type')->sortable()->toggleable(),
                TextColumn::make('nom_original')->label('Fichier')->searchable(),
                TextColumn::make('uploadePar.name')->label('Ajouté par')->sortable(),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record) => route('documents.telecharger', $record))
                    ->openUrlInNewTab(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->label('Supprimer définitivement'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorbeille::route('/'),
        ];
    }
}
