<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Semestres;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une session de relevé de notes = une combinaison année académique /
 * session (1ère ou 2ème) / cycle (Licence/Master/Doctorat) / filière — voir
 * la migration 2026_10_01_000001 pour le contexte complet.
 *
 * Volontairement le MIROIR de App\Models\SessionDeliberation ("même chose
 * que pour le PV de délibération", demande explicite du client une fois
 * celle-ci validée) plutôt qu'une classe de base commune : les deux tables
 * restent indépendantes (voir le docblock de la migration), et dupliquer ces
 * ~60 lignes reste plus simple à lire/modifier séparément qu'une
 * abstraction générique — même choix que TypeDocumentController face à
 * ReferentielController (voir son docblock).
 */
class SessionReleve extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const SESSION_PREMIERE = 'premiere';

    public const SESSION_DEUXIEME = 'deuxieme';

    public const CYCLE_LICENCE = 'licence';

    public const CYCLE_MASTER = 'master';

    public const CYCLE_DOCTORAT = 'doctorat';

    /** @var array<string, string> valeur enregistrée => libellé affiché */
    public const SESSIONS = [
        self::SESSION_PREMIERE => 'Première session',
        self::SESSION_DEUXIEME => 'Deuxième session',
    ];

    /** @var array<string, string> valeur enregistrée => libellé affiché */
    public const CYCLES = [
        self::CYCLE_LICENCE => 'Licence',
        self::CYCLE_MASTER => 'Master',
        self::CYCLE_DOCTORAT => 'Doctorat',
    ];

    /** Semestres Licence (L1S1 ... L3S6) et Master (M1S7 ... M2S10) — voir App\Support\Semestres. */
    protected const SEMESTRES_PAR_CYCLE = [
        self::CYCLE_LICENCE => [1, 2, 3, 4, 5, 6],
        self::CYCLE_MASTER => [7, 8, 9, 10],
    ];

    protected $table = 'sessions_releves';

    protected $fillable = [
        'annee_academique_id',
        'session',
        'cycle',
        'filiere_id',
        'description',
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

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(SessionReleveFichier::class);
    }

    public function sessionLibelle(): string
    {
        return self::SESSIONS[$this->session] ?? $this->session;
    }

    public function cycleLibelle(): string
    {
        return self::CYCLES[$this->cycle] ?? $this->cycle;
    }

    /**
     * Numéros de semestre (Licence/Master) ou d'année de thèse (Doctorat) à
     * prévoir pour CE cycle — voir SessionDeliberation::numerosAttendus()
     * (identique).
     *
     * @return array<int, int>
     */
    public function numerosAttendus(): array
    {
        if ($this->cycle === self::CYCLE_DOCTORAT) {
            return range(1, self::nombreAnneesTheseDoctorat());
        }

        return self::SEMESTRES_PAR_CYCLE[$this->cycle] ?? [];
    }

    /** Rôle des cases numérotées pour ce cycle : "semestre" (Licence/Master) ou "annee_these" (Doctorat). */
    public function roleNumerote(): string
    {
        return $this->cycle === self::CYCLE_DOCTORAT
            ? SessionReleveFichier::ROLE_ANNEE_THESE
            : SessionReleveFichier::ROLE_SEMESTRE;
    }

    /** Libellé d'une case numérotée (ex. "Semestre 5" ou "Année de thèse 2"), selon le cycle de CETTE session. */
    public function libelleNumero(int $numero): string
    {
        return $this->cycle === self::CYCLE_DOCTORAT
            ? "Année de thèse {$numero}"
            : "Semestre {$numero}";
    }

    /**
     * "Licence 1"/"Licence 2"/"Licence 3" ou "Master 1"/"Master 2" — en-tête
     * de regroupement sur l'écran de gestion des fichiers, voir
     * SessionDeliberation::groupePourNumero() (identique) pour le contexte
     * complet (demande explicite du client).
     */
    public function groupePourNumero(int $numero): ?string
    {
        if ($this->cycle === self::CYCLE_DOCTORAT) {
            return null;
        }

        $code = Semestres::codeNiveauPourSemestre($numero);

        return match ($code) {
            'L1' => 'Licence 1',
            'L2' => 'Licence 2',
            'L3' => 'Licence 3',
            'M1' => 'Master 1',
            'M2' => 'Master 2',
            default => null,
        };
    }

    /**
     * Réglage PARTAGÉ avec App\Models\SessionDeliberation (même colonne
     * Parametre::nombre_annees_these_doctorat) : la durée d'une thèse est la
     * même quel que soit le document concerné (PV ou relevé), pas de raison
     * d'avoir deux réglages différents pour la même réalité académique —
     * voir Administration > Paramètres > "Doctorat".
     */
    public static function nombreAnneesTheseDoctorat(): int
    {
        return Parametre::actuel()->nombre_annees_these_doctorat ?? 3;
    }

    /** @return array<int, string> pour un <select> — reprend Semestres::toutesLesOptions(). */
    public static function optionsSemestres(): array
    {
        return Semestres::toutesLesOptions();
    }
}
