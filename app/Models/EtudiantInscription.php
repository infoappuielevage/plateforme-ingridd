<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une ligne de l'historique d'inscriptions d'un App\Models\Etudiant — voir
 * le docblock de la migration 2026_10_08_000002_create_etudiant_inscriptions_table.php
 * pour le détail complet (une ligne par année académique, niveau + filière
 * pour cette année précise). Pas de SoftDeletes ici, comme les autres tables
 * de "cases"/historique du projet : une ligne n'a aucun sens indépendamment
 * de son étudiant.
 */
class EtudiantInscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'annee_academique_id',
        'niveau_id',
        'filiere_id',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    /**
     * Les cases récurrentes créées pour CETTE année précise (voir
     * App\Models\TypePieceEtudiant::$recurrente) — utilisée notamment par
     * App\Http\Controllers\EtudiantController::supprimerInscription() pour
     * mettre à la corbeille les documents encore attachés avant de retirer
     * l'inscription (la suppression en base des lignes elle-même est gérée
     * par `cascadeOnDelete()`, voir la migration
     * 2026_10_09_000002_add_type_piece_et_inscription_to_etudiant_fichiers_table.php).
     */
    public function fichiers(): HasMany
    {
        return $this->hasMany(EtudiantFichier::class);
    }
}
