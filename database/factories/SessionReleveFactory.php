<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\SessionReleve;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionReleve> */
class SessionReleveFactory extends Factory
{
    protected $model = SessionReleve::class;

    public function definition(): array
    {
        return [
            'annee_academique_id' => AnneeAcademique::factory(),
            'session' => SessionReleve::SESSION_PREMIERE,
            'cycle' => SessionReleve::CYCLE_LICENCE,
            'filiere_id' => Filiere::factory(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
