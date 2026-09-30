<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrairement aux semestres Licence/Master (numérotation LMD standard,
 * voir App\Support\Semestres — volontairement PAS un réglage), le nombre
 * d'années de thèse à prévoir pour le Doctorat n'a pas de standard fixe
 * unique : le client a explicitement demandé de pouvoir le régler lui-même
 * depuis Administration > Paramètres plutôt que de le figer dans le code
 * (voir App\Http\Controllers\SessionDeliberationController, qui l'utilise
 * pour savoir combien de cases "Année de thèse N" créer à l'ouverture d'une
 * session Doctorat).
 *
 * Nullable ici (comme les autres colonnes de parametres) : Parametre::actuel()
 * fournit la valeur par défaut (3) à la toute première création de la ligne
 * id=1 — voir App\Models\Parametre::VALEURS_PAR_DEFAUT — une ligne déjà
 * existante avant cette migration récupère simplement NULL, géré par
 * SessionDeliberation::nombreAnneesTheseDoctorat().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->unsignedTinyInteger('nombre_annees_these_doctorat')->nullable()->after('types_fichiers_autorises');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('nombre_annees_these_doctorat');
        });
    }
};
