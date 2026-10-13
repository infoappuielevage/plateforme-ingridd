<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une dépense = UNE catégorie (App\Models\TypeDepense) × UNE année
 * académique, avec exactement DEUX cases fixes — un bordereau (PDF) et un
 * fichier Excel — demande explicite du client ("tous composer de bordereau
 * en pdf, et un fichier en exel").
 *
 * Structure volontairement minimale, même raisonnement que
 * App\Models\EtatFinancier (un seul `document_id` directement sur la ligne,
 * pas de table de "cases" séparée) : ici DEUX colonnes `document_id`
 * (`bordereau_document_id`/`excel_document_id`) suffisent puisqu'il n'y a
 * QUE deux cases fixes, jamais de liste en nombre libre pour une dépense —
 * pas besoin d'une table enfant comme App\Models\ReunionFichier.
 *
 * Contrairement à App\Models\RecetteDossier (une ligne créée EXPLICITEMENT
 * par l'utilisateur via un formulaire "Nouveau dossier"), une ligne ici est
 * créée AUTOMATIQUEMENT : par le seeder de départ et par
 * App\Models\TypeDepense::synchroniserCasesRetroactives() (nouvelle
 * catégorie ajoutée en administration) ou par le hook
 * App\Models\AnneeAcademique::booted() (nouvelle année académique créée) —
 * pour que CHAQUE catégorie active ait déjà sa ligne (vide) sur CHAQUE
 * année académique, sans étape de création manuelle (demande explicite du
 * client, confirmée via la question de clarification posée avant cette
 * migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            $table->foreignId('type_depense_id')->constrained('types_depenses')->restrictOnDelete();
            $table->foreignId('bordereau_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('excel_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['annee_academique_id', 'type_depense_id'], 'depenses_annee_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
