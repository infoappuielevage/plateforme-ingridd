<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sous-types de document (ex. le type "Attestation" a pour sous-types
 * "Succès"/"Vacation"/"Inscription"...) — recensés par le client en même
 * temps qu'une refonte complète du référentiel des types (voir
 * database/seeders/TypeDocumentSeeder.php). Table à part (plutôt qu'une
 * colonne texte sur `documents`) pour rester gérable depuis l'administration,
 * type par type (voir App\Models\SousTypeDocument et
 * TypeDocumentResource\RelationManagers\SousTypesRelationManager) : certaines
 * listes sont explicitement ouvertes côté client (ex. "Bordereau" : BNSP,
 * CNSS, Coris Banque... "etc."), donc pas question de les figer dans le code.
 *
 * `cascadeOnDelete()` sur `type_document_id` : un type de document n'a de
 * sens sans ses sous-types, donc une suppression DÉFINITIVE du type (pas une
 * simple mise à la corbeille, qui ne déclenche aucune suppression SQL réelle)
 * élimine aussi ses sous-types. Contrairement aux référentiels de premier
 * niveau (TypeDocument...), pas de corbeille ici : voir le docblock de
 * SousTypeDocument pour pourquoi ce n'est pas nécessaire.
 *
 * `unique(['type_document_id', 'libelle'])` : évite un doublon de libellé au
 * sein d'un même type (rien n'empêche le même libellé sur deux types
 * différents, ex. "Licence" pourrait exister ailleurs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sous_types_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->constrained('type_documents')->cascadeOnDelete();
            $table->string('libelle');
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['type_document_id', 'libelle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sous_types_documents');
    }
};
