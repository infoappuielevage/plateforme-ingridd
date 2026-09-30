<?php

namespace Database\Factories;

use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PvSoutenanceFichier> */
class PvSoutenanceFichierFactory extends Factory
{
    protected $model = PvSoutenanceFichier::class;

    public function definition(): array
    {
        return [
            'pv_soutenance_id' => PvSoutenance::factory(),
            'role' => PvSoutenanceFichier::ROLE_DIPLOME,
            'champ_supplementaire_id' => null,
            'document_id' => null,
        ];
    }
}
