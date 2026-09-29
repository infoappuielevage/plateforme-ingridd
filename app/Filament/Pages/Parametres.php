<?php

namespace App\Filament\Pages;

use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Écran "Paramètres" — tout ce qui était auparavant codé en dur (nom de
 * l'application, couleur, tailles maximales d'upload, durée de rétention de
 * l'historique) devient modifiable ici après le déploiement, sans jamais
 * avoir besoin de retoucher au code ou de redéployer.
 *
 * Réservé en dur au rôle admin (même principe que RoleResource/UserResource) :
 * la durée de rétention de l'historique, en particulier, est un réglage
 * sensible pour la traçabilité — pas question qu'un rôle mal configuré
 * puisse se l'accorder lui-même.
 */
class Parametres extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Paramètres';

    protected static ?string $title = 'Paramètres';

    protected static string $view = 'filament.pages.parametres';

    public ?array $data = [];

    protected static function estAdmin(): bool
    {
        return Auth::user()?->role === User::ROLE_ADMIN;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::estAdmin();
    }

    public static function canAccess(): bool
    {
        return self::estAdmin();
    }

    public function mount(): void
    {
        $parametres = Parametre::actuel();

        $this->form->fill([
            ...$parametres->only([
                'nom_application',
                'logo',
                'couleur_principale',
                'taille_max_document_mo',
                'taille_max_avatar_mo',
                'retention_audit_jours',
                'retention_sauvegardes_nombre',
                'mysqldump_chemin',
                'frequence_sauvegarde_auto',
            ]),
            // Jamais vide dans le formulaire, même si la colonne est encore
            // NULL en base (ligne créée avant ce réglage) — voir
            // Parametre::typesFichiersAutorisesEffectifs().
            'types_fichiers_autorises' => $parametres->typesFichiersAutorisesEffectifs(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identité de l\'application')
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('nom_application')
                        ->label('Nom affiché')
                        ->required()
                        ->maxLength(150),
                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->disk('public')
                        ->directory('logo')
                        ->visibility('public')
                        ->maxSize(1024)
                        ->helperText('Laissez vide pour garder le logo par défaut. 1 Mo maximum — un SVG est accepté ici (contrairement à la photo de profil) car son contenu est fourni par un administrateur de confiance, pas par n\'importe quel utilisateur.'),
                    ColorPicker::make('couleur_principale')
                        ->label('Couleur principale')
                        ->required(),
                ]),
            Section::make('Types de fichiers acceptés')
                ->icon('heroicon-o-document-check')
                ->description('Familles de formats autorisées à l\'ajout d\'un document. Décochez une famille pour la refuser sans toucher au code — voir Administration > Documents.')
                ->schema([
                    CheckboxList::make('types_fichiers_autorises')
                        ->label('Familles acceptées')
                        ->options(TypesFichiers::optionsPourFormulaire())
                        ->columns(2)
                        ->required()
                        ->helperText('Au moins une famille doit rester cochée. Une famille entièrement nouvelle (ex. audio/vidéo) reste à demander : cette liste est volontairement fermée, par sécurité.'),
                ]),
            Section::make('Limites d\'upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->description('Exprimées en méga-octets (Mo).')
                ->schema([
                    TextInput::make('taille_max_document_mo')
                        ->label('Taille maximale d\'un document')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(500)
                        ->suffix('Mo')
                        ->required(),
                    TextInput::make('taille_max_avatar_mo')
                        ->label('Taille maximale d\'une photo de profil')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->suffix('Mo')
                        ->required(),
                ]),
            Section::make('Historique (audit)')
                ->icon('heroicon-o-clock')
                ->schema([
                    TextInput::make('retention_audit_jours')
                        ->label('Purge automatique après')
                        ->numeric()
                        ->minValue(30)
                        ->suffix('jours')
                        ->required()
                        ->helperText('Les entrées de l\'historique plus anciennes que cette durée sont supprimées automatiquement chaque jour (voir routes/console.php).'),
                ]),
            Section::make('Sauvegardes')
                ->icon('heroicon-o-archive-box')
                ->schema([
                    Select::make('frequence_sauvegarde_auto')
                        ->label('Fréquence de la sauvegarde automatique')
                        ->options([
                            'quotidienne' => 'Quotidienne',
                            'hebdomadaire' => 'Hebdomadaire (chaque lundi)',
                            'mensuelle' => 'Mensuelle (le 1er du mois)',
                        ])
                        ->required()
                        ->helperText('Nécessite que le planificateur Laravel tourne réellement sur le serveur (voir README.md) — sinon, seule la sauvegarde manuelle (Administration > Sauvegardes) fonctionne.'),
                    TextInput::make('retention_sauvegardes_nombre')
                        ->label('Nombre de sauvegardes à conserver')
                        ->numeric()
                        ->minValue(1)
                        ->suffix('sauvegardes')
                        ->required()
                        ->helperText('Au-delà de ce nombre, les sauvegardes les plus anciennes sont supprimées automatiquement à chaque nouvelle sauvegarde.'),
                    TextInput::make('mysqldump_chemin')
                        ->label('Chemin de l\'exécutable mysqldump')
                        ->maxLength(255)
                        ->helperText('Laissez vide pour utiliser "mysqldump" depuis le PATH système (suffisant la plupart du temps). À renseigner uniquement si la sauvegarde échoue avec un message "mysqldump introuvable" — ex. C:\\laragon\\bin\\mysql\\mysql-8.x\\bin\\mysqldump.exe. Sans effet si la base de données utilisée est SQLite.'),
                ]),
        ])->statePath('data');
    }

    public function enregistrer(): void
    {
        $donnees = $this->form->getState();

        Parametre::actuel()->fill($donnees)->save();
        Parametre::oublierCache();

        Notification::make()
            ->title('Paramètres enregistrés')
            ->success()
            ->send();
    }
}
