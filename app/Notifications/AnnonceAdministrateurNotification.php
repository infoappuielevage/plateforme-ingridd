<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Premier message d'une conversation, rédigé et envoyé par un administrateur
 * depuis Administration > Messagerie > Nouveau message (voir
 * App\Http\Controllers\MessagerieController::envoyerNouveau()) à un
 * utilisateur précis, à tous les utilisateurs d'un rôle, ou à tout le
 * monde — demande explicite du client, qui voulait pouvoir "gérer" les
 * notifications plutôt que de se limiter aux seules alertes automatiques
 * (blocage de compte, réinitialisation de mot de passe, sauvegarde/espace
 * disque — voir les autres classes de ce dossier).
 *
 * Pour une RÉPONSE dans une conversation déjà existante, voir plutôt
 * App\Notifications\NouveauMessageNotification : deux classes distinctes
 * pour ne pas réétiqueter cette notification-ci après coup (son nom reste
 * celui d'origine, repris tel quel par tests/Feature/NotificationControllerTest.php).
 */
class AnnonceAdministrateurNotification extends Notification
{
    public function __construct(protected string $titre, protected string $message, protected ?string $url = null)
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
            'titre' => $this->titre,
            'message' => $this->message,
            'icone' => 'bi-megaphone-fill',
            'url' => $this->url,
        ];
    }
}
