<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut "lu / non lu" d'une conversation, PAR PERSONNE qui y participe —
 * voir le docblock de 2026_10_25_000001_create_conversations_table.php.
 *
 * Une ligne par (conversation, utilisateur) qui a déjà ouvert cette
 * conversation au moins une fois. Nécessaire car "côté institution" est
 * partagé entre tous les administrateurs (voir le docblock de
 * App\Http\Controllers\MessagerieController) : si l'administrateur A ouvre
 * une conversation, cela ne doit PAS la marquer comme lue pour
 * l'administrateur B, qui ne l'a pas encore vue — chacun a donc son propre
 * `lu_jusqua`, comparé à `conversations.dernier_message_a` (voir
 * App\Models\Conversation::estNonLuePour()).
 *
 * Pas de clé primaire composite choisie : un simple id auto-incrémenté +
 * `unique(['conversation_id', 'user_id'])`, plus simple à manipuler avec
 * Eloquent (updateOrCreate(), notamment) qu'une clé composite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_jusqua')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_lectures');
    }
};
