<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "Liste des étudiants (finances)" pour une année académique — voir le
 * docblock de la migration 2026_10_20_000003_create_listes_finances_etudiants_table.php.
 * Structure IDENTIQUE à App\Models\RecetteDossier (liste en nombre libre de
 * fichiers), mais écran séparé de Gestion des recettes.
 */
class ListeFinanceEtudiant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // Explicite — sans ça Eloquent ne pluraliserait que le dernier segment
    // ("ListeFinanceEtudiant" -> "liste_finance_etudiants" au lieu de
    // "listes_finances_etudiants"), même bug que App\Models\TypeDepense
    // (voir son docblock).
    protected $table = 'listes_finances_etudiants';

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
        return $this->hasMany(ListeFinanceEtudiantFichier::class);
    }
}
