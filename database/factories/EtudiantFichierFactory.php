<?php

namespace Database\Factories;

use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EtudiantFichier> */
class EtudiantFichierFactory extends Factory
{
    protected $model = EtudiantFichier::class;

    public function definition(): array
    {
        return [
            'etudiant_id' => Etudiant::factory(),
            'numero' => 1,
            'libelle' => null,
            'document_id' => null,
        ];
    }
}
