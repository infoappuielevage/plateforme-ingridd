<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Reunion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reunion> */
class ReunionFactory extends Factory
{
    protected $model = Reunion::class;

    public function definition(): array
    {
        return [
            'annee_academique_id' => AnneeAcademique::factory(),
            'date_reunion' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'sous_type_document_id' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
