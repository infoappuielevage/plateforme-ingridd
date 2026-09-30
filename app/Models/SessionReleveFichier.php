<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une case au sein d'une App\Models\SessionReleve — miroir de
 * App\Models\SessionDeliberationFichier (voir son docblock pour le détail
 * complet des choix : pas de SoftDeletes ici, une case n'a aucun sens
 * indépendamment de sa session parente).
 *
 * Contrairement à SessionDeliberationFichier, pas de cases "Note
 * d'information" ici : demande explicite du client, les relevés de notes
 * n'ont besoin que des cases numérotées (semestre ou année de thèse) — voir
 * SessionReleveController::creerCasesVides().
 */
class SessionReleveFichier extends Model
{
    use HasFactory;

    public const ROLE_SEMESTRE = 'semestre';

    public const ROLE_ANNEE_THESE = 'annee_these';

    /** Case d'un champ supplémentaire ajouté depuis l'administration — voir App\Models\ChampSupplementaire. */
    public const ROLE_SUPPLEMENTAIRE = 'supplementaire';

    protected $table = 'session_releve_fichiers';

    protected $fillable = [
        'session_releve_id',
        'role',
        'numero',
        'champ_supplementaire_id',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    public function sessionReleve(): BelongsTo
    {
        return $this->belongsTo(SessionReleve::class);
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
     * Le champ supplémentaire peut avoir été supprimé définitivement entre
     * temps (voir `nullOnDelete()` dans la migration) : le texte générique
     * ci-dessous évite alors une case sans aucun libellé plutôt que de
     * planter.
     */
    public function libelle(): string
    {
        if ($this->role === self::ROLE_SUPPLEMENTAIRE) {
            return $this->champSupplementaire?->libelle ?? 'Champ supplémentaire (supprimé)';
        }

        return $this->sessionReleve?->libelleNumero($this->numero ?? 0) ?? (string) $this->role;
    }
}
