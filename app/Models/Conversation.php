<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une conversation de la messagerie (Administration > Messagerie) — voir le
 * docblock de la migration 2026_10_25_000001_create_conversations_table.php
 * et App\Http\Controllers\MessagerieController pour le contexte complet.
 *
 * Volontairement SANS App\Models\Concerns\Auditable : contrairement aux
 * autres modèles du projet, le contenu d'une conversation est privé entre
 * ses deux participants (l'utilisateur et "l'institution", voir le
 * docblock du contrôleur) — il ne doit jamais apparaître dans Administration
 * > Historique, consultable par des comptes qui n'ont rien à voir avec
 * l'échange.
 */
class Conversation extends Model
{
    protected $fillable = [
        'utilisateur_id',
        'dernier_message_a',
    ];

    protected function casts(): array
    {
        return [
            'dernier_message_a' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /**
     * SANS `orderBy()` ici, volontairement : un `orderBy('id')` posé sur la
     * relation elle-même s'ADDITIONNE à tout tri appliqué ensuite par un
     * appelant (ex. `->latest('id')`) au lieu de le remplacer — deux clauses
     * ORDER BY sur la même colonne, la première imposée ici l'emportant
     * silencieusement. Chaque appelant applique donc son propre tri
     * explicite : `orderBy('id')` dans MessagerieController::afficher()
     * (eager loading, ordre chronologique d'affichage) et ::messagesRecents()
     * (même raison), `latestOfMany()` via dernierMessage() ci-dessous pour
     * la boîte de réception.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Dernier message de la conversation — utilisé dans la liste de la boîte de réception. */
    public function dernierMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(ConversationLecture::class);
    }

    /**
     * Vrai si `$utilisateur` n'a pas encore vu le dernier message de cette
     * conversation. Toujours faux si ce dernier message est le sien (on
     * n'attend pas de soi-même qu'on "relise" ce qu'on vient d'écrire) ou si
     * la conversation est encore vide.
     *
     * Suppose `dernierMessage` et `lectures` déjà chargées (eager loading,
     * voir MessagerieController) pour éviter une requête par conversation
     * dans la liste de la boîte de réception.
     */
    public function estNonLuePour(User $utilisateur): bool
    {
        if (! $this->dernier_message_a) {
            return false;
        }

        $dernierMessage = $this->relationLoaded('dernierMessage')
            ? $this->dernierMessage
            : $this->dernierMessage()->first();

        if (! $dernierMessage || $dernierMessage->auteur_id === $utilisateur->id) {
            return false;
        }

        $lecture = $this->relationLoaded('lectures')
            ? $this->lectures->firstWhere('user_id', $utilisateur->id)
            : $this->lectures()->where('user_id', $utilisateur->id)->first();

        return ! $lecture || ! $lecture->lu_jusqua || $lecture->lu_jusqua->lt($this->dernier_message_a);
    }
}
