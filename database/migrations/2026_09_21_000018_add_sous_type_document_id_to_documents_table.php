<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sous_type_document_id` : facultatif, ne concerne que les documents dont
 * le type a des sous-types configurés (voir App\Models\SousTypeDocument et
 * DocumentResource::form(), même principe que `semestre` pour les relevés
 * de notes — champ masqué si le type sélectionné n'en a aucun).
 *
 * `nullOnDelete()` plutôt qu'un garde-fou "encore utilisé" (contrairement
 * aux référentiels de premier niveau) : le champ étant déjà facultatif, la
 * suppression d'un sous-type ne peut jamais laisser un document dans un état
 * invalide — au pire, son sous-type redevient vide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('sous_type_document_id')
                ->nullable()
                ->after('type_document_id')
                ->constrained('sous_types_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['sous_type_document_id']);
            $table->dropColumn('sous_type_document_id');
        });
    }
};
