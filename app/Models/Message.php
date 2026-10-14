<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un message dans une conversation de la messagerie — voir le docblock de la
 * migration 2026_10_25_000002_create_messages_table.php et
 * App\Models\Conversation.
 *
 * Volontairement SANS App\Models\Concerns\Auditable — voir le docblock de
 * Conversation.
 */
class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'auteur_id',
        'titre',
        'contenu',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** Voir le docblock de la migration : `auteur_id` passe à null si le compte a depuis été supprimé. */
    public function nomAuteur(): string
    {
        return $this->auteur?->name ?? 'Utilisateur supprimé';
    }
}
