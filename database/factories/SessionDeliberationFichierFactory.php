<?php

namespace Database\Factories;

use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionDeliberationFichier> */
class SessionDeliberationFichierFactory extends Factory
{
    protected $model = SessionDeliberationFichier::class;

    public function definition(): array
    {
        return [
            'session_deliberation_id' => SessionDeliberation::factory(),
            'role' => SessionDeliberationFichier::ROLE_NOTE_ETUDIANTS,
            'numero' => null,
            'document_id' => null,
        ];
    }
}
