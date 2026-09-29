<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre de sauvegardes à conserver (les plus anciennes sont supprimées
 * au-delà) — voir App\Console\Commands\CreerSauvegarde et
 * App\Filament\Pages\Sauvegardes. Même logique que
 * `retention_audit_jours` : un réglage opérationnel modifiable après le
 * déploiement plutôt que codé en dur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->unsignedInteger('retention_sauvegardes_nombre')->default(10)->after('retention_audit_jours');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('retention_sauvegardes_nombre');
        });
    }
};
