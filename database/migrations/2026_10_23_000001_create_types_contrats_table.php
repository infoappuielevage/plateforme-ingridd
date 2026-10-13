<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des types de contrats — demande explicite du client ("intègre
 * à présent la gestion des contrat, on aurait les partenariat... les
 * contrat de prestation de Service, et tout autre contrat dont tu pense que
 * c'est necessaire"), EXACTEMENT le même principe que
 * App\Models\TypeDepense/App\Models\TypePieceEtudiant : une liste gérée
 * depuis l'administration (Référentiels > Catégories de contrats), jamais
 * figée dans le code — voir App\Http\Controllers\TypeContratController.
 *
 * Contrairement à App\Models\TypeDepense, AUCUNE synchronisation
 * rétroactive ici : un contrat n'est pas "une catégorie × une année
 * académique" (une ligne par combinaison, créée automatiquement) mais un
 * enregistrement créé À LA DEMANDE, au cas par cas (un partenariat signé,
 * un bail renouvelé...) — voir le docblock de la migration
 * 2026_10_23_000002_create_contrats_table.php. Ce référentiel reste donc
 * une simple liste de catégories pour les onglets de l'écran "Gestion des
 * contrats", sans logique de création automatique.
 *
 * `code` : clé stable utilisée par le seeder de départ
 * (database/seeders/TypeContratSeeder.php) et par l'URL de chaque onglet
 * (voir routes/web.php, `{typeContrat:code}`) — jamais modifiable après
 * création (voir TypeContratController::valider()), même principe que
 * App\Models\TypeDepense/TypeDocument.
 *
 * `actif` + SoftDeletes : désactiver une catégorie la retire simplement des
 * onglets et du formulaire "Nouveau contrat" sans jamais toucher aux
 * contrats déjà créés — même garde-fou que les autres référentiels
 * "extensibles" de ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_contrats', function (Blueprint $table) {
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
        Schema::dropIfExists('types_contrats');
    }
};
