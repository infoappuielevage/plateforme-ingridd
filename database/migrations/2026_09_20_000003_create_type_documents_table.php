<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nom de table conforme à la convention Eloquent pour le modèle
        // App\Models\TypeDocument (singulier "type" + pluriel "documents") —
        // sans cela, Eloquent cherche "type_documents" alors que la table
        // s'appellerait "types_documents", d'où une erreur "no such table".
        Schema::create('type_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_documents');
    }
};
