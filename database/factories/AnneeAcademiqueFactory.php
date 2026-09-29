<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnneeAcademique> */
class AnneeAcademiqueFactory extends Factory
{
    protected $model = AnneeAcademique::class;

    public function definition(): array
    {
        $debut = fake()->unique()->numberBetween(2015, 2040);

        return [
            'libelle' => $debut.'-'.($debut + 1),
            'actif' => true,
        ];
    }
}
