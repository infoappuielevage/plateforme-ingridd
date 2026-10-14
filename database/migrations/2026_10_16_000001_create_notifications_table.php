<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table standard des notifications Laravel (voir App\Models\User, qui
 * utilise déjà le trait Illuminate\Notifications\Notifiable) — alimente la
 * cloche de notifications de la barre de navigation (voir layouts/
 * gestion.blade.php, App\Http\Controllers\NotificationController) ainsi que
 * les notifications automatiques (blocage/déblocage de compte,
 * réinitialisation de mot de passe par un administrateur, alertes
 * sauvegarde/espace disque — voir app/Notifications/) et les messages
 * manuels envoyés par un administrateur depuis Administration > Messagerie
 * (voir App\Http\Controllers\MessagerieController).
 *
 * Reprend EXACTEMENT le schéma généré par `php artisan notifications:table`
 * dans n'importe quel projet Laravel : `id` en UUID (pas un entier
 * auto-incrémenté) pour que l'identifiant d'une notification ne révèle
 * jamais combien de notifications existent au total ni dans quel ordre, et
 * `notifiable_type`/`notifiable_id` (morphs) plutôt qu'une simple clé
 * `user_id` : seul App\Models\User est notifiable aujourd'hui, mais cette
 * table reste ainsi réutilisable sans migration supplémentaire si un autre
 * modèle (ex. une adresse de contact générique) devait un jour recevoir des
 * notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
