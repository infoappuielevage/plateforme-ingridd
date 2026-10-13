<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AlerteEspaceDisqueNotification;
use App\Notifications\AlerteSauvegardeNotification;
use App\Support\Formatage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Vérifie quotidiennement (voir routes/console.php) les deux mêmes alertes
 * déjà affichées en direct sur le tableau de bord de l'administrateur (voir
 * DashboardController::derniereSauvegarde()/espaceDisque()) et, si l'une
 * d'elles est active, prévient chaque administrateur par une notification
 * (cloche, voir layouts/gestion.blade.php) — demande explicite du client, qui
 * ne voulait pas dépendre d'ouvrir le tableau de bord pour s'en rendre compte.
 *
 * Ne notifie jamais deux fois la MÊME alerte tant qu'un administrateur n'a
 * pas déjà marqué la précédente comme lue (voir notifierAdmins()) : sinon,
 * tournant chaque jour, cette commande empilerait une notification
 * identique quotidiennement tant que le problème sous-jacent n'est pas
 * résolu.
 *
 * ⚠️ Comme la sauvegarde automatique (voir App\Console\Commands\
 * CreerSauvegarde), cette commande ne s'exécute que si le planificateur
 * Laravel tourne réellement sur le serveur.
 */
class VerifierAlertes extends Command
{
    protected $signature = 'ingridd:verifier-alertes';

    protected $description = "Notifie les administrateurs si la dernière sauvegarde est en retard/absente ou si l'espace disque devient faible";

    public function handle(): int
    {
        $this->verifierSauvegarde();
        $this->verifierEspaceDisque();

        return self::SUCCESS;
    }

    /** Même condition d'alerte que DashboardController::derniereSauvegarde() (absente, ou vieille de plus d'une semaine). */
    protected function verifierSauvegarde(): void
    {
        $derniere = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'))
            ->map(fn (string $fichier) => Storage::disk('sauvegardes')->lastModified($fichier))
            ->sort()
            ->last();

        if (! $derniere) {
            $this->notifierAdmins(AlerteSauvegardeNotification::class, "Aucune sauvegarde n'a encore été créée.");

            return;
        }

        $date = Carbon::createFromTimestamp($derniere);

        if ($date->lt(now()->subWeek())) {
            $this->notifierAdmins(
                AlerteSauvegardeNotification::class,
                'La dernière sauvegarde remonte au '.$date->format('d/m/Y H:i').'.'
            );
        }
    }

    /** Même condition d'alerte que DashboardController::espaceDisque() (moins de 10% ou moins de 2 Go libres). */
    protected function verifierEspaceDisque(): void
    {
        $chemin = Storage::disk('documents')->path('');
        $libre = @disk_free_space($chemin);
        $total = @disk_total_space($chemin);

        if ($libre === false || $total === false || $total <= 0) {
            return;
        }

        $pourcentageLibre = ($libre / $total) * 100;

        if ($pourcentageLibre < 10 || $libre < 2 * 1024 ** 3) {
            $this->notifierAdmins(
                AlerteEspaceDisqueNotification::class,
                'Il ne reste que '.Formatage::octets((int) $libre).' libres sur le disque ('.round(100 - $pourcentageLibre).'% utilisé).'
            );
        }
    }

    /**
     * Notifie chaque administrateur, sauf s'il a déjà une notification NON
     * LUE de ce même type — voir le docblock de la classe pour pourquoi
     * (sans ce garde-fou, une alerte non résolue serait renvoyée chaque
     * jour par la planification).
     */
    protected function notifierAdmins(string $classeNotification, string $description): void
    {
        User::query()->where('role', User::ROLE_ADMIN)->get()->each(function (User $admin) use ($classeNotification, $description) {
            $dejaNonLue = $admin->unreadNotifications()->where('type', $classeNotification)->exists();

            if (! $dejaNonLue) {
                $admin->notify(new $classeNotification($description));
            }
        });
    }
}
