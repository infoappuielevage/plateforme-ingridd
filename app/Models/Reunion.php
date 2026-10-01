<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un PV de réunion — voir le docblock de la migration
 * 2026_10_07_000001_create_reunions_table.php pour le contexte complet.
 *
 * Contrairement à App\Models\PvSoutenance (un étudiant) ou SessionDeliberation/
 * SessionReleve (une filière entière pour une session), une réunion ici ne
 * concerne ni étudiant ni filière : elle identifie simplement un événement
 * (date, type, année académique), d'où l'absence de filiere_id.
 *
 * Tous les champs restent modifiables après création (voir
 * ReunionController::update()) — contrairement aux PV de délibération/
 * Relevés de notes, rien ici ne dépend d'une combinaison figeant des cases
 * (pas de champ "cycle" déterminant un nombre de semestres) : corriger la
 * date ou le type d'une réunion après coup ne pose donc aucun problème.
 */
class Reunion extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'reunions';

    protected $fillable = [
        'annee_academique_id',
        'date_reunion',
        'sous_type_document_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_reunion' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    /** Le "type de réunion" (réunion hebdomadaire, extraordinaire...) — voir le docblock de la migration. */
    public function sousType(): BelongsTo
    {
        return $this->belongsTo(SousTypeDocument::class, 'sous_type_document_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(ReunionFichier::class);
    }

    /** Libellé du type de réunion, ou un texte générique si aucun sous-type n'est renseigné. */
    public function typeLibelle(): string
    {
        return $this->sousType?->libelle ?? 'Non précisé';
    }
}
