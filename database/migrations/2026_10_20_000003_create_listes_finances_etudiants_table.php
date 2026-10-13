<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Liste des étudiants (finances)" — demande explicite du client ("intègre
 * également la GESTION de la listes des etudiant afin qu'on puissent avoir
 * la listes des etudiants avec leurs etat de finances par année académique
 * en fichier exel, pdf, etc... dans la partie Finance"). Écran séparé de
 * "Gestion des recettes" (confirmé via la question de clarification posée
 * avant cette migration), mais structure IDENTIQUE à App\Models\
 * RecetteDossier (voir le docblock de
 * 2026_10_17_000004_create_recette_dossiers_table.php) : une ligne par
 * année académique (créée EXPLICITEMENT par l'utilisateur, contrairement
 * aux Dépenses), contenant une liste EN NOMBRE LIBRE de fichiers (Excel,
 * PDF...) — voir 2026_10_20_000004_create_liste_finance_etudiant_fichiers_table.php.
 *
 * AUCUNE relation avec App\Models\Etudiant/App\Models\EtudiantFichier (le
 * "vrai" dossier étudiant) — simple dépôt de fichiers donnant une vue
 * d'ensemble par année, même principe et même garde-fou explicite que
 * App\Models\RecetteDossier pour le retrait diplôme/frais de soutenance
 * ("à ne pas confondre avec ce qui était déjà en place, aucune relation").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listes_finances_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('annee_academique_id', 'listes_finances_etudiants_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listes_finances_etudiants');
    }
};
