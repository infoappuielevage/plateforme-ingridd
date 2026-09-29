<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Référentiel (modelé sur TypeArchive d'ingridd-platform) : code/libelle/ordre/actif.
 *
 * `SoftDeletes` : même principe que les autres référentiels (Filiere,
 * Niveau, AnneeAcademique) — corbeille pour les référentiels, voir
 * TypeDocumentResource::table().
 *
 * `champs_diplome_actifs`/`champs_soutenance_actifs`/`champ_semestre_actif`/
 * `epingle_secretariat` : configuration du comportement de ce type,
 * modifiable depuis l'administration sans toucher au code (voir la
 * migration 2026_09_29_000021_..., App\Support\TypesDiplome et
 * App\Support\TypesPvSoutenance, qui lisent désormais ces colonnes plutôt
 * qu'une liste de codes figée dans le code).
 */
class TypeDocument extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'libelle',
        'ordre',
        'actif',
        'champs_diplome_actifs',
        'champs_soutenance_actifs',
        'champ_semestre_actif',
        'epingle_secretariat',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'ordre' => 'integer',
            'champs_diplome_actifs' => 'boolean',
            'champs_soutenance_actifs' => 'boolean',
            'champ_semestre_actif' => 'boolean',
            'epingle_secretariat' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** Sous-types configurés pour ce type (voir App\Models\SousTypeDocument) — triés par `ordre`. */
    public function sousTypes(): HasMany
    {
        return $this->hasMany(SousTypeDocument::class)->orderBy('ordre');
    }
}
