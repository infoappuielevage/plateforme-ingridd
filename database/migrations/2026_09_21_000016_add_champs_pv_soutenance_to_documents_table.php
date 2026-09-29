<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs complémentaires pour les documents de type "PV de soutenance" (voir
 * App\Support\TypesPvSoutenance et DocumentResource, où ils ne sont
 * affichés que pour ce type précis — même principe que `semestre` pour les
 * relevés de notes ou les champs "Diplôme"/"Attestation") : numérisation du
 * registre papier des soutenances de mémoire, sur demande du client.
 *
 * `nom_titulaire` et `mention` (ajoutés par la migration
 * 2026_09_20_000014_...) sont réutilisés tels quels pour le nom de
 * l'étudiant et la mention obtenue — inutile de dupliquer ces deux colonnes
 * pour un troisième type de document qui en a conceptuellement besoin de la
 * même façon.
 *
 * Toutes ces colonnes restent nullable : un document d'un autre type (ex.
 * un contrat, ou même un diplôme) ne les renseigne jamais.
 *
 * Pas de suivi de retrait/archivage pour ce type (contrairement aux
 * diplômes/attestations) : non demandé pour l'instant par le client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('theme_memoire')->nullable()->after('retrait_enregistre_par_id');
            $table->date('date_soutenance')->nullable()->after('theme_memoire');
            $table->text('membres_jury')->nullable()->after('date_soutenance');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'theme_memoire',
                'date_soutenance',
                'membres_jury',
            ]);
        });
    }
};
