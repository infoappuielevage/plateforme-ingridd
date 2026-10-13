<?php

namespace Database\Seeders;

use App\Models\TypeContrat;
use Illuminate\Database\Seeder;

/**
 * Liste de départ des catégories de contrats — demande explicite du client
 * ("intègre à présent la gestion des contrat, on aurait les partenariat...
 * les contrat de prestation de Service, et tout autre contrat dont tu pense
 * que c'est necessaire"), confirmée/complétée via la question de
 * clarification posée avant la migration 2026_10_23_000001_create_types_contrats_table.php
 * ("Bail / location", "Convention de stage/formation", "Contrat
 * enseignants", "Contrat Personnelle INGRIDD" — réponses libres du client).
 * Librement complétée ensuite depuis Référentiels > Catégories de contrats.
 *
 * `code` sert de clé stable (voir App\Models\TypeDepense/TypeDocument) :
 * `updateOrCreate()` ne duplique donc jamais au rejeu.
 */
class TypeContratSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'PARTENARIAT', 'libelle' => 'Partenariat', 'ordre' => 1],
            ['code' => 'PRESTATION_SERVICE', 'libelle' => 'Prestation de service', 'ordre' => 2],
            ['code' => 'BAIL_LOCATION', 'libelle' => 'Bail / location', 'ordre' => 3],
            ['code' => 'CONVENTION_STAGE_FORMATION', 'libelle' => 'Convention de stage / formation', 'ordre' => 4],
            ['code' => 'CONTRAT_ENSEIGNANT', 'libelle' => 'Contrat enseignant', 'ordre' => 5],
            ['code' => 'CONTRAT_PERSONNEL', 'libelle' => 'Contrat personnel INGRIDD', 'ordre' => 6],
        ];

        foreach ($types as $type) {
            TypeContrat::query()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
