<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier d'une App\Models\Reunion — voir le docblock de la migration
 * 2026_10_07_000002_create_reunion_fichiers_table.php pour le détail complet
 * des deux rôles (`rapport`, case fixe unique ; `piece_jointe`, liste libre).
 * Pas de SoftDeletes ici, comme les autres tables de "cases" du projet : une
 * ligne n'a aucun sens indépendamment de sa réunion parente.
 */
class ReunionFichier extends Model
{
    use HasFactory;

    /** Case fixe, unique, pré-créée vide à la création de la réunion. */
    public const ROLE_RAPPORT = 'rapport';

    /** Liste libre, aucune case pré-créée — une ligne par pièce jointe réellement ajoutée. */
    public const ROLE_PIECE_JOINTE = 'piece_jointe';

    protected $table = 'reunion_fichiers';

    protected $fillable = [
        'reunion_id',
        'role',
        'numero',
        'libelle',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function estRemplie(): bool
    {
        return $this->document_id !== null;
    }

    /**
     * "Rapport de la réunion" pour la case fixe ; pour une pièce jointe, le
     * libellé saisi à l'ajout (ex. "Liste de présence") ou, à défaut, le nom
     * original du fichier déposé — jamais de texte vide dans la liste.
     */
    public function libelle(): string
    {
        if ($this->role === self::ROLE_RAPPORT) {
            return 'Rapport de la réunion';
        }

        return $this->libelle ?: ($this->document?->nom_original ?? 'Pièce jointe');
    }
}
