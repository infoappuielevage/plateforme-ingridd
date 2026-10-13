<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Prévient un utilisateur qu'un administrateur a demandé la réinitialisation
 * de SON mot de passe (voir UserController::reinitialiserMotDePasse(), qui
 * réutilise le flux standard "mot de passe oublié" — l'administrateur ne
 * voit ni ne choisit jamais le mot de passe lui-même). Simple rappel dans la
 * cloche de notifications : le lien de réinitialisation, lui, part toujours
 * par email (Illuminate\Auth\Notifications\ResetPassword, déclenché par
 * Password::sendResetLink()), pas par cette notification.
 */
class MotDePasseReinitialiseParAdminNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Mot de passe réinitialisé',
            'message' => 'Un administrateur a demandé la réinitialisation de votre mot de passe. Consultez votre email pour le lien de réinitialisation.',
            'icone' => 'bi-key-fill',
            'url' => null,
        ];
    }
}
