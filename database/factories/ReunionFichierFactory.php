<?php

namespace Database\Factories;

use App\Models\Reunion;
use App\Models\ReunionFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReunionFichier> */
class ReunionFichierFactory extends Factory
{
    protected $model = ReunionFichier::class;

    public function definition(): array
    {
        return [
            'reunion_id' => Reunion::factory(),
            'role' => ReunionFichier::ROLE_RAPPORT,
            'numero' => null,
            'libelle' => null,
            'document_id' => null,
        ];
    }
}
