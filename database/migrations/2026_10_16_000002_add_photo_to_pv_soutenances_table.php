<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo de l'étudiant sur un PV de soutenance — demande explicite du client
 * ("et une image de la personne"), affichée en vignette (voir
 * resources/views/gestion/pv-soutenances/{form,show,index}.blade.php), même
 * principe que `avatar` sur App\Models\User (voir son docblock) plutôt
 * qu'une case de plus parmi les documents (App\Models\PvSoutenanceFichier) :
 * un portrait se lit d'un coup d'œil, pas en ouvrant un fichier téléchargé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pv_soutenances', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('lieu_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('pv_soutenances', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
