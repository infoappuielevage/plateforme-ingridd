<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier d'une App\Models\ListeFinanceEtudiant — liste en nombre libre,
 * voir le docblock de la migration
 * 2026_10_20_000004_create_liste_finance_etudiant_fichiers_table.php. Pas de
 * SoftDeletes ici (même principe qu'App\Models\RecetteDossierFichier) :
 * "retirer" un fichier le retire ENTIÈREMENT de la liste (voir
 * ListeFinanceEtudiantController::supprimerFichier()), seul le
 * App\Models\Document attaché passe par la corbeille.
 */
class ListeFinanceEtudiantFichier extends Model
{
    use HasFactory;

    // Explicite par cohérence/prudence — voir App\Models\TypeDepense pour un
    // cas où le nom de table auto-déduit par Eloquent ne correspondait PAS
    // au nom réel de la table.
    protected $table = 'liste_finance_etudiant_fichiers';

    protected $fillable = [
        'liste_finance_etudiant_id',
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

    public function listeFinanceEtudiant(): BelongsTo
    {
        return $this->belongsTo(ListeFinanceEtudiant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Libellé affiché — celui saisi par l'utilisateur, ou le nom du fichier déposé à défaut (même principe que App\Models\RecetteDossierFichier::libelle()). */
    public function libelle(): string
    {
        return $this->libelle ?: ($this->document?->nom_original ?? 'Fichier');
    }
}
