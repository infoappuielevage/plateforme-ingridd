<?php

namespace App\Filament\Resources\TypeDocumentResource\RelationManagers;

use App\Support\ResourceAccess;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sous-types du type de document en cours d'édition (ex. "Succès",
 * "Vacation"... pour "Attestation") — voir App\Models\SousTypeDocument. Un
 * onglet dédié apparaît automatiquement en bas de la fiche d'un type de
 * document existant (jamais à la création : un type doit d'abord exister
 * avant de pouvoir lui rattacher des sous-types).
 *
 * Permissions calquées sur la ressource "types_documents" (pas de clé à part
 * pour les sous-types : gérer les sous-types d'un type fait partie de gérer
 * ce référentiel) — voir App\Support\ResourceAccess.
 *
 * Pas de corbeille ici (contrairement au reste des référentiels) :
 * suppression directe, protégée uniquement par ->nullOnDelete() sur
 * `documents.sous_type_document_id` (voir le docblock de SousTypeDocument
 * pour pourquoi ce garde-fou suffit).
 */
class SousTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'sousTypes';

    protected static ?string $title = 'Sous-types';

    protected static ?string $modelLabel = 'sous-type';

    protected static ?string $pluralModelLabel = 'sous-types';

    public function form(Form $form): Form
    {
        return $form->schema([
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
                ->helperText('Un sous-type inactif n\'apparaît plus dans le formulaire des documents, sans toucher aux documents déjà classés dedans.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('libelle')
            ->defaultSort('ordre')
            ->columns([
                TextColumn::make('libelle')->label('Libellé')->searchable(),
                TextColumn::make('ordre')->label('Ordre')->sortable(),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('documents_count')->label('Documents')->counts('documents'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => ResourceAccess::can('types_documents', 'create')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => ResourceAccess::can('types_documents', 'edit')),
                DeleteAction::make()
                    ->visible(fn () => ResourceAccess::can('types_documents', 'delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn () => ResourceAccess::can('types_documents', 'delete')),
            ]);
    }
}
