<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un dossier "Retrait diplôme" ou "Frais de soutenance" de Gestion des
 * Recettes — voir le docblock de la migration
 * 2026_10_17_000004_create_recette_dossiers_table.php pour le contexte
 * complet, en particulier l'absence de toute relation avec les mécanismes
 * déjà existants par ailleurs (statut_retrait sur Document, PvSoutenance).
 */
class RecetteDossier extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPE_RETRAIT_DIPLOME = 'retrait_diplome';

    public const TYPE_FRAIS_SOUTENANCE = 'frais_soutenance';

    /** Libellé affiché par type — voir typeLibelle() ci-dessous. */
    public const TYPES = [
        self::TYPE_RETRAIT_DIPLOME => 'Retrait diplôme',
        self::TYPE_FRAIS_SOUTENANCE => 'Frais de soutenance',
    ];

    protected $table = 'recette_dossiers';

    protected $fillable = [
        'annee_academique_id',
        'type',
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
        return $this->hasMany(RecetteDossierFichier::class);
    }

    public function typeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
