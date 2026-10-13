<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier d'un App\Models\RecetteScolarite — voir le docblock de la
 * migration 2026_10_17_000003_create_recette_scolarite_fichiers_table.php
 * pour le détail complet des cinq rôles. Pas de SoftDeletes ici, comme les
 * autres tables de "cases" du projet : une ligne n'a aucun sens
 * indépendamment de son dossier parent.
 */
class RecetteScolariteFichier extends Model
{
    use HasFactory;

    /** Case fixe, unique, pré-créée vide à la création du dossier. */
    public const ROLE_ORANGE_MONEY = 'orange_money';

    /** Case fixe, unique, pré-créée vide à la création du dossier. */
    public const ROLE_MOOV_MONEY = 'moov_money';

    /** Case fixe, unique, pré-créée vide à la création du dossier. */
    public const ROLE_TELECEL_MONEY = 'telecel_money';

    /** Liste libre, numérotée ("Bordereau 1", "Bordereau 2"...), aucune case pré-créée. */
    public const ROLE_BORDEREAU = 'bordereau';

    /** Liste libre, aucune case pré-créée — tout document de scolarité hors des quatre rôles ci-dessus. */
    public const ROLE_AUTRE = 'autre';

    /** Les trois cases fixes pré-créées à la création d'un dossier — voir RecetteScolariteController::creerCasesFixes(). */
    public const ROLES_FIXES = [self::ROLE_ORANGE_MONEY, self::ROLE_MOOV_MONEY, self::ROLE_TELECEL_MONEY];

    /** Libellé affiché par rôle fixe — voir libelle() ci-dessous. */
    public const LIBELLES_FIXES = [
        self::ROLE_ORANGE_MONEY => 'Relevé Orange Money',
        self::ROLE_MOOV_MONEY => 'Relevé Moov Money',
        self::ROLE_TELECEL_MONEY => 'Relevé Telecel Money',
    ];

    protected $table = 'recette_scolarite_fichiers';

    protected $fillable = [
        'recette_scolarite_id',
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

    public function recetteScolarite(): BelongsTo
    {
        return $this->belongsTo(RecetteScolarite::class);
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
     * Libellé lisible de la ligne — fixe pour les trois relevés Mobile
     * Money, "Bordereau {numero}" pour un bordereau, et le libellé saisi (ou
     * le nom original du fichier à défaut) pour "autre" — jamais de texte
     * vide dans la liste.
     */
    public function libelle(): string
    {
        if (array_key_exists($this->role, self::LIBELLES_FIXES)) {
            return self::LIBELLES_FIXES[$this->role];
        }

        if ($this->role === self::ROLE_BORDEREAU) {
            return 'Bordereau '.$this->numero;
        }

        return $this->libelle ?: ($this->document?->nom_original ?? 'Document');
    }
}
