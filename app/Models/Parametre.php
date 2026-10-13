<?php

namespace App\Models;

use App\Support\TypesFichiers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de la plateforme — une seule ligne en base (id=1), volontairement
 * réservée en dur au rôle admin (voir App\Filament\Pages\Parametres), pour
 * les mêmes raisons que Users/Roles : certains réglages ici (durée de
 * rétention de l'historique, par exemple) ont un impact direct sur la
 * sécurité/la traçabilité, un rôle mal configuré ne doit jamais pouvoir se
 * les accorder lui-même.
 *
 * self::actuel() interroge la base à chaque appel, volontairement SANS
 * cache : une table à une seule ligne, lue quelques fois par page, ne
 * justifie pas la complexité d'un cache. Deux pièges rencontrés en pratique
 * ont motivé ce choix : (1) mettre en cache une instance de modèle Eloquent
 * via un cache persistant (Cache::remember, driver "database"/"file") la
 * fait sérialiser, et la relire peut échouer avec "__PHP_Incomplete_Class"
 * selon le contexte d'exécution ; (2) une propriété statique PHP survit
 * d'un test à l'autre dans une même exécution PHPUnit alors que
 * RefreshDatabase annule les données entre deux tests, ce qui désynchronise
 * le cache de la base réelle. Une simple requête à chaque appel évite les
 * deux.
 *
 * self::estDisponible() protège contre le cas où ce code s'exécute avant que
 * la migration correspondante ait tourné (première installation, commande
 * `artisan migrate` elle-même) : sans cette garde, l'application ne pourrait
 * jamais démarrer pour exécuter la migration qui crée cette table.
 */
class Parametre extends Model
{
    protected $table = 'parametres';

    protected $fillable = [
        'nom_application',
        'logo',
        'couleur_principale',
        'taille_max_document_mo',
        'taille_max_avatar_mo',
        'retention_audit_jours',
        'retention_sauvegardes_nombre',
        'mysqldump_chemin',
        'frequence_sauvegarde_auto',
        'types_fichiers_autorises',
        'nombre_annees_these_doctorat',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        // Voir App\Support\Verrouillage — deux seuils indépendants, tous les
        // deux facultatifs (nullable), réglables depuis Administration >
        // Paramètres > Verrouillage.
        'verrouillage_annee_academique_id',
        'verrouillage_date',
    ];

    protected function casts(): array
    {
        return [
            'taille_max_document_mo' => 'integer',
            'taille_max_avatar_mo' => 'integer',
            'retention_audit_jours' => 'integer',
            'retention_sauvegardes_nombre' => 'integer',
            'types_fichiers_autorises' => 'array',
            'nombre_annees_these_doctorat' => 'integer',
            'mail_port' => 'integer',
            // Chiffré en base (voir le docblock de la migration
            // 2026_10_10_000001_add_configuration_mail_to_parametres_table.php)
            // — jamais stocké ni journalisé en clair. Eloquent le déchiffre
            // automatiquement à la lecture (avec APP_KEY), donc
            // $parametres->mail_password redonne bien le mot de passe en
            // clair dans le code PHP (ex. appliquerConfigurationMail()
            // ci-dessous) : seule la colonne SQL elle-même est chiffrée.
            'mail_password' => 'encrypted',
            'verrouillage_date' => 'date',
        ];
    }

    public function verrouillageAnneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'verrouillage_annee_academique_id');
    }

    /**
     * Valeurs par défaut de la ligne id=1 à sa toute première création — mêmes
     * valeurs que defautsSansBase() (hors nom_application/logo/couleur, qui ne
     * sont lus qu'affichés et jamais utilisés en calcul). Déclarées ici en dur
     * plutôt que de compter sur les DEFAULT des colonnes : firstOrCreate()
     * construit le nouveau modèle uniquement à partir des clés qu'on lui
     * donne, puis Eloquent n'inclut dans l'INSERT que les attributs
     * effectivement définis sur le modèle — les colonnes absentes de ce
     * tableau ne sont pas forcément couvertes par un DEFAULT SQL selon le
     * pilote de base ; un enregistrement créé sans taille_max_avatar_mo
     * explicite a déjà été vu à 0/NULL plutôt que 2, ce qui rend l'envoi
     * d'une photo de profil impossible (validation "max:0 Ko"). Voir aussi
     * typesFichiersAutorisesEffectifs() pour le cas d'une ligne déjà
     * existante avant l'ajout d'un réglage (ALTER TABLE, pas d'INSERT — une
     * ligne existante récupère alors simplement NULL, non 0/2).
     */
    protected const VALEURS_PAR_DEFAUT = [
        'taille_max_document_mo' => 20,
        'taille_max_avatar_mo' => 2,
        'retention_audit_jours' => 730,
        'retention_sauvegardes_nombre' => 10,
        'frequence_sauvegarde_auto' => 'quotidienne',
        // Voir App\Models\SessionDeliberation::nombreAnneesTheseDoctorat() et
        // App\Models\SessionReleve::nombreAnneesTheseDoctorat() (même
        // réglage PARTAGÉ par les deux) — durée standard d'une thèse au
        // Burkina Faso/LMD, modifiable depuis Administration > Paramètres
        // sans toucher au code.
        'nombre_annees_these_doctorat' => 3,
    ];

    /**
     * La planification (routes/console.php) reste quotidienne côté Laravel —
     * c'est ici qu'on décide si le jour convient à la fréquence choisie,
     * pour que changer "quotidienne" en "hebdomadaire"/"mensuelle"/
     * "annuelle" depuis Administration > Paramètres n'exige jamais de
     * toucher au code.
     */
    public function doitSauvegarderAujourdhui(): bool
    {
        return match ($this->frequence_sauvegarde_auto) {
            'hebdomadaire' => now()->isMonday(),
            'mensuelle' => now()->day === 1,
            // Demande explicite du client, en plus de quotidienne/
            // hebdomadaire/mensuelle : garder au moins une sauvegarde par
            // année civile, au cas où aucune des fréquences plus
            // rapprochées n'aurait été activée ("pourquoi pas une
            // sauvegarde automatique également chaque année") — 1er
            // janvier, même logique que "le 1er du mois" pour mensuelle.
            'annuelle' => now()->month === 1 && now()->day === 1,
            default => true, // 'quotidienne'
        };
    }

    public static function estDisponible(): bool
    {
        try {
            return Schema::hasTable('parametres');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function actuel(): self
    {
        if (! self::estDisponible()) {
            return self::defautsSansBase();
        }

        // Le deuxième argument ne s'applique qu'à la toute première création
        // de la ligne id=1 (nouvelle installation) : voir
        // typesFichiersAutorisesEffectifs() pour le cas d'une ligne déjà
        // existante avant l'ajout de cette colonne (ALTER TABLE, pas
        // d'INSERT — une ligne existante récupère alors simplement NULL).
        return self::query()->firstOrCreate(['id' => 1], [
            ...self::VALEURS_PAR_DEFAUT,
            'types_fichiers_autorises' => TypesFichiers::toutesLesCles(),
        ]);
    }

    /**
     * Valeur effective des familles de fichiers autorisées : toutes par
     * défaut tant que rien n'a encore été enregistré explicitement (colonne
     * NULL — soit une ligne créée avant l'ajout de ce réglage, migration
     * 2026_09_20_000013, soit jamais modifiée depuis Administration >
     * Paramètres). Utilisée par App\Filament\Pages\Parametres (pour ne pas
     * afficher un formulaire vide au premier chargement) — DocumentResource,
     * lui, passe directement par TypesFichiers::mimesPour(), qui gère déjà
     * ce même cas NULL.
     */
    public function typesFichiersAutorisesEffectifs(): array
    {
        return ! empty($this->types_fichiers_autorises)
            ? $this->types_fichiers_autorises
            : TypesFichiers::toutesLesCles();
    }

    /**
     * Surcharge la configuration mail de Laravel (normalement lue depuis
     * le fichier .env, voir config/mail.php) par celle enregistrée ici
     * (Administration > Paramètres > Configuration mail) — demande
     * explicite du client : pouvoir renouveler un mot de passe d'application
     * (Gmail...) ou changer d'hébergeur SMTP sans avoir à modifier .env à la
     * main sur le serveur.
     *
     * UNIQUEMENT si `mail_host` est renseigné : tant que cet écran n'a
     * jamais été rempli, cette méthode ne fait rien et .env continue de
     * faire foi exactement comme avant son existence — aucune régression
     * pour une installation qui n'utilise pas ce réglage.
     *
     * Appelée une fois par requête depuis
     * App\Providers\AppServiceProvider::boot(), juste après avoir chargé
     * $this (voir son docblock) — avant qu'un email puisse potentiellement
     * partir (ex. réinitialisation de mot de passe).
     */
    public function appliquerConfigurationMail(): void
    {
        if (! $this->mail_host) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $this->mail_host,
            'mail.mailers.smtp.port' => $this->mail_port ?: 587,
            'mail.mailers.smtp.username' => $this->mail_username,
            'mail.mailers.smtp.password' => $this->mail_password,
            'mail.mailers.smtp.encryption' => $this->mail_encryption ?: null,
            'mail.from.address' => $this->mail_from_address ?: config('mail.from.address'),
            'mail.from.name' => $this->mail_from_name ?: config('mail.from.name'),
        ]);
    }

    /** Conservée pour compatibilité (appelée après enregistrement) — sans effet, actuel() ne cache plus rien. */
    public static function oublierCache(): void
    {
        //
    }

    /** Utilisé uniquement avant la toute première migration (voir estDisponible()) : jamais persisté. */
    protected static function defautsSansBase(): self
    {
        return new self([
            'nom_application' => 'INGRIDD — Archives & Documents',
            'couleur_principale' => '#6366f1',
            ...self::VALEURS_PAR_DEFAUT,
            'types_fichiers_autorises' => TypesFichiers::toutesLesCles(),
        ]);
    }
}
