<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les fichiers d'un contrat (voir 2026_10_23_000002_create_contrats_table.php)
 * — EXACTEMENT le même principe à deux rôles que
 * 2026_10_07_000002_create_reunion_fichiers_table.php (App\Models\ReunionFichier) :
 *
 * - `contrat_signe` : LE contrat signé lui-même — case UNIQUE et FIXE,
 *   pré-créée vide à la création du contrat (voir
 *   App\Http\Controllers\ContratController::creerCasesVides()). `numero`
 *   reste NULL pour ce rôle (une seule case, pas de numérotation).
 *
 * - `avenant` : les avenants (modifications ultérieures du contrat) EN
 *   NOMBRE LIBRE — demande explicite du client ("document(s) joints (le
 *   contrat signé, avenants)"). Comme `piece_jointe` sur les réunions,
 *   AUCUNE case n'est pré-créée : une ligne n'existe que lorsqu'un avenant
 *   est effectivement ajouté, et la supprimer retire la ligne elle-même
 *   plutôt que de la vider. `numero` sert de simple compteur d'ajout (1, 2,
 *   3...), pour un affichage stable dans l'ordre d'ajout.
 *
 * `libelle` (nullable) : uniquement utile pour `avenant` (ex. "Avenant n°1 —
 * prolongation", "Révision tarifaire") — un avenant sans libellé retombe sur
 * le nom original du fichier déposé, voir App\Models\ContratFichier::libelle().
 *
 * `document_id` nullable + `nullOnDelete()`, `contrat_id` en
 * `cascadeOnDelete()` : mêmes raisons que les autres tables de "cases" du
 * projet (reunion_fichiers, recette_dossier_fichiers...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrat_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnDelete();
            // 'contrat_signe' | 'avenant' — voir App\Models\ContratFichier::ROLE_*.
            $table->string('role', 20);
            $table->unsignedInteger('numero')->nullable();
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contrat_id', 'role', 'numero'], 'contrat_fichiers_case_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_fichiers');
    }
};
