<?php

namespace Database\Factories;

use App\Models\TypePieceEtudiant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TypePieceEtudiant> */
class TypePieceEtudiantFactory extends Factory
{
    protected $model = TypePieceEtudiant::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PIECE_####')),
            'libelle' => ucfirst(fake()->words(2, true)),
            'recurrente' => false,
            'ordre' => 0,
            'actif' => true,
        ];
    }
}
