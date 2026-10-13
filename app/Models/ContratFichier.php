<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier d'un App\Models\Contrat — voir le docblock de la migration
 * 2026_10_23_000003_create_contrat_fichiers_table.php pour le détail complet
 * des deux rôles (`contrat_signe`, case fixe unique ; `avenant`, liste
 * libre). Pas de SoftDeletes ici, comme les autres tables de "cases" du
 * projet : une ligne n'a aucun sens indépendamment de son contrat parent.
 */
class ContratFichier extends Model
{
    use HasFactory;

    /** Case fixe, unique, pré-créée vide à la création du contrat. */
    public const ROLE_CONTRAT_SIGNE = 'contrat_signe';

    /** Liste libre, aucune case pré-créée — une ligne par avenant réellement ajouté. */
    public const ROLE_AVENANT = 'avenant';

    protected $table = 'contrat_fichiers';

    protected $fillable = [
        'contrat_id',
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

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
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
     * "Contrat signé" pour la case fixe ; pour un avenant, le libellé saisi
     * à l'ajout (ex. "Avenant n°1 — prolongation") ou, à défaut, le nom
     * original du fichier déposé — jamais de texte vide dans la liste.
     */
    public function libelle(): string
    {
        if ($this->role === self::ROLE_CONTRAT_SIGNE) {
            return 'Contrat signé';
        }

        return $this->libelle ?: ($this->document?->nom_original ?? 'Avenant');
    }
}
