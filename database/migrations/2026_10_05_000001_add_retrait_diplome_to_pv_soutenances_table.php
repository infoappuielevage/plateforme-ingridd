<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi du retrait PHYSIQUE du diplôme — demande explicite du client, allant
 * plus loin qu'un simple "Retiré/Non retiré" : qui est venu le récupérer
 * (l'étudiant lui-même, ou une autre personne — auquel cas son nom est
 * précisé) et la date à laquelle cette personne l'a effectivement récupéré.
 *
 * Remplace la première version (App\Models\Document::statut_retrait/
 * date_retrait/retrait_enregistre_par_id, réutilisés tels quels depuis les
 * diplômes/attestations classiques) : ces colonnes-là ne capturaient QUE "un
 * utilisateur connecté a coché la case, à telle date" — pas "qui, physiquement,
 * est venu". Les champs ci-dessous vivent directement sur `pv_soutenances`
 * plutôt que sur `documents` : un PV de soutenance n'a qu'UN SEUL diplôme
 * (voir App\Models\PvSoutenanceFichier::ROLE_DIPLOME, une case fixe, jamais
 * répétée), donc pas besoin de les rattacher au fichier déposé — et ça
 * simplifie l'affichage en liste (voir PvSoutenanceController::index()) en
 * évitant une jointure.
 *
 * Deux dates bien distinctes, toutes les deux demandées par le client :
 * `diplome_date_retrait` (quand la personne a PHYSIQUEMENT pris le diplôme,
 * saisie à la main, peut être une date passée) et
 * `diplome_retrait_enregistre_le` (quand un utilisateur connecté a SAISI
 * cette information dans l'application — toujours "maintenant" au moment de
 * l'enregistrement, jamais modifiable à la main).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pv_soutenances', 'diplome_retirant')) {
            Schema::table('pv_soutenances', function (Blueprint $table) {
                // 'etudiant' | 'autre' — voir App\Models\PvSoutenance::RETIRANT_*.
                $table->string('diplome_retirant', 20)->nullable()->after('maitre_stage');
                // Uniquement renseigné quand diplome_retirant = 'autre'.
                $table->string('diplome_nom_retirant', 150)->nullable()->after('diplome_retirant');
                $table->date('diplome_date_retrait')->nullable()->after('diplome_nom_retirant');
                $table->foreignId('diplome_retrait_enregistre_par_id')->nullable()->after('diplome_date_retrait')
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('diplome_retrait_enregistre_le')->nullable()->after('diplome_retrait_enregistre_par_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pv_soutenances', function (Blueprint $table) {
            $table->dropForeign(['diplome_retrait_enregistre_par_id']);
            $table->dropColumn([
                'diplome_retirant',
                'diplome_nom_retirant',
                'diplome_date_retrait',
                'diplome_retrait_enregistre_par_id',
                'diplome_retrait_enregistre_le',
            ]);
        });
    }
};
