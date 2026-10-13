<?php

namespace Database\Factories;

use App\Models\TypeContrat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TypeContrat> */
class TypeContratFactory extends Factory
{
    protected $model = TypeContrat::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TYPE_??##')),
            'libelle' => fake()->words(2, true),
            'ordre' => fake()->numberBetween(0, 20),
            'actif' => true,
        ];
    }
}
