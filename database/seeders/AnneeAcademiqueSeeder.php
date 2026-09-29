<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use Illuminate\Database\Seeder;

/**
 * Quelques années académiques de départ, jusqu'à l'année en cours — un outil
 * d'archives a normalement besoin de pouvoir classer des documents plus
 * anciens dès le premier jour, contrairement aux niveaux (LMD, universels)
 * ou aux filières (propres à l'établissement, non pré-remplies). L'admin
 * peut en ajouter/désactiver depuis Référentiels > Années académiques.
 */
class AnneeAcademiqueSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(2020, 2026) as $debut) {
            AnneeAcademique::query()->updateOrCreate(
                ['libelle' => $debut.'-'.($debut + 1)],
                ['actif' => true]
            );
        }
    }
}
