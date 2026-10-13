<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\EtudiantInscription;
use App\Models\Filiere;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EtudiantInscription> */
class EtudiantInscriptionFactory extends Factory
{
    protected $model = EtudiantInscription::class;

    public function definition(): array
    {
        return [
            'etudiant_id' => Etudiant::factory(),
            'annee_academique_id' => AnneeAcademique::factory(),
            'niveau_id' => Niveau::factory(),
            'filiere_id' => Filiere::factory(),
        ];
    }
}
