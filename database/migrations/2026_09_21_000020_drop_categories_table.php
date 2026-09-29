<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression complète du référentiel "Catégorie" (sur demande du client,
 * qui n'en a pas l'usage — voir la migration précédente pour le retrait de
 * `categorie_id` sur `documents`, préalable indispensable à celle-ci).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
