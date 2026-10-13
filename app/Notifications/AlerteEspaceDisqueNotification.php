<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Prévient chaque administrateur que l'espace disque restant devient faible
 * — même condition d'alerte que la carte "Espace de stockage" du tableau de
 * bord (voir DashboardController::espaceDisque()), poussée en notification
 * pour ne pas dépendre d'ouvrir le tableau de bord pour s'en rendre compte.
 * Envoyée par App\Console\Commands\VerifierAlertes (planifiée
 * quotidiennement, voir routes/console.php), jamais en double tant qu'un
 * administrateur n'a pas déjà marqué la précédente comme lue (voir
 * VerifierAlertes::notifierAdmins()).
 */
class AlerteEspaceDisqueNotification extends Notification
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
            'titre' => 'Espace disque faible',
            'message' => $this->description,
            'icone' => 'bi-hdd-fill',
            'url' => route('gestion.dashboard'),
        ];
    }
}
