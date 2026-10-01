<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les fichiers d'une réunion (voir 2026_10_07_000001_create_reunions_table.php)
 * — DEUX natures bien différentes, demandées explicitement par le client,
 * d'où les deux rôles ci-dessous plutôt qu'une simple relation `documents`
 * en cascade :
 *
 * - `rapport` : LE rapport de la réunion — case UNIQUE et FIXE, pré-créée
 *   vide à la création de la réunion (voir
 *   App\Http\Controllers\ReunionController::creerCasesVides()), exactement
 *   comme les cases fixes de PvSoutenanceFichier/SessionDeliberationFichier.
 *   `numero` reste NULL pour ce rôle (une seule case, pas de numérotation).
 *
 * - `piece_jointe` : des pièces jointes complémentaires EN NOMBRE LIBRE
 *   (liste de présence, annexes...) — contrairement aux cases fixes
 *   ci-dessus, AUCUNE case n'est pré-créée pour ce rôle : une ligne n'existe
 *   que lorsqu'un fichier est effectivement ajouté (voir
 *   ReunionController::ajouterPieceJointe()), et la supprimer retire la
 *   ligne elle-même plutôt que de la vider (pas de "case" à garder
 *   disponible, contrairement à `rapport` — voir
 *   ReunionController::supprimerPieceJointe()). `numero` sert ici de simple
 *   compteur d'ajout (1, 2, 3...), pour un affichage stable dans l'ordre
 *   d'ajout plutôt que par id.
 *
 * `libelle` (nullable) : uniquement utile pour `piece_jointe` (ex. "Liste de
 * présence", "Annexe budget") — une pièce jointe n'a pas de rôle fixe pour
 * se décrire elle-même, contrairement à `rapport` (toujours "Rapport de la
 * réunion", géré côté PHP/Blade). Laissé vide, une pièce jointe retombe sur
 * le nom original du fichier déposé — voir App\Models\ReunionFichier::libelle().
 *
 * `document_id` nullable + `nullOnDelete()`, `reunion_id` en
 * `cascadeOnDelete()` : mêmes raisons que pour les tables de cases
 * existantes (session_deliberation_fichiers, pv_soutenance_fichiers...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reunion_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reunions')->cascadeOnDelete();
            // 'rapport' | 'piece_jointe' — voir App\Models\ReunionFichier::ROLE_*.
            $table->string('role', 20);
            $table->unsignedInteger('numero')->nullable();
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reunion_id', 'role', 'numero'], 'reunion_fichiers_case_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reunion_fichiers');
    }
};
