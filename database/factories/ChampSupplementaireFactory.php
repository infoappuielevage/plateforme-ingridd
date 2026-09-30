<?php

namespace Database\Factories;

use App\Models\ChampSupplementaire;
use App\Models\SessionDeliberation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChampSupplementaire> */
class ChampSupplementaireFactory extends Factory
{
    protected $model = ChampSupplementaire::class;

    public function definition(): array
    {
        return [
            'libelle' => ucfirst(fake()->words(3, true)),
            'pour_pv' => true,
            'pour_releve' => false,
            'pour_soutenance' => false,
            'cycles' => [SessionDeliberation::CYCLE_LICENCE],
            'ordre' => 0,
            'actif' => true,
        ];
    }
}
