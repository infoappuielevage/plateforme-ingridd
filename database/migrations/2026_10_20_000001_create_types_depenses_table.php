<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des catégories de dépenses — demande explicite du client
 * ("intègre la Gestion des Dépenses composé de... Bancaire... Vacataire...
 * Salaires... Achats... avec la possibilité d'intégrer d'autres dépenses
 * comme ce qui est en place pour champs supplémentaire"), c'est-à-dire
 * EXACTEMENT le même principe que App\Models\TypePieceEtudiant/
 * App\Models\ChampSupplementaire : une liste gérée depuis l'administration,
 * jamais figée dans le code — voir App\Models\TypeDepense::
 * synchroniserCasesRetroactives()/synchroniserPourAnnee().
 *
 * `code` : clé stable utilisée par le seeder de départ
 * (database/seeders/TypeDepenseSeeder.php) et par l'URL de chaque onglet
 * (voir routes/web.php, `{typeDepense:code}`) — jamais modifiable après
 * création (voir TypeDepenseController::valider()), même principe que
 * App\Models\TypePieceEtudiant/TypeDocument.
 *
 * `actif` + SoftDeletes : désactiver une catégorie arrête la création de
 * nouvelles cases (nouvelles années académiques ET synchronisation
 * rétroactive) sans jamais toucher aux cases déjà créées — même garde-fou
 * que les autres référentiels "extensibles" de ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 150);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_depenses');
    }
};
