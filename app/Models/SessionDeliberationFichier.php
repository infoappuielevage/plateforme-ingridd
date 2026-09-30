<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une case (voir la migration 2026_09_30_000024_create_session_deliberation_fichiers_table.php
 * pour le contexte complet) au sein d'une App\Models\SessionDeliberation.
 * Pas de SoftDeletes ici, comme SousTypeDocument (voir son docblock) : une
 * case n'a aucun sens indépendamment de sa session parente, elle est
 * supprimée avec elle (cascadeOnDelete en base) — seul un hard-delete de la
 * session emporte ses cases.
 */
class SessionDeliberationFichier extends Model
{
    use HasFactory;

    public const ROLE_NOTE_ETUDIANTS = 'note_etudiants';

    public const ROLE_NOTE_ENSEIGNANTS = 'note_enseignants';

    public const ROLE_SEMESTRE = 'semestre';

    public const ROLE_ANNEE_THESE = 'annee_these';

    /** Case d'un champ supplémentaire ajouté depuis l'administration — voir App\Models\ChampSupplementaire. */
    public const ROLE_SUPPLEMENTAIRE = 'supplementaire';

    /** @var array<string, string> valeur enregistrée => libellé affiché (cases non numérotées uniquement). */
    public const ROLES_NOTES = [
        self::ROLE_NOTE_ETUDIANTS => "Note d'information — étudiants",
        self::ROLE_NOTE_ENSEIGNANTS => "Note d'information — enseignants",
    ];

    protected $table = 'session_deliberation_fichiers';

    protected $fillable = [
        'session_deliberation_id',
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

    public function sessionDeliberation(): BelongsTo
    {
        return $this->belongsTo(SessionDeliberation::class);
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
     * concerné, fixe pour les deux rôles "note_*", sinon délégué à la
     * session (dépend du cycle — "Semestre" ou "Année de thèse").
     *
     * Le champ supplémentaire peut avoir été supprimé définitivement entre
     * temps (voir `nullOnDelete()` dans la migration) : le texte générique
     * ci-dessous évite alors une case sans aucun libellé plutôt que de
     * planter — la case reste malgré tout accessible depuis Documents > Tous
     * les documents si un fichier y était déjà déposé.
     */
    public function libelle(): string
    {
        if ($this->role === self::ROLE_SUPPLEMENTAIRE) {
            return $this->champSupplementaire?->libelle ?? 'Champ supplémentaire (supprimé)';
        }

        if (array_key_exists($this->role, self::ROLES_NOTES)) {
            return self::ROLES_NOTES[$this->role];
        }

        return $this->sessionDeliberation?->libelleNumero($this->numero ?? 0) ?? (string) $this->role;
    }
}
