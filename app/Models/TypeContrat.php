<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une catégorie de contrat (ex. "Partenariat", "Prestation de service"...) —
 * voir le docblock de la migration 2026_10_23_000001_create_types_contrats_table.php
 * pour le contexte complet. MÊME PRINCIPE que App\Models\TypeDepense : une
 * liste gérée depuis l'administration, jamais figée dans le code.
 */
class TypeContrat extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'types_contrats';

    protected $fillable = [
        'code',
        'libelle',
        'ordre',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    /**
     * Index de couleur (0 à 5) pour les onglets de
     * resources/views/gestion/contrats/_onglets.blade.php (classes
     * .palette-tab-N, voir public/css/style.css) — demande explicite du
     * client ("quand sa va devenir beaucoup sa sera compliqué intègre un
     * scroll pour les categorie et ameliore le design et mets en gras"),
     * MÊME PRINCIPE que App\Models\AnneeAcademique::paletteIndex() : ce
     * référentiel est OUVERT (une catégorie peut être ajoutée à tout moment
     * depuis Administration > Référentiels > Catégories de contrats),
     * impossible de réserver une couleur dédiée à chacune à l'avance — une
     * couleur PAR ROTATION, dérivée du libellé (stable tant qu'il ne change
     * pas, peu importe l'ordre d'affichage courant).
     */
    public function paletteIndex(): int
    {
        return crc32($this->libelle) % 6;
    }
}
