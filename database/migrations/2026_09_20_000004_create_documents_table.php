<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('type_document_id')->constrained('type_documents')->restrictOnDelete();
            // Chemin sur le disque privé `documents` (voir config/filesystems.php) —
            // jamais une URL publique.
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
