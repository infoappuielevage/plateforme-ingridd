<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une case (voir la migration 2026_10_04_000002_create_pv_soutenance_fichiers_table.php
 * pour le contexte complet) au sein d'un App\Models\PvSoutenance. Pas de
 * SoftDeletes ici, comme SessionDeliberationFichier (voir son docblock) :
 * une case n'a aucun sens indépendamment de son PV parent, elle est
 * supprimée avec lui (cascadeOnDelete en base).
 */
class PvSoutenanceFichier extends Model
{
    use HasFactory;

    public const ROLE_DIPLOME = 'diplome';

    public const ROLE_IMAGE_REGISTRE = 'image_registre';

    /** Case d'un champ supplémentaire ajouté depuis l'administration — voir App\Models\ChampSupplementaire. */
    public const ROLE_SUPPLEMENTAIRE = 'supplementaire';

    /** @var array<string, string> valeur enregistrée => libellé affiché (rôles fixes uniquement). */
    public const ROLES_FIXES = [
        self::ROLE_DIPLOME => 'Diplôme',
        self::ROLE_IMAGE_REGISTRE => 'Image du PV (registre)',
    ];

    protected $table = 'pv_soutenance_fichiers';

    protected $fillable = [
        'pv_soutenance_id',
        'role',
        'champ_supplementaire_id',
        'document_id',
    ];

    public function pvSoutenance(): BelongsTo
    {
        return $this->belongsTo(PvSoutenance::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Uniquement renseigné pour role = ROLE_SUPPLEMENTAIRE — voir App\Models\ChampSupplementaire. */
    public function champSupplementaire(): BelongsTo
    {
        return $this->belongsTo(ChampSupplementaire::class);
    }

    public function estRemplie(): bool
    {
        return $this->document_id !== null;
    }

    /**
     * Libellé affiché pour cette case : celui du champ supplémentaire
     * concerné pour role = ROLE_SUPPLEMENTAIRE (texte générique si le champ a
     * été supprimé définitivement entre temps — voir `nullOnDelete()` dans la
     * migration), sinon le libellé fixe (Diplôme / Image du PV).
     */
    public function libelle(): string
    {
        if ($this->role === self::ROLE_SUPPLEMENTAIRE) {
            return $this->champSupplementaire?->libelle ?? 'Champ supplémentaire (supprimé)';
        }

        return self::ROLES_FIXES[$this->role] ?? (string) $this->role;
    }
}
