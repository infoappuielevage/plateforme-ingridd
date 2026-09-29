<?php

namespace Database\Seeders;

use App\Models\Niveau;
use Illuminate\Database\Seeder;

/**
 * Les niveaux du système LMD (Licence-Master-Doctorat), commun aux
 * établissements francophones — contrairement aux filières (propres à
 * chaque établissement, non pré-remplies : à créer via Référentiels >
 * Filières), ces libellés sont suffisamment universels pour servir de
 * point de départ raisonnable.
 */
class NiveauSeeder extends Seeder
{
    public function run(): void
    {
        $niveaux = [
            ['code' => 'L1', 'libelle' => 'Licence 1', 'ordre' => 1],
            ['code' => 'L2', 'libelle' => 'Licence 2', 'ordre' => 2],
            ['code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3],
            ['code' => 'M1', 'libelle' => 'Master 1', 'ordre' => 4],
            ['code' => 'M2', 'libelle' => 'Master 2', 'ordre' => 5],
        ];

        foreach ($niveaux as $niveau) {
            Niveau::query()->updateOrCreate(['code' => $niveau['code']], $niveau);
        }
    }
}
