<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages d'une conversation — voir le docblock de
 * 2026_10_25_000001_create_conversations_table.php et
 * App\Http\Controllers\MessagerieController.
 *
 * `auteur_id` nullable + `nullOnDelete()` (contrairement à
 * `conversations.utilisateur_id`, qui supprime la conversation entière en
 * cascade) : si le compte d'un administrateur ou d'un utilisateur qui a
 * participé à une conversation est un jour supprimé
 * (App\Http\Controllers\UserController::destroy()), l'historique des
 * échanges doit survivre — seul l'auteur du message devient inconnu (voir
 * Message::nomAuteur(), qui affiche alors "Utilisateur supprimé").
 *
 * `titre` nullable : uniquement renseigné sur le premier message d'une
 * conversation envoyé depuis Nouveau message (objet de l'annonce d'origine,
 * affiché en gras au-dessus de la bulle) — jamais sur une simple réponse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre')->nullable();
            $table->text('contenu');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
