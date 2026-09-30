<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\SessionDeliberation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionDeliberation> */
class SessionDeliberationFactory extends Factory
{
    protected $model = SessionDeliberation::class;

    public function definition(): array
    {
        return [
            'annee_academique_id' => AnneeAcademique::factory(),
            'session' => SessionDeliberation::SESSION_PREMIERE,
            'cycle' => SessionDeliberation::CYCLE_LICENCE,
            'filiere_id' => Filiere::factory(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
