<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocage d'un compte par un administrateur — demande explicite du client
 * ("l'administration doit pouvoir réinitialiser un compte ou le bloquer").
 * Voir App\Models\User::estBloque(), App\Http\Controllers\UserController::
 * basculerBlocage() et App\Http\Middleware\RejeterUtilisateurBloque (qui
 * déconnecte immédiatement un compte bloqué, même en cours de session).
 *
 * Un simple timestamp nullable (plutôt qu'un booléen) : `bloque_at` garde la
 * date du blocage, utile pour l'historique (voir AuditLog), sans colonne
 * supplémentaire. NULL = compte actif (valeur par défaut, aucun utilisateur
 * existant n'est affecté par cette migration).
 *
 * Volontairement ABSENTE de User::$fillable (comme `avatar` et
 * `derniere_connexion_at`) : ne doit jamais pouvoir être modifiée par un
 * simple ->update() de masse (ex. UserController::update(), le formulaire de
 * modification d'un compte) — uniquement via forceFill() dans
 * basculerBlocage(), l'unique porte d'entrée pour ce réglage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('bloque_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bloque_at');
        });
    }
};
