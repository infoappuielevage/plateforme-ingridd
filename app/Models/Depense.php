<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une dépense = une catégorie (App\Models\TypeDepense) × une année
 * académique, avec ses deux cases fixes (bordereau + Excel) — voir le
 * docblock de la migration 2026_10_20_000002_create_depenses_table.php.
 *
 * Une ligne est créée AUTOMATIQUEMENT (jamais par un formulaire "Nouvelle
 * dépense") — voir TypeDepense::synchroniserCasesRetroactives()/
 * synchroniserPourAnnee() et App\Models\AnneeAcademique::booted().
 */
class Depense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // Explicite par cohérence/prudence (le nom auto-déduit par Eloquent
    // correspond ici, mais voir App\Models\TypeDepense pour un cas où ce
    // n'était PAS le cas — déclarer $table partout évite de devoir vérifier
    // au cas par cas).
    protected $table = 'depenses';

    protected $fillable = [
        'annee_academique_id',
        'type_depense_id',
        'bordereau_document_id',
        'excel_document_id',
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

    public function typeDepense(): BelongsTo
    {
        return $this->belongsTo(TypeDepense::class);
    }

    public function bordereauDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'bordereau_document_id');
    }

    public function excelDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'excel_document_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bordereauRempli(): bool
    {
        return $this->bordereau_document_id !== null;
    }

    public function excelRempli(): bool
    {
        return $this->excel_document_id !== null;
    }
}
