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
 * Une session de délibération = une combinaison année académique / session
 * (1ère ou 2ème) / cycle (Licence/Master/Doctorat) / filière — voir la
 * migration 2026_09_30_000023 pour le contexte complet (remplace l'ancien
 * "un document = un fichier" pour le type PV_DELIBERATION, sur demande du
 * client).
 *
 * Les fichiers proprement dits (notes d'information, PV par semestre/année
 * de thèse) sont gérés séparément, voir fichiers() ci-dessous et
 * App\Models\SessionDeliberationFichier — cette classe ne porte que
 * l'identification de la session.
 */
class SessionDeliberation extends Model
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

    /**
     * Semestres Licence (L1S1 ... L3S6) et Master (M1S7 ... M2S10) — reprend
     * telle quelle la numérotation continue de App\Support\Semestres (déjà
     * utilisée pour les relevés de notes), pour que "Semestre 7" désigne
     * toujours la même chose partout dans l'application.
     */
    protected const SEMESTRES_PAR_CYCLE = [
        self::CYCLE_LICENCE => [1, 2, 3, 4, 5, 6],
        self::CYCLE_MASTER => [7, 8, 9, 10],
    ];

    protected $table = 'sessions_deliberation';

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
        return $this->hasMany(SessionDeliberationFichier::class);
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
     * prévoir pour CE cycle — utilisé à la fois pour pré-créer les cases à
     * la création (voir SessionDeliberationController::store()) et pour
     * afficher la bonne liste de cases sur l'écran de gestion des fichiers.
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
            ? SessionDeliberationFichier::ROLE_ANNEE_THESE
            : SessionDeliberationFichier::ROLE_SEMESTRE;
    }

    /** Libellé d'une case numérotée (ex. "Semestre 5" ou "Année de thèse 2"), selon le cycle de CETTE session. */
    public function libelleNumero(int $numero): string
    {
        return $this->cycle === self::CYCLE_DOCTORAT
            ? "Année de thèse {$numero}"
            : "Semestre {$numero}";
    }

    /**
     * "Licence 1"/"Licence 2"/"Licence 3" (semestres 1-2/3-4/5-6) ou
     * "Master 1"/"Master 2" (semestres 7-8/9-10) — sert d'en-tête de
     * regroupement sur l'écran de gestion des fichiers (voir show.blade.php)
     * pour qu'on voie tout de suite que "Semestre 1" et "Semestre 2"
     * appartiennent tous les deux à Licence 1, etc. Reprend le même
     * découpage que App\Support\Semestres::PAR_CODE_NIVEAU (L1 = S1/S2...) —
     * demande explicite du client, qui trouvait la simple liste "Semestre 1
     * à 6" pas assez claire sur ce point.
     *
     * Null pour le Doctorat : chaque case ("Année de thèse N") est déjà
     * elle-même son propre regroupement, pas besoin d'un niveau intermédiaire.
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
     * Réglable depuis Administration > Paramètres (le client a explicitement
     * demandé de ne pas figer ce nombre dans le code, contrairement à la
     * numérotation Licence/Master — voir App\Support\Semestres). 3 par
     * défaut tant que rien n'a été enregistré (ligne créée avant l'ajout de
     * ce réglage, voir migration 2026_09_30_000022).
     */
    public static function nombreAnneesTheseDoctorat(): int
    {
        return Parametre::actuel()->nombre_annees_these_doctorat ?? 3;
    }

    /** @return array<int, string> pour un <select> — reprend Semestres::toutesLesOptions() pour rester cohérent avec le reste de l'application. */
    public static function optionsSemestres(): array
    {
        return Semestres::toutesLesOptions();
    }
}
