<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un PV de soutenance = UN étudiant, UNE soutenance — structure dédiée
 * demandée explicitement par le client ("PV DE SOUTENANCE, a cet niveau on
 * aura besoin de..."), en remplacement du simple "un document = un fichier"
 * utilisé jusqu'ici pour le type PV_SOUTENANCE. Voir le docblock de la
 * migration 2026_10_04_000001_create_pv_soutenances_table.php pour le
 * contexte complet (notamment pourquoi `niveau` est un référentiel
 * volontairement séparé de App\Models\Niveau).
 *
 * Contrairement à App\Models\SessionDeliberation/SessionReleve (un
 * enregistrement = une filière entière pour une session), un enregistrement
 * ici ne concerne qu'un seul étudiant, d'où les champs d'identité
 * directement sur cette classe plutôt que sur une table à part.
 *
 * Les fichiers proprement dits (diplôme, image du registre, champs
 * supplémentaires) sont gérés séparément, voir fichiers() et
 * App\Models\PvSoutenanceFichier.
 *
 * `photo` (portrait de l'étudiant, demande explicite du client) suit EXACTEMENT
 * le même principe que `avatar` sur App\Models\User (voir son docblock) :
 * volontairement ABSENTE de $fillable, modifiable uniquement via forceFill()
 * dans PvSoutenanceController::store()/update(), jamais par un simple
 * ->update() de masse — le chemin stocké ne doit provenir que d'un fichier
 * réellement uploadé et validé (voir TYPES_PHOTO_ACCEPTES dans le
 * contrôleur), jamais d'un champ texte arbitraire du formulaire.
 *
 * Retrait du diplôme (demande explicite du client, allant plus loin qu'un
 * simple "Retiré/Non retiré") : `diplome_retirant`/`diplome_nom_retirant`/
 * `diplome_date_retrait` directement sur CETTE classe plutôt que sur
 * App\Models\PvSoutenanceFichier — un PV de soutenance n'a qu'un seul
 * diplôme (case fixe, jamais répétée), inutile de passer par le fichier pour
 * ces champs. Voir le docblock de la migration
 * 2026_10_05_000001_add_retrait_diplome_to_pv_soutenances_table.php pour le
 * détail complet, notamment la distinction entre `diplome_date_retrait`
 * (saisie à la main, la date où la personne a physiquement pris le diplôme)
 * et `diplome_retrait_enregistre_le` (toujours "maintenant", jamais modifiable
 * à la main — quand un utilisateur connecté a saisi cette information).
 */
class PvSoutenance extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const NIVEAU_BTS = 'bts';

    /**
     * Ajouté sur demande explicite du client, après coup ("intègre également
     * le DTS également qui est un niveau qu'on avait pas mentionné") — DTS et
     * BTS sont deux diplômes de cycle court équivalents, d'où leur position
     * côte à côte dans NIVEAUX/les onglets de la liste (voir
     * PvSoutenanceController::requeteListe()).
     */
    public const NIVEAU_DTS = 'dts';

    public const NIVEAU_LICENCE = 'licence';

    public const NIVEAU_MASTER = 'master';

    public const NIVEAU_DOCTORAT = 'doctorat';

    /** @var array<string, string> valeur enregistrée => libellé affiché */
    public const NIVEAUX = [
        self::NIVEAU_BTS => 'BTS',
        self::NIVEAU_DTS => 'DTS',
        self::NIVEAU_LICENCE => 'Licence',
        self::NIVEAU_MASTER => 'Master',
        self::NIVEAU_DOCTORAT => 'Doctorat',
    ];

    public const DECISION_ADMIS = 'admis';

    public const DECISION_AJOURNE = 'ajourne';

    /** @var array<string, string> valeur enregistrée => libellé affiché — "Admis" par défaut (demande explicite du client). */
    public const DECISIONS = [
        self::DECISION_ADMIS => 'Admis',
        self::DECISION_AJOURNE => 'Ajourné',
    ];

    public const MENTION_PASSABLE = 'passable';

    public const MENTION_ASSEZ_BIEN = 'assez_bien';

    public const MENTION_BIEN = 'bien';

    public const MENTION_TRES_BIEN = 'tres_bien';

    /** @var array<string, string> valeur enregistrée => libellé affiché — liste exacte donnée par le client, pas de mention supplémentaire. */
    public const MENTIONS = [
        self::MENTION_PASSABLE => 'Passable',
        self::MENTION_ASSEZ_BIEN => 'Assez bien',
        self::MENTION_BIEN => 'Bien',
        self::MENTION_TRES_BIEN => 'Très bien',
    ];

    public const RETIRANT_ETUDIANT = 'etudiant';

    public const RETIRANT_AUTRE = 'autre';

    /** @var array<string, string> valeur enregistrée => libellé affiché */
    public const RETIRANTS = [
        self::RETIRANT_ETUDIANT => "L'étudiant lui-même",
        self::RETIRANT_AUTRE => 'Une autre personne',
    ];

    protected $table = 'pv_soutenances';

    protected $fillable = [
        'niveau',
        'date_soutenance',
        'numero_ordre_registre',
        'nom',
        'prenoms',
        'date_naissance',
        'lieu_naissance',
        'numero_pv',
        'filiere_id',
        'theme',
        'decision_jury',
        'moyenne',
        'mention',
        'president_jury',
        'directeur_memoire',
        'rapporteur',
        'maitre_stage',
        'created_by',
        'diplome_retirant',
        'diplome_nom_retirant',
        'diplome_date_retrait',
        'diplome_retrait_enregistre_par_id',
        'diplome_retrait_enregistre_le',
    ];

    protected function casts(): array
    {
        return [
            'date_soutenance' => 'date',
            'date_naissance' => 'date',
            'moyenne' => 'decimal:2',
            'diplome_date_retrait' => 'date',
            'diplome_retrait_enregistre_le' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** L'utilisateur connecté qui a SAISI le retrait du diplôme — pas la personne physiquement venue le chercher, voir diplomeRetirantLibelle(). */
    public function diplomeRetraitEnregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diplome_retrait_enregistre_par_id');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(PvSoutenanceFichier::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenoms} {$this->nom}");
    }

    public function niveauLibelle(): string
    {
        return self::NIVEAUX[$this->niveau] ?? $this->niveau;
    }

    /**
     * Classe CSS (voir public/css/style.css, section "PV de soutenance :
     * repères de couleur par niveau") pour afficher ce niveau sous forme de
     * badge coloré plutôt qu'en texte brut — demande explicite du client
     * ("il n'y a pas de point de repère, tout se ressemble"). Un repère
     * visuel rapide entre niveaux, surtout utile dans l'onglet "Tous" de la
     * liste où ils se mélangent (voir resources/views/gestion/pv-soutenances/index.blade.php).
     */
    public function niveauBadgeClasse(): string
    {
        return array_key_exists($this->niveau, self::NIVEAUX) ? 'bg-niveau-'.$this->niveau : 'text-bg-secondary';
    }

    public function decisionLibelle(): string
    {
        return self::DECISIONS[$this->decision_jury] ?? $this->decision_jury;
    }

    public function mentionLibelle(): string
    {
        return self::MENTIONS[$this->mention] ?? $this->mention;
    }

    /** Le diplôme a-t-il été physiquement retiré ? Basé sur diplome_date_retrait plutôt qu'un booléen dédié : renseignée <=> retiré. */
    public function estDiplomeRetire(): bool
    {
        return $this->diplome_date_retrait !== null;
    }

    /** "L'étudiant lui-même" ou le nom précisé pour "Une autre personne" — null si le diplôme n'a pas encore été retiré. */
    public function diplomeRetirantLibelle(): ?string
    {
        if ($this->diplome_retirant === self::RETIRANT_AUTRE) {
            return $this->diplome_nom_retirant ?: 'Autre personne (nom non précisé)';
        }

        if ($this->diplome_retirant === self::RETIRANT_ETUDIANT) {
            return self::RETIRANTS[self::RETIRANT_ETUDIANT];
        }

        return null;
    }
}
