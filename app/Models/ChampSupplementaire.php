<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un champ supplémentaire = un document propre ajouté depuis
 * l'administration, SANS toucher au code, à déposer sur chaque PV de
 * délibération, Relevé de notes et/ou PV de soutenance concerné — demande
 * explicite du client ("pouvoir ajouter un champ ... si on veut ajouter un
 * autre document dans le PV ou dans un relevé de notes"), étendue au PV de
 * soutenance ("Oui, via l'administration").
 *
 * `pour_pv`/`pour_releve`/`pour_soutenance` : un même champ peut s'appliquer
 * à un, plusieurs ou tous les trois — coché au cas par cas plutôt qu'une
 * seule case commune, pour rester flexible (ex. un document propre au
 * Relevé uniquement).
 *
 * `cycles` (JSON) : cycles/niveaux concernés, ex. ["doctorat"] pour un
 * document qui ne s'applique qu'au Doctorat. Sous-ensemble des clés de
 * cyclesDisponibles() ci-dessous — union de SessionDeliberation::CYCLES
 * (licence/master/doctorat, partagé avec SessionReleve) et du "bts"
 * supplémentaire nécessaire au PV de soutenance (seul des trois à connaître
 * le BTS, voir App\Models\PvSoutenance::NIVEAUX) : "cycle" (PV/Relevé) et
 * "niveau" (PV de soutenance) désignent la même notion ici, partagée par les
 * trois usages via cette unique colonne JSON.
 *
 * `actif` : désactiver un champ l'empêche d'apparaître sur les NOUVEAUX
 * PV/sessions et arrête sa synchronisation rétroactive (voir
 * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()),
 * mais ne touche JAMAIS aux cases déjà créées sur des PV/sessions existants —
 * jamais de suppression automatique d'un fichier déjà déposé.
 */
class ChampSupplementaire extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'champs_supplementaires';

    protected $fillable = [
        'libelle',
        'pour_pv',
        'pour_releve',
        'pour_soutenance',
        'cycles',
        'ordre',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'pour_pv' => 'boolean',
            'pour_releve' => 'boolean',
            'pour_soutenance' => 'boolean',
            'cycles' => 'array',
            'ordre' => 'integer',
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /** Cases de PV de délibération créées pour ce champ (voir App\Models\SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE). */
    public function fichiersPv(): HasMany
    {
        return $this->hasMany(SessionDeliberationFichier::class);
    }

    /** Cases de Relevé de notes créées pour ce champ (voir App\Models\SessionReleveFichier::ROLE_SUPPLEMENTAIRE). */
    public function fichiersReleve(): HasMany
    {
        return $this->hasMany(SessionReleveFichier::class);
    }

    /** Cases de PV de soutenance créées pour ce champ (voir App\Models\PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE). */
    public function fichiersSoutenance(): HasMany
    {
        return $this->hasMany(PvSoutenanceFichier::class);
    }

    /**
     * Valeurs possibles pour `cycles` — union de SessionDeliberation::CYCLES
     * et des niveaux "bts"/"dts" propres au PV de soutenance (voir le
     * docblock de la classe). BTS et DTS en tête (cycles courts équivalents,
     * voir App\Models\PvSoutenance::NIVEAU_DTS), dans l'ordre donné par le
     * client (BTS, DTS, Licence, Master, Doctorat).
     *
     * @return array<string, string>
     */
    public static function cyclesDisponibles(): array
    {
        return [
            PvSoutenance::NIVEAU_BTS => 'BTS',
            PvSoutenance::NIVEAU_DTS => 'DTS',
        ] + SessionDeliberation::CYCLES;
    }

    /** Libellés lisibles des cycles concernés (ex. "Licence, Doctorat"), pour l'affichage dans la liste. */
    public function cyclesLibelle(): string
    {
        if (empty($this->cycles)) {
            return '—';
        }

        $libelles = self::cyclesDisponibles();

        return collect($this->cycles)
            ->map(fn (string $cycle) => $libelles[$cycle] ?? $cycle)
            ->implode(', ');
    }

    /** Ce champ doit-il apparaître, pour CE cycle, sur les PV de délibération ? */
    public function applicablePv(string $cycle): bool
    {
        return $this->actif && $this->pour_pv && in_array($cycle, $this->cycles ?? [], true);
    }

    /** Ce champ doit-il apparaître, pour CE cycle, sur les Relevés de notes ? */
    public function applicableReleve(string $cycle): bool
    {
        return $this->actif && $this->pour_releve && in_array($cycle, $this->cycles ?? [], true);
    }

    /** Ce champ doit-il apparaître, pour CE niveau, sur les PV de soutenance ? */
    public function applicableSoutenance(string $niveau): bool
    {
        return $this->actif && $this->pour_soutenance && in_array($niveau, $this->cycles ?? [], true);
    }
}
