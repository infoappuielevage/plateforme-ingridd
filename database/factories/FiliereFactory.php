<?php

namespace Database\Factories;

use App\Models\Filiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Filiere> */
class FiliereFactory extends Factory
{
    protected $model = Filiere::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('FIL???')),
            'libelle' => fake()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
            'actif' => true,
        ];
    }
}
