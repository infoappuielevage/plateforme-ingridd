<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jusqu'où (`lu_jusqua`) une personne précise a lu une conversation — voir
 * le docblock de la migration
 * 2026_10_25_000003_create_conversation_lectures_table.php et
 * App\Models\Conversation::estNonLuePour().
 */
class ConversationLecture extends Model
{
    protected $table = 'conversation_lectures';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'lu_jusqua',
    ];

    protected function casts(): array
    {
        return [
            'lu_jusqua' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
