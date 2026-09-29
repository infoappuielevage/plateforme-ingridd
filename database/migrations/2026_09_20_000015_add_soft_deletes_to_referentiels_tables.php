<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille pour les cinq référentiels (Catégories, Types de documents,
 * Filières, Niveaux, Années académiques) — sur demande du client, le même
 * filet de sécurité que sur les documents (voir App\Models\Document, déjà
 * SoftDeletes) : une suppression va d'abord à la corbeille (réversible)
 * plutôt que d'effacer la ligne tout de suite.
 *
 * Le garde-fou déjà en place dans chaque XxxResource::table() (impossible
 * de supprimer un référentiel encore utilisé par un document, même
 * corbeillé — `$record->documents()->withTrashed()->doesntExist()`) reste
 * inchangé et s'applique maintenant à la mise à la corbeille elle-même :
 * un référentiel ne peut donc jamais être corbeillé tant qu'il est utilisé,
 * ce qui évite qu'un document existant se retrouve à pointer vers un
 * référentiel corbeillé (aucune relation Document → référentiel n'a donc
 * besoin d'un ->withTrashed() particulier).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'type_documents', 'filieres', 'niveaux', 'annees_academiques'] as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'type_documents', 'filieres', 'niveaux', 'annees_academiques'] as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
