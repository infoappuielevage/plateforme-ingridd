<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasReferentielBulkActions;
use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\TypeDocumentResource\Pages;
use App\Filament\Resources\TypeDocumentResource\RelationManagers\SousTypesRelationManager;
use App\Models\TypeDocument;
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

/** Référentiel des types de documents (ex. Contrat, Facture, Courrier...) — même logique que FiliereResource/NiveauResource. */
class TypeDocumentResource extends Resource
{
    use HasRoleBasedAccess, HasReferentielBulkActions;

    protected static ?string $model = TypeDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Types de documents';

    protected static ?string $modelLabel = 'type de document';

    protected static ?string $pluralModelLabel = 'types de documents';

    public static function resourceAccessKey(): string
    {
        return 'types_documents';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?TypeDocument $record) => $record !== null)
                ->dehydrated(fn (?TypeDocument $record) => $record === null),
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
                ->helperText('Un type inactif n\'apparaît plus dans les listes déroulantes, sans supprimer les documents déjà classés dedans.'),
            Toggle::make('epingle_secretariat')
                ->label('Épingler dans le menu "Secrétariat"')
                ->default(false)
                ->helperText('Ajoute une entrée dédiée à ce type (une par sous-type actif si ce type en a et affiche les champs Diplôme, sinon une entrée globale) dans le menu Secrétariat, en plus de la liste générale des documents.'),
            Toggle::make('champs_diplome_actifs')
                ->label('Champs "Diplôme/Attestation"')
                ->default(false)
                ->helperText('Affiche sur le formulaire : titulaire, numéro, date de délivrance, mention, statut de retrait.'),
            Toggle::make('champs_soutenance_actifs')
                ->label('Champs "PV de soutenance"')
                ->default(false)
                ->helperText('Affiche sur le formulaire : thème du mémoire, date de soutenance, membres du jury (en plus de titulaire/mention).'),
            Toggle::make('champ_semestre_actif')
                ->label('Champ "Semestre"')
                ->default(false)
                ->helperText('Affiche sur le formulaire le champ Semestre (dépendant du niveau sélectionné).'),
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
                IconColumn::make('epingle_secretariat')->label('Secrétariat')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('champs_diplome_actifs')->label('Champs diplôme')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('champs_soutenance_actifs')->label('Champs soutenance')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('champ_semestre_actif')->label('Semestre')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('documents_count')->label('Documents')->counts('documents'),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (TypeDocument $record) => ! $record->trashed()),
                DeleteAction::make()
                    ->label('Mettre à la corbeille')
                    ->visible(fn (TypeDocument $record) => ! $record->trashed() && $record->documents()->withTrashed()->doesntExist()),
                RestoreAction::make()
                    ->visible(fn (TypeDocument $record) => $record->trashed() && ResourceAccess::can('types_documents', 'delete')),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (TypeDocument $record) => $record->trashed() && ResourceAccess::can('types_documents', 'delete')),
            ])
            ->bulkActions(static::actionsGroupeesReferentiel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTypeDocuments::route('/'),
            'create' => Pages\CreateTypeDocument::route('/create'),
            'edit' => Pages\EditTypeDocument::route('/{record}/edit'),
        ];
    }

    /** Sous-types du type en cours d'édition — voir SousTypesRelationManager (n'apparaît que sur la page de modification, jamais à la création). */
    public static function getRelations(): array
    {
        return [
            SousTypesRelationManager::class,
        ];
    }
}
