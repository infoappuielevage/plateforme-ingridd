<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Models\User;
use App\Support\ResourceAccess;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Écran "Rôles & permissions" — volontairement réservé en dur au rôle
 * "admin" (voir la note en tête de App\Support\ResourceAccess) : un rôle mal
 * configuré ne doit jamais pouvoir s'accorder lui-même l'accès à cet écran.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Rôles & permissions';

    protected static ?string $modelLabel = 'rôle';

    protected static ?string $pluralModelLabel = 'rôles';

    protected static function estAdmin(): bool
    {
        return Auth::user()?->role === User::ROLE_ADMIN;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::estAdmin();
    }

    public static function canViewAny(): bool
    {
        return self::estAdmin();
    }

    public static function canCreate(): bool
    {
        return self::estAdmin();
    }

    public static function canEdit($record): bool
    {
        return self::estAdmin();
    }

    public static function canDelete($record): bool
    {
        return self::estAdmin();
    }

    public static function form(Form $form): Form
    {
        // Section (pas Fieldset) : c'est Section qui supporte ->icon() dans
        // Filament v3 — un Fieldset n'a pas cette méthode.
        $sections = [];

        foreach (ResourceAccess::RESSOURCES as $cle => $libelle) {
            $toggles = [];

            foreach (ResourceAccess::ACTIONS as $action => $libelleAction) {
                // Historique : uniquement "Voir" a un sens (lecture seule partout).
                if ($cle === 'audit_logs' && $action !== 'view') {
                    continue;
                }

                $toggles[] = Toggle::make("permissions.{$cle}.{$action}")
                    ->label($libelleAction)
                    ->inline(false);
            }

            $sections[] = Section::make($libelle)
                ->icon(ResourceAccess::ICONES[$cle] ?? 'heroicon-o-squares-2x2')
                ->schema($toggles)
                ->columns(4)
                ->collapsible();
        }

        return $form->schema([
            TextInput::make('slug')
                ->label('Identifiant (slug)')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record !== null)
                ->dehydrated(fn (?Role $record) => $record === null)
                ->helperText('Doit correspondre à la valeur du champ "Rôle" d\'un utilisateur (voir UserResource). Non modifiable après création.'),
            TextInput::make('name')
                ->label('Nom affiché')
                ->required()
                ->maxLength(100),
            ...$sections,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label('Identifiant')->searchable(),
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('users_count')->label('Utilisateurs')->counts('users'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record) => ! in_array($record->slug, [User::ROLE_ADMIN], true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
