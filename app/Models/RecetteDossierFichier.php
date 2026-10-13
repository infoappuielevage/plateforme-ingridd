<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier d'un App\Models\RecetteDossier — voir le docblock de la
 * migration 2026_10_17_000005_create_recette_dossier_fichiers_table.php.
 * Liste libre, toujours remplie (aucune ligne vide n'est jamais créée),
 * comme le rôle `piece_jointe` d'App\Models\ReunionFichier.
 */
class RecetteDossierFichier extends Model
{
    use HasFactory;

    protected $table = 'recette_dossier_fichiers';

    protected $fillable = [
        'recette_dossier_id',
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

    public function recetteDossier(): BelongsTo
    {
        return $this->belongsTo(RecetteDossier::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function libelle(): string
    {
        return $this->libelle ?: ($this->document?->nom_original ?? 'Fichier');
    }
}
