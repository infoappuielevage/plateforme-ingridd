<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion des comptes — réservée en dur au rôle "admin", comme RoleResource
 * (voir la note dans App\Support\ResourceAccess).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'utilisateurs';

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
        // Un administrateur ne peut pas se supprimer lui-même (risque de se
        // retrouver sans aucun compte admin restant).
        return self::estAdmin() && $record->id !== Auth::id();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nom complet')
                ->required()
                ->maxLength(150),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),
            Select::make('role')
                ->label('Rôle')
                ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'slug'))
                ->required(),
            TextInput::make('password')
                ->label('Mot de passe')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText('Laisser vide pour ne pas modifier le mot de passe existant.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Rôle')->badge(),
                TextColumn::make('derniere_connexion_at')->label('Dernière connexion')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
