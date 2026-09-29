<?php

namespace Database\Factories;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SousTypeDocument> */
class SousTypeDocumentFactory extends Factory
{
    protected $model = SousTypeDocument::class;

    public function definition(): array
    {
        return [
            'type_document_id' => TypeDocument::factory(),
            'libelle' => fake()->unique()->words(2, true),
            'ordre' => fake()->numberBetween(1, 20),
            'actif' => true,
        ];
    }
}
