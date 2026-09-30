<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une "case" à remplir pour un PV de soutenance (voir
 * 2026_10_04_000001_create_pv_soutenances_table.php) : le diplôme, l'image du
 * PV sur le registre, ou un document ajouté depuis Référentiels > Champs
 * supplémentaires — MÊME PRINCIPE que session_deliberation_fichiers/
 * session_releve_fichiers (voir leurs migrations respectives pour le détail
 * des choix nullOnDelete()/cascadeOnDelete(), repris ici à l'identique).
 *
 * Pas de colonne `numero` ici (contrairement aux deux tables ci-dessus) :
 * un PV de soutenance ne porte aucune case numérotée répétée (pas de
 * semestre/année de thèse) — seulement des rôles fixes (diplôme, image du
 * registre) et d'éventuels champs supplémentaires, d'où la contrainte unique
 * directement sur (pv_soutenance_id, role, champ_supplementaire_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pv_soutenance_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pv_soutenance_id')->constrained('pv_soutenances')->cascadeOnDelete();
            // 'diplome' | 'image_registre' | 'supplementaire' — voir
            // App\Models\PvSoutenanceFichier::ROLE_*.
            $table->string('role', 30);
            // Uniquement renseigné pour role = 'supplementaire' — voir
            // App\Models\ChampSupplementaire. nullOnDelete() : supprimer
            // définitivement un champ supplémentaire ne doit jamais emporter
            // les cases déjà créées (même raisonnement que
            // 2026_10_03_000002_add_champ_supplementaire_id_to_session_deliberation_fichiers_table.php).
            $table->foreignId('champ_supplementaire_id')->nullable()
                ->constrained('champs_supplementaires')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['pv_soutenance_id', 'role', 'champ_supplementaire_id'],
                'pv_soutenance_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_soutenance_fichiers');
    }
};
