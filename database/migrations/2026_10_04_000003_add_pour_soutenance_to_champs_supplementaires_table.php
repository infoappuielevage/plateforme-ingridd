<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend les champs supplémentaires (voir
 * 2026_10_03_000001_create_champs_supplementaires_table.php et
 * App\Models\ChampSupplementaire) au PV de soutenance, au même titre que le
 * PV de délibération (`pour_pv`) et le Relevé de notes (`pour_releve`) —
 * réponse du client à la question posée lors de la construction du PV de
 * soutenance ("Oui, via l'administration").
 *
 * Simple ALTER TABLE additif : contrairement aux migrations
 * 2026_10_03_000002/000003, `champs_supplementaires` n'a aucun index unique
 * touchant une colonne booléenne, donc aucun problème d'index de clé
 * étrangère (erreur 1553) à prévoir ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('champs_supplementaires', 'pour_soutenance')) {
            Schema::table('champs_supplementaires', function (Blueprint $table) {
                $table->boolean('pour_soutenance')->default(false)->after('pour_releve');
            });
        }
    }

    public function down(): void
    {
        Schema::table('champs_supplementaires', function (Blueprint $table) {
            $table->dropColumn('pour_soutenance');
        });
    }
};
