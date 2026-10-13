<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des Recettes > Scolarité — nouvel écran dédié (demande explicite
 * du client, "intègre la Gestion des Recette... la Scolarité"), MÊME
 * PRINCIPE que sessions_deliberation/reunions avant lui : un vrai
 * enregistrement dédié, un par ANNÉE ACADÉMIQUE (contrairement aux états
 * financiers, en année civile simple — voir le docblock de
 * 2026_10_17_000001_create_etats_financiers_table.php — la scolarité reste
 * rattachée au référentiel App\Models\AnneeAcademique déjà utilisé partout
 * ailleurs dans l'application, demande explicite du client).
 *
 * Aucun autre champ qu'`annee_academique_id` : contrairement à
 * sessions_deliberation (cycle/filière), la scolarité ne distingue ici ni
 * cycle ni filière — un seul dossier "Scolarité" par année, dans lequel se
 * rangent ensuite les pièces (bordereaux de versement, relevés Orange/Moov/
 * Telecel Money, autres documents — voir
 * 2026_10_17_000003_create_recette_scolarite_fichiers_table.php).
 *
 * `restrictOnDelete()` sur annee_academique_id (même raisonnement que
 * reunions/sessions_deliberation) : champ obligatoire d'identification,
 * supprimer une année académique encore utilisée par un dossier Scolarité
 * doit être bloqué plutôt que de laisser un dossier orphelin.
 *
 * SoftDeletes : même convention que les autres tables métier de premier
 * niveau de ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recette_scolarites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Un seul dossier Scolarité par année académique.
            $table->unique('annee_academique_id', 'recette_scolarites_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recette_scolarites');
    }
};
