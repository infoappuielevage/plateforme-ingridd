<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les pièces d'un dossier Scolarité (voir
 * 2026_10_17_000002_create_recette_scolarites_table.php) — CINQ rôles,
 * demande explicite du client ("Bordereau de versement... Relevé Orange
 * Money ; Relevé Moov Money ; Relevé Telecel Money ; autres documents") :
 *
 * - `orange_money` / `moov_money` / `telecel_money` : TROIS cases FIXES,
 *   uniques, pré-créées vides à la création du dossier (voir
 *   App\Http\Controllers\RecetteScolariteController::creerCasesFixes()) —
 *   même principe que le rôle `rapport` d'App\Models\ReunionFichier.
 *   `numero` reste NULL pour ces trois rôles (une seule case chacun).
 *
 * - `bordereau` : liste EN NOMBRE LIBRE ("on pourra avoir par année
 *   académique les bordereau 1, bordereau 2, etc...") — AUCUNE case n'est
 *   pré-créée, une ligne n'existe que lorsqu'un bordereau est effectivement
 *   ajouté. `numero` sert ici de numéro d'ordre affiché ("Bordereau 1",
 *   "Bordereau 2"...), même principe que le rôle `piece_jointe` d'
 *   App\Models\ReunionFichier.
 *
 * - `autre` : liste EN NOMBRE LIBRE également, pour tout document de
 *   scolarité qui ne rentre dans aucune des quatre cases ci-dessus.
 *   `libelle` (nullable) y est utile (ex. "Reçu complémentaire"), comme pour
 *   `piece_jointe` sur les réunions — vide, un document "autre" retombe sur
 *   le nom original du fichier déposé.
 *
 * `document_id` nullable + `nullOnDelete()`, `recette_scolarite_id` en
 * `cascadeOnDelete()` : mêmes raisons que pour les tables de cases
 * existantes (reunion_fichiers, session_deliberation_fichiers...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recette_scolarite_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recette_scolarite_id')->constrained('recette_scolarites')->cascadeOnDelete();
            // 'orange_money' | 'moov_money' | 'telecel_money' | 'bordereau' | 'autre'
            // — voir App\Models\RecetteScolariteFichier::ROLE_*.
            $table->string('role', 20);
            // Numéro d'ordre pour 'bordereau'/'autre' (compteur d'ajout) ; NULL
            // pour les trois cases fixes (une seule case chacune).
            $table->unsignedInteger('numero')->nullable();
            // Utile uniquement pour 'autre' — voir le docblock ci-dessus.
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['recette_scolarite_id', 'role', 'numero'],
                'recette_scolarite_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recette_scolarite_fichiers');
    }
};
