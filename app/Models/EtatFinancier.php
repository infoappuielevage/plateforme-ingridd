<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un état financier — voir le docblock de la migration
 * 2026_10_17_000001_create_etats_financiers_table.php pour le contexte
 * complet. Structure volontairement minimale (année + fichier +
 * observation) : `document_id` vit directement ici (pas de table de "cases"
 * séparée, un seul fichier par enregistrement), voir
 * App\Http\Controllers\EtatFinancierController.
 */
class EtatFinancier extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'etats_financiers';

    protected $fillable = [
        'annee',
        'document_id',
        'observation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estRempli(): bool
    {
        return $this->document_id !== null;
    }
}
