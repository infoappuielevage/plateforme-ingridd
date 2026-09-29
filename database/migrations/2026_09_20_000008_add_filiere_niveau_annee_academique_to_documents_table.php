<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout après coup (voir database/migrations/2026_09_20_000004_create_documents_table.php,
 * déjà exécutée) : on ne modifie jamais une migration déjà appliquée, on en
 * ajoute une nouvelle qui altère la table.
 *
 * Les trois colonnes sont volontairement nullable : un document archivé
 * n'est pas toujours rattaché à une filière/un niveau/une année académique
 * précise (ex. un document administratif générique) — voir App\Models\Document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('filiere_id')->nullable()->after('type_document_id')->constrained('filieres')->nullOnDelete();
            $table->foreignId('niveau_id')->nullable()->after('filiere_id')->constrained('niveaux')->nullOnDelete();
            $table->string('annee_academique', 9)->nullable()->after('niveau_id');

            $table->index('annee_academique');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['filiere_id']);
            $table->dropForeign(['niveau_id']);
            $table->dropColumn(['filiere_id', 'niveau_id', 'annee_academique']);
        });
    }
};
