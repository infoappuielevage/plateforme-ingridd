<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Formatage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

/**
 * Écran "Sauvegardes" (Administration > Sauvegardes, réservé en dur au rôle
 * admin — même principe que Users/Roles/Parametres : ce qui touche à la
 * sécurité/l'intégrité des données ne doit jamais dépendre d'une matrice de
 * permissions qui pourrait être mal configurée).
 *
 * Pas de Resource/Table Filament classique ici : les sauvegardes ne sont pas
 * des lignes en base, mais des fichiers sur le disque `sauvegardes` (voir
 * config/filesystems.php) — un rendu Blade simple (comme Paramètres) est
 * plus sûr qu'un détournement de Filament\Tables pour des données non
 * Eloquent.
 *
 * `creer()` appelle directement la commande `ingridd:sauvegarde` de façon
 * synchrone : pour un volume de documents raisonnable (établissement de
 * taille moyenne), la durée reste acceptable pour une requête web. Si le
 * volume grossit au point de dépasser le délai d'exécution PHP, faire
 * passer cette commande par une file d'attente (queue) deviendrait
 * nécessaire — hors périmètre de cette v1, voir README.md.
 */
class Sauvegardes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Sauvegardes';

    protected static ?string $title = 'Sauvegardes';

    protected static string $view = 'filament.pages.sauvegardes';

    public array $sauvegardes = [];

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
        $this->chargerListe();
    }

    protected function chargerListe(): void
    {
        $this->sauvegardes = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'))
            ->map(fn (string $fichier) => [
                'nom' => $fichier,
                'taille' => Formatage::octets(Storage::disk('sauvegardes')->size($fichier)),
                'date' => Carbon::createFromTimestamp(Storage::disk('sauvegardes')->lastModified($fichier))->format('d/m/Y H:i'),
                'horodatage' => Storage::disk('sauvegardes')->lastModified($fichier),
            ])
            ->sortByDesc('horodatage')
            ->values()
            ->all();
    }

    public function creer(): void
    {
        $code = Artisan::call('ingridd:sauvegarde');

        $this->chargerListe();

        if ($code === 0) {
            Notification::make()->title('Sauvegarde créée')->success()->send();
        } else {
            Notification::make()->title('Échec de la création de la sauvegarde')->danger()->send();
        }
    }

    public function supprimer(string $nom): void
    {
        // Le nom vient d'un appel Livewire côté client : on ne fait jamais
        // confiance à une valeur qui pourrait contenir un chemin ("../..")
        // avant de la passer à Storage::delete().
        abort_if(str_contains($nom, '/') || str_contains($nom, '\\'), 403);

        Storage::disk('sauvegardes')->delete($nom);

        $this->chargerListe();

        Notification::make()->title('Sauvegarde supprimée')->success()->send();
    }
}
