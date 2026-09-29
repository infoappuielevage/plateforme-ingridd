<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace la colonne libre `documents.annee_academique` (chaîne "AAAA-AAAA")
 * par une clé étrangère `annee_academique_id` vers le nouveau référentiel
 * annees_academiques — pour que le champ soit une liste déroulante dans le
 * formulaire plutôt qu'une saisie libre (source d'erreurs de format/de
 * doublons proches, ex. "2025-2026" vs "2025 - 2026").
 *
 * En profite pour ajouter `semestre` (nullable), utile notamment pour les
 * relevés de notes qui concernent un semestre précis. Numéroté de 1 à 10
 * selon le niveau (L1 = S1/S2 ... M2 = S9/S10, numérotation continue sur
 * les 5 années — voir App\Support\Semestres) : la colonne elle-même n'a pas
 * besoin de changer pour ça, `unsignedTinyInteger` couvre largement 1 à 10.
 *
 * Les valeurs déjà présentes dans `annee_academique` (si la précédente
 * migration a déjà tourné et que des documents existent) sont reprises dans
 * le nouveau référentiel puis rattachées, pour ne rien perdre.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Reprendre les valeurs déjà utilisées dans le nouveau référentiel.
        $anneesExistantes = DB::table('documents')
            ->whereNotNull('annee_academique')
            ->distinct()
            ->pluck('annee_academique');

        foreach ($anneesExistantes as $libelle) {
            DB::table('annees_academiques')->insertOrIgnore([
                'libelle' => $libelle,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2) Ajouter la nouvelle colonne (nullable : un document n'est pas
        // toujours rattaché à une année académique précise).
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('annee_academique_id')->nullable()->after('niveau_id')->constrained('annees_academiques')->nullOnDelete();
            $table->unsignedTinyInteger('semestre')->nullable()->after('annee_academique_id');
        });

        // 3) Rattacher chaque document à la bonne ligne du référentiel.
        // (->get()->each() plutôt que ->each() : la méthode d'itération
        // directe sur le query builder n'existe plus dans Laravel récent.)
        DB::table('documents')->whereNotNull('annee_academique')->orderBy('id')->get()->each(function ($document) {
            $anneeId = DB::table('annees_academiques')->where('libelle', $document->annee_academique)->value('id');

            if ($anneeId) {
                DB::table('documents')->where('id', $document->id)->update(['annee_academique_id' => $anneeId]);
            }
        });

        // 4) Supprimer l'ancienne colonne (et son index).
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['annee_academique']);
            $table->dropColumn('annee_academique');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('annee_academique', 9)->nullable()->after('niveau_id');
        });

        DB::table('documents')->whereNotNull('annee_academique_id')->orderBy('id')->get()->each(function ($document) {
            $libelle = DB::table('annees_academiques')->where('id', $document->annee_academique_id)->value('libelle');

            if ($libelle) {
                DB::table('documents')->where('id', $document->id)->update(['annee_academique' => $libelle]);
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('annee_academique');
            $table->dropForeign(['annee_academique_id']);
            $table->dropColumn(['annee_academique_id', 'semestre']);
        });
    }
};
