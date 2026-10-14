<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Réponse envoyée dans une conversation déjà existante de la messagerie
 * (voir App\Http\Controllers\MessagerieController::repondre()) — distincte
 * de App\Notifications\AnnonceAdministrateurNotification, qui ne sert
 * qu'au tout premier message d'une conversation (voir son docblock).
 *
 * Pointe toujours vers la conversation (`url`) : cliquer la notification
 * dans la cloche doit amener directement sur l'échange, pas seulement
 * afficher son contenu.
 */
class NouveauMessageNotification extends Notification
{
    public function __construct(protected Message $message)
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
            'titre' => 'Nouveau message de '.$this->message->nomAuteur(),
            'message' => Str::limit($this->message->contenu, 150),
            'icone' => 'bi-chat-dots-fill',
            'url' => route('gestion.messagerie.afficher', $this->message->conversation_id),
        ];
    }
}
