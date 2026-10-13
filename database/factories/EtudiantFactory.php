<?php

namespace Database\Factories;

use App\Models\Etudiant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Etudiant> */
class EtudiantFactory extends Factory
{
    protected $model = Etudiant::class;

    public function definition(): array
    {
        return [
            'matricule' => strtoupper(fake()->unique()->bothify('ING-####??')),
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
        ];
    }
}
