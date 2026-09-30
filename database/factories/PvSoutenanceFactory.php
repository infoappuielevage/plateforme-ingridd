<?php

namespace Database\Factories;

use App\Models\Filiere;
use App\Models\PvSoutenance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PvSoutenance> */
class PvSoutenanceFactory extends Factory
{
    protected $model = PvSoutenance::class;

    public function definition(): array
    {
        return [
            'niveau' => PvSoutenance::NIVEAU_LICENCE,
            'date_soutenance' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'numero_ordre_registre' => (string) fake()->unique()->numberBetween(1, 100000),
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'date_naissance' => fake()->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'),
            'lieu_naissance' => fake()->city(),
            'numero_pv' => 'PV-'.Str::upper(fake()->unique()->bothify('??####')),
            'filiere_id' => Filiere::factory(),
            'theme' => fake()->sentence(8),
            'decision_jury' => PvSoutenance::DECISION_ADMIS,
            'moyenne' => fake()->randomFloat(2, 10, 18),
            'mention' => PvSoutenance::MENTION_BIEN,
            'president_jury' => fake()->name(),
            'directeur_memoire' => fake()->name(),
            'rapporteur' => fake()->name(),
            'maitre_stage' => fake()->name(),
        ];
    }
}
