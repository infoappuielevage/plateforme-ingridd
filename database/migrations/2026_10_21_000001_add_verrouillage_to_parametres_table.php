<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages du verrouillage des documents — demande explicite du client
 * ("verrouiller les documents déjà enregistrés... depuis 2025, sans
 * autorisation de l'administration"). Voir App\Support\Verrouillage pour la
 * logique complète ; en résumé, DEUX seuils indépendants, chacun facultatif
 * et réglable depuis Administration > Paramètres > Verrouillage :
 * - `verrouillage_annee_academique_id` : verrouille tout document rattaché à
 *   cette année académique OU UNE ANNÉE ANTÉRIEURE.
 * - `verrouillage_date` : verrouille tout document DÉPOSÉ avant cette date,
 *   même sans année académique renseignée (ex. PV, documents administratifs).
 * Un document verrouillé par l'une OU l'autre règle reste verrouillé tant
 * qu'il n'a pas été explicitement déverrouillé (voir la migration suivante,
 * 2026_10_21_000002, qui ajoute les colonnes de déverrouillage sur
 * `documents`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->foreignId('verrouillage_annee_academique_id')->nullable()->after('nombre_annees_these_doctorat')
                ->constrained('annees_academiques')->nullOnDelete();
            $table->date('verrouillage_date')->nullable()->after('verrouillage_annee_academique_id');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verrouillage_annee_academique_id');
            $table->dropColumn('verrouillage_date');
        });
    }
};
