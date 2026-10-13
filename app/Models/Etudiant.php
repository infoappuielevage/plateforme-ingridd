<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Le dossier d'un étudiant — voir le docblock de la migration
 * 2026_10_08_000001_create_etudiants_table.php pour le contexte complet.
 *
 * AUCUN champ niveau/filière/année académique directement ici : voir
 * inscriptions() et App\Models\EtudiantInscription — ces trois informations
 * changent chaque année, contrairement à l'identité de l'étudiant
 * (matricule, nom, prénoms) qui reste stable tout son parcours.
 */
class Etudiant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'matricule',
        'nom',
        'prenoms',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(EtudiantInscription::class);
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(EtudiantFichier::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenoms} {$this->nom}");
    }

    /**
     * L'inscription la plus récente (par libellé d'année académique
     * décroissant, ex. "2026-2027" avant "2025-2026") — celle qui détermine
     * le niveau/la filière "actuels" affichés sur la liste. Nécessite
     * `inscriptions.anneeAcademique` déjà chargés (voir
     * EtudiantController::index()/show()) pour éviter une requête N+1 ;
     * sans eager loading, Laravel la charge à la demande comme toute
     * relation.
     */
    public function inscriptionActuelle(): ?EtudiantInscription
    {
        return $this->inscriptions
            ->sortByDesc(fn (EtudiantInscription $inscription) => $inscription->anneeAcademique?->libelle)
            ->first();
    }
}
