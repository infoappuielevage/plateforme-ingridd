<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trois réglages ajoutés pour éviter d'avoir à toucher au code pour de
 * petits ajustements (voir App\Filament\Pages\Parametres) :
 *
 * - `mysqldump_chemin` : chemin vers l'exécutable `mysqldump`, utilisé par
 *   App\Console\Commands\CreerSauvegarde quand la connexion active est MySQL
 *   (voir cette migration et la commande pour le contexte : le passage de
 *   SQLite à MySQL avait laissé la sauvegarde de la base elle-même hors
 *   service, la commande ne sachant sauvegarder qu'un fichier SQLite). Vide
 *   par défaut (la commande essaie alors simplement `mysqldump` depuis le
 *   PATH système) — à renseigner seulement si ce n'est pas suffisant.
 *
 * - `frequence_sauvegarde_auto` : quotidienne/hebdomadaire/mensuelle — la
 *   planification elle-même reste quotidienne côté Laravel (voir
 *   routes/console.php), c'est la commande qui décide si le jour convient
 *   (Parametre::doitSauvegarderAujourdhui()) : changer la fréquence ne
 *   demande donc plus de modifier le code.
 *
 * - `types_fichiers_autorises` : quelles familles de formats (voir
 *   App\Support\TypesFichiers::CATEGORIES) sont acceptées à l'ajout d'un
 *   document — toutes par défaut (comportement identique à avant ce
 *   réglage). Reste une liste blanche de familles pré-approuvées, pas une
 *   saisie libre de type MIME : ajouter une famille entièrement nouvelle
 *   (ex. audio/vidéo) reste un changement de code, volontairement — accepter
 *   n'importe quel type de fichier sur simple déclaration texte affaiblirait
 *   la protection que la liste blanche est censée apporter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->string('mysqldump_chemin')->nullable()->after('retention_sauvegardes_nombre');
            $table->string('frequence_sauvegarde_auto')->default('quotidienne')->after('mysqldump_chemin');
            $table->json('types_fichiers_autorises')->nullable()->after('frequence_sauvegarde_auto');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn(['mysqldump_chemin', 'frequence_sauvegarde_auto', 'types_fichiers_autorises']);
        });
    }
};
