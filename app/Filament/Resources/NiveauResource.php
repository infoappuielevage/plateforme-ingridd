<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasReferentielBulkActions;
use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\NiveauResource\Pages;
use App\Models\Niveau;
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

/** Référentiel des niveaux (ex. Licence 1, Licence 2, Master 1...) — même pattern que TypeDocumentResource. */
class NiveauResource extends Resource
{
    use HasRoleBasedAccess, HasReferentielBulkActions;

    protected static ?string $model = Niveau::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-arrow-up';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Niveaux';

    protected static ?string $modelLabel = 'niveau';

    protected static ?string $pluralModelLabel = 'niveaux';

    public static function resourceAccessKey(): string
    {
        return 'niveaux';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Niveau $record) => $record !== null)
                ->dehydrated(fn (?Niveau $record) => $record === null),
            TextInput::make('libelle')
                ->label('Libellé')
                ->required()
                ->maxLength(150),
            TextInput::make('ordre')
                ->label('Ordre d\'affichage')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('actif')
                ->label('Actif')
                ->default(true)
                ->helperText('Un niveau inactif n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre')
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('libelle')->label('Libellé')->searchable(),
                TextColumn::make('ordre')->label('Ordre')->sortable(),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('documents_count')->label('Documents')->counts('documents'),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Niveau $record) => ! $record->trashed()),
                DeleteAction::make()
                    ->label('Mettre à la corbeille')
                    ->visible(fn (Niveau $record) => ! $record->trashed() && $record->documents()->withTrashed()->doesntExist()),
                RestoreAction::make()
                    ->visible(fn (Niveau $record) => $record->trashed() && ResourceAccess::can('niveaux', 'delete')),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (Niveau $record) => $record->trashed() && ResourceAccess::can('niveaux', 'delete')),
            ])
            ->bulkActions(static::actionsGroupeesReferentiel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNiveaux::route('/'),
            'create' => Pages\CreateNiveau::route('/create'),
            'edit' => Pages\EditNiveau::route('/{record}/edit'),
        ];
    }
}
