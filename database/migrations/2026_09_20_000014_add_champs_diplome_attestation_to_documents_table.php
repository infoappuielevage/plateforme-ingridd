<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs complémentaires pour les documents de type "Diplôme" et
 * "Attestation" (voir App\Support\TypesDiplome et DocumentResource, où ils
 * ne sont affichés que pour ces deux types précis — comme `semestre` ne
 * l'est que pour les relevés de notes) : le client a demandé à pouvoir
 * mieux gérer/suivre ces documents précis, en particulier savoir qui n'est
 * pas encore venu récupérer son diplôme/attestation.
 *
 * Toutes ces colonnes restent nullable : un document d'un autre type (ex.
 * un contrat) ne les renseigne jamais.
 *
 * `statut_retrait` est une chaîne plutôt qu'un enum SQL (portabilité
 * SQLite/MySQL, cohérent avec le reste du projet, ex. `frequence_sauvegarde_auto`
 * sur Parametre) — les deux valeurs possibles sont les constantes
 * Document::STATUT_RETRAIT_NON_RETIRE / STATUT_RETRAIT_RETIRE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('nom_titulaire')->nullable()->after('semestre');
            $table->string('numero_diplome', 100)->nullable()->after('nom_titulaire');
            $table->date('date_delivrance')->nullable()->after('numero_diplome');
            $table->string('mention', 100)->nullable()->after('date_delivrance');
            $table->string('statut_retrait', 20)->nullable()->after('mention');
            $table->date('date_retrait')->nullable()->after('statut_retrait');
            $table->foreignId('retrait_enregistre_par_id')->nullable()->after('date_retrait')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['retrait_enregistre_par_id']);
            $table->dropColumn([
                'nom_titulaire',
                'numero_diplome',
                'date_delivrance',
                'mention',
                'statut_retrait',
                'date_retrait',
                'retrait_enregistre_par_id',
            ]);
        });
    }
};
