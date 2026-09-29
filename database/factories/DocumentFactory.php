<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'description' => fake()->optional()->paragraph(),
            'type_document_id' => TypeDocument::factory(),
            'chemin' => 'uploads/'.fake()->uuid().'.pdf',
            'nom_original' => fake()->word().'.pdf',
            'extension' => 'pdf',
            'taille' => fake()->numberBetween(1000, 500000),
            'uploaded_by' => User::factory(),
        ];
    }
}
