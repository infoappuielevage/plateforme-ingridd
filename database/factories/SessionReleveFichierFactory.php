<?php

namespace Database\Factories;

use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionReleveFichier> */
class SessionReleveFichierFactory extends Factory
{
    protected $model = SessionReleveFichier::class;

    public function definition(): array
    {
        return [
            'session_releve_id' => SessionReleve::factory(),
            'role' => SessionReleveFichier::ROLE_SEMESTRE,
            'numero' => null,
            'document_id' => null,
        ];
    }
}
