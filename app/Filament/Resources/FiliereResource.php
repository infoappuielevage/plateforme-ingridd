<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasReferentielBulkActions;
use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\FiliereResource\Pages;
use App\Models\Filiere;
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

/** Référentiel des filières (ex. Informatique, Gestion...) — même pattern que TypeDocumentResource. */
class FiliereResource extends Resource
{
    use HasRoleBasedAccess, HasReferentielBulkActions;

    protected static ?string $model = Filiere::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Filières';

    protected static ?string $modelLabel = 'filière';

    protected static ?string $pluralModelLabel = 'filières';

    public static function resourceAccessKey(): string
    {
        return 'filieres';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Filiere $record) => $record !== null)
                ->dehydrated(fn (?Filiere $record) => $record === null),
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
                ->label('Active')
                ->default(true)
                ->helperText('Une filière inactive n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.'),
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
                IconColumn::make('actif')->label('Active')->boolean(),
                TextColumn::make('documents_count')->label('Documents')->counts('documents'),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Filiere $record) => ! $record->trashed()),
                DeleteAction::make()
                    ->label('Mettre à la corbeille')
                    ->visible(fn (Filiere $record) => ! $record->trashed() && $record->documents()->withTrashed()->doesntExist()),
                RestoreAction::make()
                    ->visible(fn (Filiere $record) => $record->trashed() && ResourceAccess::can('filieres', 'delete')),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (Filiere $record) => $record->trashed() && ResourceAccess::can('filieres', 'delete')),
            ])
            ->bulkActions(static::actionsGroupeesReferentiel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilieres::route('/'),
            'create' => Pages\CreateFiliere::route('/create'),
            'edit' => Pages\EditFiliere::route('/{record}/edit'),
        ];
    }
}
