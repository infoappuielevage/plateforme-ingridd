<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une catégorie de dépense (ex. "Bancaire", "Vacataire"...) — voir le
 * docblock de la migration 2026_10_20_000001_create_types_depenses_table.php
 * pour le contexte complet. MÊME PRINCIPE que App\Models\TypePieceEtudiant :
 * une liste gérée depuis l'administration, jamais figée dans le code.
 */
class TypeDepense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // Explicite — sans ça Eloquent ne pluraliserait que le dernier segment
    // ("TypeDepense" -> "type_depenses" au lieu de "types_depenses", vu en
    // pratique : SQLSTATE[42S02] au premier `db:seed --class=TypeDepenseSeeder`
    // du client). Même raison que App\Models\TypePieceEtudiant/
    // ChampSupplementaire, qui déclarent toutes les deux explicitement $table.
    protected $table = 'types_depenses';

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

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    /**
     * Ajoute une ligne App\Models\Depense (vide) pour CETTE catégorie sur
     * CHAQUE année académique déjà existante — demande explicite du client,
     * même principe que TypePieceEtudiant::synchroniserCasesRetroactives() :
     * une catégorie ajoutée (ou réactivée) après coup doit aussi profiter
     * aux années académiques déjà créées, pas seulement aux nouvelles. Ne
     * retire JAMAIS une ligne existante, même si la catégorie est désactivée
     * ensuite.
     *
     * Appelée par App\Http\Controllers\TypeDepenseController::store()/
     * update()/restore() ET par database/seeders/TypeDepenseSeeder.php — le
     * pendant pour une NOUVELLE année académique est
     * synchroniserPourAnnee(), appelée depuis App\Models\AnneeAcademique::booted().
     */
    public function synchroniserCasesRetroactives(): void
    {
        if (! $this->actif) {
            return;
        }

        AnneeAcademique::query()->each(function (AnneeAcademique $annee) {
            $this->creerDepenseSiAbsente($annee);
        });
    }

    /**
     * Pendant de synchroniserCasesRetroactives() ci-dessus, pour UNE SEULE
     * année académique (celle qui vient d'être créée) : ajoute une ligne
     * App\Models\Depense (vide) pour CHAQUE catégorie active — voir
     * App\Models\AnneeAcademique::booted().
     */
    public static function synchroniserPourAnnee(AnneeAcademique $anneeAcademique): void
    {
        self::query()->where('actif', true)->get()->each(
            fn (self $type) => $type->creerDepenseSiAbsente($anneeAcademique)
        );
    }

    protected function creerDepenseSiAbsente(AnneeAcademique $anneeAcademique): void
    {
        Depense::query()->firstOrCreate([
            'annee_academique_id' => $anneeAcademique->id,
            'type_depense_id' => $this->id,
        ]);
    }

    /**
     * Index de couleur (0 à 5) pour les onglets de
     * resources/views/gestion/depenses/_onglets.blade.php (classes
     * .palette-tab-N, voir public/css/style.css) — demande explicite du
     * client ("fais de meme pour et recette et depense, etc.." après le même
     * traitement sur App\Models\TypeContrat), MÊME PRINCIPE que
     * App\Models\TypeContrat::paletteIndex()/App\Models\AnneeAcademique::
     * paletteIndex() : ce référentiel est OUVERT (une catégorie peut être
     * ajoutée à tout moment depuis Administration > Référentiels > Catégories
     * de dépenses), impossible de réserver une couleur dédiée à chacune à
     * l'avance — une couleur PAR ROTATION, dérivée du libellé (stable tant
     * qu'il ne change pas, peu importe l'ordre d'affichage courant).
     */
    public function paletteIndex(): int
    {
        return crc32($this->libelle) % 6;
    }
}
