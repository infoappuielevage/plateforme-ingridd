<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Référentiel des années académiques (ex. "2025-2026") — même pattern que
 * Filiere/Niveau, mais un seul champ `libelle` (pas de `code` séparé : le
 * libellé "AAAA-AAAA" est déjà l'identifiant naturel) et un tri par défaut
 * du plus récent au plus ancien plutôt que par un champ `ordre` manuel.
 *
 * `SoftDeletes` : voir TypeDocument (même principe, corbeille pour les
 * référentiels — AnneeAcademiqueResource::table()).
 */
class AnneeAcademique extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'annees_academiques';

    protected $fillable = [
        'libelle',
        'actif',
    ];

    /**
     * Crée automatiquement une ligne App\Models\Depense (vide) pour chaque
     * catégorie de dépense active, dès qu'une NOUVELLE année académique est
     * créée — demande explicite du client, voir App\Models\TypeDepense::
     * synchroniserPourAnnee()/synchroniserCasesRetroactives() (son pendant,
     * pour une NOUVELLE catégorie sur toutes les années déjà existantes).
     *
     * Un hook sur le modèle plutôt qu'un appel explicite depuis
     * App\Http\Controllers\ReferentielController::store() (contrairement à
     * App\Http\Controllers\EtudiantController::creerCasesIdentite(), appelée
     * directement par son propre contrôleur) : ReferentielController est
     * volontairement un contrôleur GÉNÉRIQUE partagé par les trois
     * référentiels "simples" (voir son docblock) — lui faire connaître
     * App\Models\Depense casserait cette mutualisation. Même principe que
     * App\Models\Document::booted() (déjà un hook sur le modèle dans ce
     * projet), pas une nouveauté d'architecture.
     */
    protected static function booted(): void
    {
        static::created(function (self $anneeAcademique) {
            TypeDepense::synchroniserPourAnnee($anneeAcademique);
        });
    }

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Index de couleur (0 à 5) pour l'affichage en onglet/badge coloré sur
     * l'écran PV de réunion (voir resources/views/gestion/reunions/index.blade.php
     * et public/css/style.css, classes .bg-palette-N/.palette-tab-N) — demande
     * explicite du client ("fait de même pour les PV de réunion pour les
     * couleurs et pour le tableau fait par année académique"), même esprit
     * que PvSoutenance::niveauBadgeClasse()/SessionDeliberation::cycleBadgeClasse().
     *
     * Contrairement à Niveau/Cycle (une poignée de valeurs fixes, chacune
     * avec SA couleur dédiée), ce référentiel est OUVERT : une nouvelle
     * année académique est créée chaque année, impossible de lui réserver
     * une couleur à l'avance. On attribue donc une couleur PAR ROTATION sur
     * une palette de 6 teintes, dérivée du libellé (stable tant que le
     * libellé ne change pas, peu importe le tri ou la pagination en cours —
     * contrairement à une position dans une liste, qui bougerait selon le
     * contexte d'affichage).
     */
    public function paletteIndex(): int
    {
        return crc32($this->libelle) % 6;
    }
}
