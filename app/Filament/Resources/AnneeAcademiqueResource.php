<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasReferentielBulkActions;
use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\AnneeAcademiqueResource\Pages;
use App\Models\AnneeAcademique;
use App\Support\ResourceAccess;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Référentiel des années académiques (ex. "2025-2026") — permet que le champ
 * correspondant sur Document soit une liste déroulante plutôt qu'un champ de
 * saisie libre (voir DocumentResource::form()). Même pattern que
 * TypeDocument/Filiere/Niveau, avec un seul champ `libelle` (pas
 * de `code` séparé) et un tri par défaut du plus récent au plus ancien.
 */
class AnneeAcademiqueResource extends Resource
{
    use HasRoleBasedAccess, HasReferentielBulkActions;

    protected static ?string $model = AnneeAcademique::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Années académiques';

    protected static ?string $modelLabel = 'année académique';

    protected static ?string $pluralModelLabel = 'années académiques';

    public static function resourceAccessKey(): string
    {
        return 'annees_academiques';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('libelle')
                ->label('Année académique')
                ->placeholder('2025-2026')
                ->required()
                ->maxLength(9)
                ->unique(ignoreRecord: true)
                ->regex('/^\d{4}-\d{4}$/')
                ->validationMessages([
                    'regex' => 'L\'année académique doit être au format AAAA-AAAA, par exemple 2025-2026.',
                ])
                ->helperText('Format AAAA-AAAA (ex. 2025-2026).'),
            Toggle::make('actif')
                ->label('Actif')
                ->default(true)
                ->helperText('Une année inactive n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('libelle', 'desc')
            ->columns([
                TextColumn::make('libelle')->label('Année académique')->searchable(),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('documents_count')->label('Documents')->counts('documents'),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (AnneeAcademique $record) => ! $record->trashed()),
                DeleteAction::make()
                    ->label('Mettre à la corbeille')
                    ->visible(fn (AnneeAcademique $record) => ! $record->trashed() && $record->documents()->withTrashed()->doesntExist()),
                RestoreAction::make()
                    ->visible(fn (AnneeAcademique $record) => $record->trashed() && ResourceAccess::can('annees_academiques', 'delete')),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (AnneeAcademique $record) => $record->trashed() && ResourceAccess::can('annees_academiques', 'delete')),
            ])
            ->bulkActions(static::actionsGroupeesReferentiel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnneesAcademiques::route('/'),
            'create' => Pages\CreateAnneeAcademique::route('/create'),
            'edit' => Pages\EditAnneeAcademique::route('/{record}/edit'),
        ];
    }
}
