<?php

namespace Database\Seeders;

use App\Models\Filiere;
use Illuminate\Database\Seeder;

/**
 * Liste réelle des filières de l'INGRIDD (sigle + nom complet), fournie par
 * l'établissement. L'ordre ci-dessous fixe l'ordre d'affichage par défaut
 * (colonne `ordre`) dans les listes déroulantes et les écrans de gestion.
 */
class FiliereSeeder extends Seeder
{
    public function run(): void
    {
        $filieres = [
            ['code' => 'EGEO', 'libelle' => 'Exploration Géologique', 'ordre' => 1],
            ['code' => 'GA', 'libelle' => 'Géosciences Appliquées', 'ordre' => 2],
            ['code' => 'GAE', 'libelle' => 'Gestion des Aspects Environnementaux', 'ordre' => 3],
            ['code' => 'GSSSU', 'libelle' => 'Gestion de la Santé Sécurité et Situation d\'Urgence', 'ordre' => 4],
            ['code' => 'QHSSE', 'libelle' => 'Qualité Hygiène Santé Sécurité Environnement', 'ordre' => 5],
        ];

        foreach ($filieres as $filiere) {
            Filiere::query()->updateOrCreate(['code' => $filiere['code']], $filiere);
        }
    }
}
