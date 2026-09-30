<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Miroir de 2026_10_03_000002_add_champ_supplementaire_id_to_session_deliberation_fichiers_table.php,
 * appliqué ici à session_releve_fichiers — voir son docblock pour le détail
 * complet des choix (nullOnDelete, contrainte unique recréée avec la colonne
 * en plus, index intermédiaire nécessaire sur MySQL/InnoDB avant de retirer
 * l'ancien index unique, garde Schema::hasColumn() pour rester rejouable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('session_releve_fichiers', 'champ_supplementaire_id')) {
            Schema::table('session_releve_fichiers', function (Blueprint $table) {
                $table->foreignId('champ_supplementaire_id')->nullable()->after('numero')
                    ->constrained('champs_supplementaires')->nullOnDelete();
            });
        }

        Schema::table('session_releve_fichiers', function (Blueprint $table) {
            $table->index('session_releve_id', 'session_releve_fichiers_session_id_index');
        });

        Schema::table('session_releve_fichiers', function (Blueprint $table) {
            $table->dropUnique('session_releve_fichiers_case_unique');
            $table->unique(
                ['session_releve_id', 'role', 'numero', 'champ_supplementaire_id'],
                'session_releve_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('session_releve_fichiers', function (Blueprint $table) {
            $table->dropUnique('session_releve_fichiers_case_unique');
            $table->unique(
                ['session_releve_id', 'role', 'numero'],
                'session_releve_fichiers_case_unique'
            );
        });

        Schema::table('session_releve_fichiers', function (Blueprint $table) {
            $table->dropIndex('session_releve_fichiers_session_id_index');
        });

        Schema::table('session_releve_fichiers', function (Blueprint $table) {
            $table->dropForeign(['champ_supplementaire_id']);
            $table->dropColumn('champ_supplementaire_id');
        });
    }
};
