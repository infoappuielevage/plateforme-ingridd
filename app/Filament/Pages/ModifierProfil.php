<?php

namespace App\Filament\Pages;

use App\Models\Parametre;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * "Mon profil" — repris tel quel du correctif de sécurité appliqué sur
 * ingridd-platform : `->avatar()` applique déjà `->image()`, qui autorise
 * "image/*" — ce qui inclut "image/svg+xml". Un SVG est un format texte
 * pouvant contenir du JavaScript (balise <script> ou attributs on*) : le
 * laisser passer comme "image" ouvrait une faille XSS stockée (le fichier
 * est servi tel quel par le navigateur qui l'affiche). D'où la restriction
 * explicite à des formats matriciels sans risque de script.
 */
class ModifierProfil extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.modifier-profil';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Auth::user()->only(['name', 'email', 'avatar']));
    }

    public function form(Form $form): Form
    {
        // Lu une fois ici plutôt que dans une Closure passée à
        // ->maxSize()/->helperText() — plus simple, et évite de dépendre de
        // la prise en charge des Closures par ces méthodes précises.
        $tailleMaxMo = Parametre::actuel()->taille_max_avatar_mo;

        return $form->schema([
            Section::make('Informations')
                ->schema([
                    FileUpload::make('avatar')
                        ->label('')
                        ->avatar()
                        // Voir le docblock de la classe : restriction volontaire,
                        // sans laquelle un SVG serait accepté comme "image/*".
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->disk('public')
                        ->directory('avatars')
                        ->visibility('public')
                        ->maxSize($tailleMaxMo * 1024)
                        ->helperText("PNG, JPEG ou WebP, carrée de préférence ({$tailleMaxMo} Mo maximum). Laissez vide pour garder vos initiales."),
                    TextInput::make('name')
                        ->label('Nom complet')
                        ->required()
                        ->maxLength(150),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique('users', 'email', ignorable: Auth::user())
                        ->maxLength(150),
                ]),
            Section::make('Mot de passe')
                ->schema([
                    TextInput::make('mot_de_passe_actuel')
                        ->label('Mot de passe actuel')
                        ->password()
                        ->revealable()
                        ->currentPassword()
                        ->requiredWith('nouveau_mot_de_passe'),
                    TextInput::make('nouveau_mot_de_passe')
                        ->label('Nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->rule(\Illuminate\Validation\Rules\Password::default())
                        ->dehydrated(false)
                        ->confirmed(),
                    TextInput::make('nouveau_mot_de_passe_confirmation')
                        ->label('Confirmer le nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->dehydrated(false),
                ])
                ->description('Laissez ces champs vides pour ne pas changer votre mot de passe.'),
            Placeholder::make('derniere_connexion')
                ->label('Dernière connexion')
                ->content(fn () => Auth::user()->derniere_connexion_at?->format('d/m/Y H:i').' depuis '.(Auth::user()->derniere_connexion_ip ?? '—') ?: 'Jamais'),
        ])->statePath('data');
    }

    public function enregistrer(): void
    {
        $donnees = $this->form->getState();

        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        $nouvelAvatar = $donnees['avatar'] ?? $utilisateur->avatar;
        $ancienAvatar = $utilisateur->avatar;

        $utilisateur->fill([
            'name' => $donnees['name'],
            'email' => $donnees['email'],
            'avatar' => $nouvelAvatar,
        ]);

        if (filled($donnees['nouveau_mot_de_passe'] ?? null)) {
            $utilisateur->password = Hash::make($donnees['nouveau_mot_de_passe']);
        }

        $utilisateur->save();

        // Supprime l'ancienne photo de profil si elle a été remplacée — sans
        // ça, `storage/app/public/avatars` accumule des fichiers orphelins.
        if ($ancienAvatar && $ancienAvatar !== $nouvelAvatar) {
            Storage::disk('public')->delete($ancienAvatar);
        }

        Notification::make()
            ->title('Profil mis à jour')
            ->success()
            ->send();
    }
}
