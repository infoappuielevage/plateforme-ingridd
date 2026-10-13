<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Annonce en texte libre, rédigée et envoyée par un administrateur depuis
 * Administration > Annonces (voir App\Http\Controllers\AnnonceController) à
 * un utilisateur précis, à tous les utilisateurs d'un rôle, ou à tout le
 * monde — demande explicite du client, qui voulait pouvoir "gérer" les
 * notifications plutôt que de se limiter aux seules alertes automatiques
 * (blocage de compte, réinitialisation de mot de passe, sauvegarde/espace
 * disque — voir les autres classes de ce dossier).
 */
class AnnonceAdministrateurNotification extends Notification
{
    public function __construct(protected string $titre, protected string $message)
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
            'url' => null,
        ];
    }
}
