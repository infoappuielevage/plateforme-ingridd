<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Prévient chaque administrateur que la dernière sauvegarde est absente ou
 * en retard — même condition d'alerte que la carte "Dernière sauvegarde" du
 * tableau de bord (voir DashboardController::derniereSauvegarde()), mais
 * poussée en notification pour ne pas dépendre d'ouvrir le tableau de bord
 * pour s'en rendre compte. Envoyée par App\Console\Commands\VerifierAlertes
 * (planifiée quotidiennement, voir routes/console.php), jamais en double
 * tant qu'un administrateur n'a pas déjà marqué la précédente comme lue
 * (voir VerifierAlertes::notifierAdmins()).
 */
class AlerteSauvegardeNotification extends Notification
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
            'titre' => 'Sauvegarde en retard',
            'message' => $this->description,
            'icone' => 'bi-cloud-arrow-up-fill',
            'url' => route('gestion.sauvegardes.index'),
        ];
    }
}
