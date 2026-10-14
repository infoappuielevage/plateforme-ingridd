<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie (Administration > Messagerie) — remplace l'ancien écran
 * "Annonces" (App\Http\Controllers\AnnonceController, désormais supprimé) :
 * demande explicite du client, qui voulait que ce soit "comme une
 * messagerie" plutôt qu'un simple envoi à sens unique dans la cloche de
 * notifications. Voir App\Http\Controllers\MessagerieController.
 *
 * Une conversation = UN compte "côté institution" (n'importe quel
 * administrateur, voir le docblock de MessagerieController) + UN utilisateur
 * précis (`utilisateur_id`), jamais plus : pas de messagerie entre deux
 * utilisateurs non-admin entre eux, et pas de "groupe" à plusieurs
 * destinataires dans une même conversation. Quand un administrateur envoie
 * un message à "tout le monde" ou "un rôle" depuis Nouveau message, cela crée
 * UNE conversation PAR destinataire (voir
 * MessagerieController::envoyerNouveau()) plutôt qu'une seule conversation
 * partagée — pour que chaque utilisateur ne voie jamais les réponses des
 * autres, et que tous les administrateurs partagent la même boîte de
 * réception (ils voient et peuvent répondre à TOUTES les conversations, pas
 * seulement celles qu'ils ont eux-mêmes initiées).
 *
 * `unique('utilisateur_id')` : un utilisateur donné n'a jamais qu'UNE seule
 * conversation avec l'institution dans le temps (elle se poursuit, elle ne
 * se recrée pas à chaque nouvelle annonce) — voir firstOrCreate() dans le
 * contrôleur.
 *
 * `dernier_message_a` dénormalise la date du dernier message (plutôt que de
 * recalculer un MAX(messages.created_at) à chaque affichage de la boîte de
 * réception) : sert à la fois à trier la liste des conversations et à
 * détecter les conversations non lues (voir Conversation::estNonLuePour()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('dernier_message_a')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
