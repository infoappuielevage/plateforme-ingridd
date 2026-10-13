<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Prévient un utilisateur que SON compte vient d'être bloqué ou débloqué par
 * un administrateur (voir UserController::basculerBlocage()) — stockée
 * uniquement en base (cloche de notifications, voir layouts/gestion.blade.php
 * et NotificationController), jamais par email : contrairement à la
 * réinitialisation de mot de passe, rien n'exige ici d'agir depuis une boîte
 * mail, et un compte tout juste bloqué ne peut de toute façon plus se
 * connecter pour lire quoi que ce soit (voir App\Http\Middleware\
 * RejeterUtilisateurBloque) — le message attend simplement d'être vu au
 * prochain déblocage, ou sert d'explication a posteriori.
 */
class CompteBloqueNotification extends Notification
{
    public function __construct(protected bool $bloque)
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
        return $this->bloque
            ? [
                'titre' => 'Compte bloqué',
                'message' => 'Votre compte a été bloqué par un administrateur.',
                'icone' => 'bi-lock-fill',
                'url' => null,
            ]
            : [
                'titre' => 'Compte débloqué',
                'message' => 'Votre compte a été débloqué par un administrateur. Vous pouvez de nouveau vous connecter.',
                'icone' => 'bi-unlock-fill',
                'url' => null,
            ];
    }
}
