<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Prévient chaque administrateur qu'une purge automatique de la corbeille
 * (voir App\Console\Commands\PurgerCorbeilles, Administration > Paramètres
 * > Corbeilles) vient de supprimer DÉFINITIVEMENT au moins un élément.
 *
 * Contrairement à App\Notifications\AlerteSauvegardeNotification (qui
 * signale une condition PERSISTANTE — "la sauvegarde est en retard" — et
 * évite donc de notifier deux fois tant qu'elle n'est pas résolue), chaque
 * purge est un événement PONCTUEL et DISTINCT : une notification est donc
 * envoyée à chaque exécution qui supprime effectivement quelque chose, sans
 * garde-fou de déduplication (voir App\Console\Commands\VerifierAlertes::
 * notifierAdmins() pour le cas inverse).
 */
class PurgeCorbeillesNotification extends Notification
{
    public function __construct(protected string $description)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Purge automatique de la corbeille',
            'message' => $this->description,
            'icone' => 'bi-trash3-fill',
            'url' => route('gestion.corbeilles.index'),
        ];
    }
}
