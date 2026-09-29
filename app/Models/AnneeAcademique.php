<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Référentiel des années académiques (ex. "2025-2026") — même pattern que
 * Filiere/Niveau, mais un seul champ `libelle` (pas de `code` séparé : le
 * libellé "AAAA-AAAA" est déjà l'identifiant naturel) et un tri par défaut
 * du plus récent au plus ancien plutôt que par un champ `ordre` manuel.
 *
 * `SoftDeletes` : voir TypeDocument (même principe, corbeille pour les
 * référentiels — AnneeAcademiqueResource::table()).
 */
class AnneeAcademique extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'annees_academiques';

    protected $fillable = [
        'libelle',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
