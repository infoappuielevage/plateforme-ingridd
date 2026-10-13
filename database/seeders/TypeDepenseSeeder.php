<?php

namespace Database\Seeders;

use App\Models\TypeDepense;
use Illuminate\Database\Seeder;

/**
 * Liste de départ des catégories de dépenses — demande explicite du client
 * (voir le docblock de la migration 2026_10_20_000001_create_types_depenses_table.php).
 * Librement complétée ensuite depuis Référentiels > Catégories de dépenses.
 *
 * `code` sert de clé stable (voir App\Models\TypePieceEtudiant/TypeDocument) :
 * `updateOrCreate()` ne duplique donc jamais au rejeu.
 *
 * Appelle ensuite TypeDepense::synchroniserCasesRetroactives() pour chaque
 * catégorie — exactement comme le ferait un admin en l'ajoutant depuis
 * l'écran Référentiels > Catégories de dépenses (voir
 * App\Http\Controllers\TypeDepenseController::store()) — pour que ce
 * seeder crée bien les lignes App\Models\Depense (vides) sur TOUTES les
 * années académiques déjà existantes, y compris sur une base déjà en
 * service.
 */
class TypeDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'BANCAIRE', 'libelle' => 'Dépenses bancaires', 'ordre' => 1],
            ['code' => 'VACATAIRE', 'libelle' => 'Vacataires', 'ordre' => 2],
            ['code' => 'SALAIRE', 'libelle' => 'Salaires', 'ordre' => 3],
            ['code' => 'ACHAT', 'libelle' => 'Achats', 'ordre' => 4],
        ];

        foreach ($types as $type) {
            $modele = TypeDepense::query()->updateOrCreate(['code' => $type['code']], $type);
            $modele->synchroniserCasesRetroactives();
        }
    }
}
