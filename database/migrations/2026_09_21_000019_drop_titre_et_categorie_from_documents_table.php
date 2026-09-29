<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retrait du champ "Titre" et du référentiel "Catégorie" des documents (sur
 * demande du client : un titre libre faisait doublon avec le nom du fichier
 * d'origine déjà conservé (`nom_original`), et la catégorie n'était pas
 * utilisée dans la pratique — le classement se fait désormais uniquement par
 * type/sous-type de document, voir la refonte complète du référentiel des
 * types (TypeDocumentSeeder) et les nouvelles pages dédiées par type
 * (Administration > menu "Secrétariat").
 *
 * La table `categories` elle-même est supprimée séparément par la migration
 * suivante (2026_09_21_000020) : il faut d'abord retirer la clé étrangère
 * `categorie_id` qui la référence depuis `documents`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['categorie_id']);
            $table->dropColumn(['titre', 'categorie_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('titre')->nullable()->after('id');
            $table->foreignId('categorie_id')->nullable()->after('description')->constrained('categories')->nullOnDelete();
        });
    }
};
