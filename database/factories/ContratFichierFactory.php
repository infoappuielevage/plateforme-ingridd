<?php

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\ContratFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContratFichier> */
class ContratFichierFactory extends Factory
{
    protected $model = ContratFichier::class;

    public function definition(): array
    {
        return [
            'contrat_id' => Contrat::factory(),
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
            'numero' => null,
            'libelle' => null,
            'document_id' => null,
        ];
    }
}
