<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des années académiques (ex. "2025-2026"), pour que le champ
 * correspondant sur Document soit une liste déroulante plutôt qu'un champ de
 * saisie libre — voir la migration suivante, qui convertit la colonne
 * `documents.annee_academique` (chaîne) en `annee_academique_id` (clé
 * étrangère vers cette table) et migre les données déjà présentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_academiques', function (Blueprint $table) {
            $table->id();
            // Format "AAAA-AAAA" (ex. 2025-2026), validé côté formulaire —
            // voir App\Filament\Resources\AnneeAcademiqueResource.
            $table->string('libelle', 9)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_academiques');
    }
};
