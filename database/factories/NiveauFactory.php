<?php

namespace Database\Factories;

use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Niveau> */
class NiveauFactory extends Factory
{
    protected $model = Niveau::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('NIV???')),
            'libelle' => fake()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
            'actif' => true,
        ];
    }
}
