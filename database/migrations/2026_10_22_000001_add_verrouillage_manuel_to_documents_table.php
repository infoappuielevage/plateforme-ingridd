<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrouillage MANUEL d'un document — demande explicite du client : en plus
 * des deux seuils automatiques (année académique / date, voir la migration
 * 2026_10_21_000001) et du déverrouillage explicite (2026_10_21_000002), il
 * manquait un moyen de verrouiller un document À LA DEMANDE, qui ne tombe
 * sous aucun des deux seuils réglés dans Administration > Paramètres.
 *
 * Ces deux colonnes et `deverrouille_le`/`deverrouille_par_id` sont
 * mutuellement exclusives : verrouiller EFFACE `deverrouille_le`/
 * `deverrouille_par_id`, et déverrouiller efface `verrouille_le`/
 * `verrouille_par_id` (voir App\Http\Controllers\DocumentController::
 * verrouiller()/deverrouiller()) — à tout instant, au plus une des deux
 * paires est renseignée, ce qui évite toute ambiguïté dans
 * App\Support\Verrouillage::estVerrouille().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('verrouille_le')->nullable()->after('deverrouille_par_id');
            $table->foreignId('verrouille_par_id')->nullable()->after('verrouille_le')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verrouille_par_id');
            $table->dropColumn('verrouille_le');
        });
    }
};
