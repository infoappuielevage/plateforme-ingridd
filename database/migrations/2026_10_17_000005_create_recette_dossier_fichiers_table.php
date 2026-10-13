<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les fichiers d'un dossier Retrait diplôme / Frais de soutenance (voir
 * 2026_10_17_000004_create_recette_dossiers_table.php) — liste EN NOMBRE
 * LIBRE, aucune case pré-créée (même principe que le rôle `piece_jointe`
 * d'App\Models\ReunionFichier/App\Models\EtudiantFichier) : une ligne
 * n'existe que lorsqu'un fichier est effectivement ajouté, et la retirer
 * supprime la ligne elle-même plutôt que de la vider.
 *
 * Pas de colonne `role` ici (contrairement à recette_scolarite_fichiers) :
 * un seul type de case pour toute la liste, `numero` suffit comme simple
 * compteur d'ajout pour un affichage stable dans l'ordre d'ajout.
 *
 * `document_id` nullable + `nullOnDelete()`, `recette_dossier_id` en
 * `cascadeOnDelete()` : mêmes raisons que pour les autres tables de cases du
 * projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recette_dossier_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recette_dossier_id')->constrained('recette_dossiers')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['recette_dossier_id', 'numero'], 'recette_dossier_fichiers_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recette_dossier_fichiers');
    }
};
