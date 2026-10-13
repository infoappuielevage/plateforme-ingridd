<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un dossier "Scolarité" de Gestion des Recettes — voir le docblock de la
 * migration 2026_10_17_000002_create_recette_scolarites_table.php pour le
 * contexte complet. Un seul dossier par année académique.
 */
class RecetteScolarite extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'recette_scolarites';

    protected $fillable = [
        'annee_academique_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(RecetteScolariteFichier::class);
    }
}
