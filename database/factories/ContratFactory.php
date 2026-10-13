<?php

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\TypeContrat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contrat> */
class ContratFactory extends Factory
{
    protected $model = Contrat::class;

    public function definition(): array
    {
        return [
            'type_contrat_id' => TypeContrat::factory(),
            'partenaire_prestataire' => fake()->company(),
            'objet' => fake()->sentence(4),
            'montant' => null,
            'date_signature' => null,
            'date_debut' => null,
            'date_fin' => null,
            'statut' => Contrat::STATUT_ACTIF,
            'responsable_id' => null,
            'contact_nom' => null,
            'contact_email' => null,
            'contact_telephone' => null,
            'observations' => null,
        ];
    }
}
