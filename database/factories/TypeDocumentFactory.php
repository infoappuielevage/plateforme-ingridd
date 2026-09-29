<?php

namespace Database\Factories;

use App\Models\TypeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TypeDocument> */
class TypeDocumentFactory extends Factory
{
    protected $model = TypeDocument::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('TYP???')),
            'libelle' => fake()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
            'actif' => true,
        ];
    }
}
