<?php

namespace App\Console\Commands;

use App\Models\Parametre;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Sauvegarde complète (base de données + tous les documents stockés) dans
 * une seule archive .zip, déposée sur le disque privé `sauvegardes` (voir
 * config/filesystems.php) — téléchargeable ou supprimable depuis
 * Administration > Sauvegardes (App\Filament\Pages\Sauvegardes), qui appelle
 * aussi cette commande pour "Créer une sauvegarde maintenant".
 *
 * Volontairement sans dépendance externe (pas de spatie/laravel-backup) :
 * ZipArchive fait partie de l'extension PHP "zip", presque toujours déjà
 * installée. `mysqldump` (voir sauvegarderMysql()) est fourni avec tout
 * serveur MySQL (Symfony\Component\Process, lui, est déjà une dépendance
 * de Laravel lui-même — rien à installer en plus).
 *
 * Gère SQLite et MySQL (voir config/database.php) — au départ ce projet
 * n'utilisait que SQLite, et le passage à MySQL avait laissé cette commande
 * incapable de sauvegarder la base elle-même (elle ne savait copier qu'un
 * fichier .sqlite) : les archives produites entre-temps ne contenaient que
 * les documents, pas les données. D'où le choix ci-dessous d'échouer
 * clairement (FAILURE) si la base n'a pas pu être incluse, plutôt que de
 * continuer silencieusement avec une archive incomplète — mieux vaut un
 * échec visible qu'une fausse confiance dans une sauvegarde inutilisable.
 *
 * Planifiée quotidiennement (voir routes/console.php, qui consulte
 * Parametre::doitSauvegarderAujourdhui() pour respecter la fréquence choisie
 * dans Administration > Paramètres) ; peut aussi être lancée manuellement :
 * `php artisan ingridd:sauvegarde`.
 */
class CreerSauvegarde extends Command
{
    protected $signature = 'ingridd:sauvegarde
        {--conserver= : Nombre de sauvegardes à conserver (par défaut : Administration > Paramètres)}';

    protected $description = 'Crée une sauvegarde complète (base de données + documents) et purge les plus anciennes au-delà du nombre à conserver';

    /** Fichier .sql temporaire produit par mysqldump (voir sauvegarderMysql()) — supprimé une fois l'archive fermée, jamais avant (ZipArchive lit son contenu à la fermeture, pas à addFile()). */
    protected ?string $cheminDumpTemporaire = null;

    public function handle(): int
    {
        // Appelée de façon SYNCHRONE depuis une requête web (voir
        // SauvegardeController::store()) : sans ça, la limite par défaut de
        // PHP (souvent 30 ou 60 secondes sur une config Apache/Laragon
        // Windows classique) peut interrompre BRUTALEMENT le script en plein
        // export mysqldump ou compression des documents — une erreur fatale
        // de ce type n'est pas une Exception, Laravel ne peut ni la
        // rattraper ni l'afficher proprement, et la requête s'arrête sans
        // réponse cohérente (c'est ce qui pouvait ressembler à une
        // déconnexion : le script meurt avant d'avoir pu renvoyer la page).
        set_time_limit(0);

        try {
            return $this->creerLarchive();
        } catch (\Throwable $exception) {
            // Filet de sécurité : cette commande est appelée depuis une
            // requête web (voir SauvegardeController::store()) — une
            // exception qui s'en échapperait NON rattrapée deviendrait une
            // erreur HTTP 500 que ce contrôleur ne gère pas (il ne sait
            // afficher que "$code !== 0", pas une exception), invisible
            // pour l'utilisateur : exactement le genre de plantage qui
            // pouvait ressembler à une déconnexion (voir aussi
            // set_time_limit() et le flux ci-dessous, pour la même raison).
            // On journalise le détail complet (storage/logs/laravel.log)
            // tout en affichant un message clair à l'écran.
            report($exception);
            $this->error('Erreur inattendue pendant la sauvegarde : '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function creerLarchive(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('L\'extension PHP "zip" n\'est pas installée sur ce serveur : impossible de créer une sauvegarde.');

            return self::FAILURE;
        }

        // Le suffixe (6 caractères de uniqid()) évite une collision de nom
        // si deux sauvegardes sont lancées dans la même seconde (double-clic,
        // nouvelle tentative après une manuelle juste après la planifiée...).
        $nomFichier = 'sauvegarde-'.now()->format('Y-m-d-His').'-'.substr(uniqid(), -6).'.zip';
        // dossierTemporaire() plutôt que sys_get_temp_dir() (revue de
        // sécurité demandée par le client) : le dossier temp système est
        // PARTAGÉ par tous les comptes/applications de la machine (sur un
        // serveur mutualisé en particulier), alors que storage/app/ reste
        // déjà hors d'accès web (voir le docblock de la classe et
        // config/filesystems.php pour les disques "documents"/"sauvegardes")
        // — une archive complète base+documents n'y transite donc jamais,
        // même brièvement, hors du périmètre déjà protégé de l'application.
        $dossierTemporaire = $this->dossierTemporaire();
        // Reliquats d'une exécution précédente interrompue (plantage, coupure
        // de courant, redémarrage du serveur en plein milieu d'une sauvegarde
        // — voir aussi le bug ZipArchive corrigé juste en dessous, qui en
        // laissait justement derrière lui avant ce correctif). Sans risque de
        // supprimer le fichier d'une AUTRE exécution en cours : cette
        // commande n'est jamais censée tourner deux fois en même temps (un
        // seul admin, déclenchée depuis Administration > Sauvegardes ou la
        // tâche planifiée quotidienne) — tout ce qui traîne ici au démarrage
        // d'une nouvelle sauvegarde est forcément un reliquat du passé.
        $this->nettoyerDebrisAnciens($dossierTemporaire);

        $cheminTemporaire = tempnam($dossierTemporaire, 'ingridd-sauvegarde-');

        $archive = new ZipArchive;

        if ($archive->open($cheminTemporaire, ZipArchive::OVERWRITE) !== true) {
            $this->error('Impossible de créer l\'archive temporaire.');

            return self::FAILURE;
        }

        $baseIncluse = match (config('database.default')) {
            'sqlite' => $this->sauvegarderSqlite($archive),
            'mysql' => $this->sauvegarderMysql($archive),
            default => $this->echecBaseNonPriseEnCharge(),
        };

        $this->ajouterDossier($archive, storage_path('app/documents'), 'documents');

        $archive->close();

        // CAUSE RÉELLE du fichier .zip temporaire qui restait dans storage/
        // app/sauvegardes-tmp après une sauvegarde pourtant réussie (signalé
        // par le client) : sous Windows, ZipArchive::close() ne relâche PAS
        // immédiatement le verrou système sur le fichier — tant que l'objet
        // PHP $archive reste en mémoire (ici, jusqu'à la fin de cette
        // méthode), Windows refuse de le supprimer ("fichier utilisé par un
        // autre processus"). D'où l'échec, déterministe et reproductible à
        // chaque sauvegarde (pas un simple antivirus occasionnel) : le
        // unlink() plus bas arrivait alors que $archive détenait encore le
        // fichier. `unset()` ici force PHP à détruire l'objet — et donc
        // Windows à relâcher son verrou — AVANT qu'on tente de lire puis
        // supprimer ce même fichier juste après.
        unset($archive);

        if ($this->cheminDumpTemporaire) {
            $this->supprimerFichierTemporaire($this->cheminDumpTemporaire);
        }

        if (! $baseIncluse) {
            $this->supprimerFichierTemporaire($cheminTemporaire);
            $this->error('Sauvegarde annulée : la base de données n\'a pas pu être incluse (voir le message ci-dessus). Les documents seuls ne suffisent pas à restaurer la plateforme.');

            return self::FAILURE;
        }

        // En flux (fopen), jamais file_get_contents() : une archive de
        // plusieurs dizaines/centaines de Mo (courant dès que la
        // bibliothèque de documents grossit) chargée entièrement en mémoire
        // dépasse vite la limite PHP par défaut (souvent 128 Mo) et provoque
        // une erreur fatale "Allowed memory size exhausted" — NON
        // rattrapable par un try/catch, elle aussi, et pour la même raison
        // que set_time_limit() ci-dessus : le script meurt sans que Laravel
        // ait la moindre chance d'afficher un message clair.
        $flux = fopen($cheminTemporaire, 'r');
        Storage::disk('sauvegardes')->put($nomFichier, $flux);
        if (is_resource($flux)) {
            fclose($flux);
        }
        $this->supprimerFichierTemporaire($cheminTemporaire);

        $this->info("Sauvegarde créée : {$nomFichier}");

        $nombreAConserver = $this->option('conserver') !== null
            ? (int) $this->option('conserver')
            : (Parametre::actuel()->retention_sauvegardes_nombre ?? 10);

        $this->purgerAnciennes($nombreAConserver);

        return self::SUCCESS;
    }

    /** @return bool true si le fichier SQLite a bien été ajouté à l'archive (ou s'il n'y avait, à raison, rien à ajouter — voir ci-dessous). */
    protected function sauvegarderSqlite(ZipArchive $archive): bool
    {
        // Lu depuis la config plutôt que codé en dur : respecte un DB_DATABASE
        // personnalisé dans .env (la valeur par défaut reste database.sqlite).
        $cheminBase = config('database.connections.sqlite.database', database_path('database.sqlite'));

        // ":memory:" n'existe que dans les tests automatisés (voir
        // phpunit.xml, DB_DATABASE=:memory:) : il n'y a rien à en tirer, mais
        // ce n'est pas une erreur de configuration comme le serait un vrai
        // chemin introuvable — ne pas faire échouer la commande pour ça.
        if ($cheminBase === ':memory:') {
            return true;
        }

        if (! is_file($cheminBase)) {
            $this->error("Base de données SQLite introuvable à l'emplacement attendu ({$cheminBase}).");

            return false;
        }

        $archive->addFile($cheminBase, 'base-de-donnees/database.sqlite');

        return true;
    }

    /**
     * Exporte la base MySQL via `mysqldump` (un simple fichier .sql,
     * restaurable avec `mysql nom_base < dump.sql`). Le mot de passe est
     * transmis via la variable d'environnement MYSQL_PWD plutôt qu'en
     * argument de commande, pour ne pas apparaître dans la liste des
     * processus du serveur — SAUF s'il est vide (voir ci-dessous).
     *
     * @return bool true si l'export a réussi et a été ajouté à l'archive.
     */
    protected function sauvegarderMysql(ZipArchive $archive): bool
    {
        $config = config('database.connections.mysql');
        $binaire = Parametre::actuel()->mysqldump_chemin ?: 'mysqldump';

        // Même raisonnement que dans creerLarchive() : hors du dossier temp
        // système partagé, le dump SQL (données complètes de la plateforme
        // en clair) ne transite que dans storage/app/, déjà privé.
        $cheminDump = tempnam($this->dossierTemporaire(), 'ingridd-mysqldump-');

        $commande = [
            $binaire,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--skip-lock-tables',
            '--result-file='.$cheminDump,
            $config['database'],
        ];

        // Cas très courant en local (root sans mot de passe, ex. Laragon
        // par défaut) : sous Windows, une variable d'environnement à VALEUR
        // VIDE est purement et simplement supprimée par l'API de création de
        // processus — mysqldump démarre alors sans MYSQL_PWD du tout et,
        // ne trouvant pas non plus de "--password" sur la ligne de commande,
        // se met à ATTENDRE un mot de passe tapé au clavier sur un
        // terminal... qui n'existe pas ici (le process est lancé sans TTY
        // depuis une requête web). Résultat : plus aucune sortie, plus
        // aucune erreur, le process reste bloqué jusqu'au timeout de 300s
        // ci-dessous — exactement le blocage/"déconnexion" observé.
        // "--password=" (signe égal, rien après) est la syntaxe standard
        // pour dire explicitement "mot de passe vide" SANS invite,
        // contrairement à "--password" seul qui, lui, invite toujours.
        if ($config['password'] === '' || $config['password'] === null) {
            $commande[] = '--password=';
            $environnement = null;
        } else {
            $environnement = ['MYSQL_PWD' => $config['password']];
        }

        try {
            $processus = new Process($commande, null, $environnement);
            $processus->setTimeout(300);
            $processus->run();

            if (! $processus->isSuccessful()) {
                $this->error("Échec de mysqldump : ".trim($processus->getErrorOutput() ?: $processus->getOutput()));
                $this->avertirCheminMysqldump();
                $this->supprimerFichierTemporaire($cheminDump);

                return false;
            }
        } catch (ProcessException $exception) {
            $this->error("Impossible de lancer \"{$binaire}\" : ".$exception->getMessage());
            $this->avertirCheminMysqldump();
            $this->supprimerFichierTemporaire($cheminDump);

            return false;
        }

        if (! is_file($cheminDump) || filesize($cheminDump) === 0) {
            $this->error('mysqldump n\'a produit aucun contenu.');
            $this->supprimerFichierTemporaire($cheminDump);

            return false;
        }

        $archive->addFile($cheminDump, 'base-de-donnees/dump.sql');
        // Supprimé par handle() après $archive->close() — pas ici, ZipArchive
        // ne lit le contenu du fichier qu'à la fermeture de l'archive.
        $this->cheminDumpTemporaire = $cheminDump;

        return true;
    }

    protected function avertirCheminMysqldump(): void
    {
        $this->warn('Si "mysqldump" n\'est pas dans le PATH système, renseignez son chemin complet (ex. C:\\laragon\\bin\\mysql\\mysql-8.x\\bin\\mysqldump.exe) dans Administration > Paramètres > Sauvegardes.');
    }

    protected function echecBaseNonPriseEnCharge(): bool
    {
        $this->error('Connexion "'.config('database.default').'" non prise en charge pour la sauvegarde automatique de la base (seuls sqlite et mysql le sont).');

        return false;
    }

    /**
     * Dossier temporaire privé pour les fichiers de travail de cette
     * commande (archive .zip en cours de constitution, dump mysqldump) —
     * voir les deux appels à tempnam() ci-dessus. Créé à la demande (pas de
     * migration/seeder pour un simple dossier) : `storage/app/` est déjà
     * hors d'accès web dans Laravel, contrairement au dossier temp système
     * (sys_get_temp_dir()), partagé par toutes les applications de la
     * machine.
     */
    protected function dossierTemporaire(): string
    {
        $dossier = storage_path('app/sauvegardes-tmp');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        return $dossier;
    }

    /**
     * Supprime un fichier de travail (archive .zip temporaire, dump
     * mysqldump) avec plusieurs tentatives.
     *
     * La cause PRINCIPALE du fichier qui restait coincé dans storage/app/
     * sauvegardes-tmp est corrigée à la source (voir le `unset($archive)`
     * dans creerLarchive()) : cette méthode reste malgré tout prudente, par
     * simple défense en profondeur — un antivirus qui viendrait de scanner
     * le fichier qu'on referme peut, lui aussi, en détenir un verrou bref
     * sous Windows (ERROR_SHARING_VIOLATION), un faux positif qui disparaît
     * en général en quelques dizaines de millisecondes.
     *
     * Ne fait jamais échouer la commande elle-même si la suppression finit
     * par ne pas aboutir : à ce stade l'archive est déjà bien enregistrée
     * sur le disque `sauvegardes`, un fichier de travail qui traîne reste
     * un oubli de ménage, pas une sauvegarde manquée — mais c'est quand
     * même journalisé pour ne pas laisser le dossier grossir en silence
     * (voir aussi nettoyerDebrisAnciens(), qui rattrape malgré tout un tel
     * reliquat à la sauvegarde suivante).
     */
    protected function supprimerFichierTemporaire(string $chemin): void
    {
        for ($tentative = 0; $tentative < 5; $tentative++) {
            if (! is_file($chemin) || @unlink($chemin)) {
                return;
            }

            usleep(100_000); // 100 ms
        }

        report(new \RuntimeException("Impossible de supprimer le fichier temporaire de sauvegarde : {$chemin}"));
    }

    /**
     * Supprime, AVANT de démarrer une nouvelle sauvegarde, tout fichier de
     * travail qu'une exécution précédente aurait laissé derrière elle
     * (plantage, coupure de courant, redémarrage du serveur en plein
     * milieu...) — par exemple un reliquat du bug ZipArchive corrigé
     * ci-dessus, pour les sauvegardes déjà produites avant ce correctif.
     *
     * Volontairement inconditionnel (pas de filtre par âge ni par préfixe
     * de nom) : cette commande n'est jamais censée tourner deux fois en
     * même temps (un seul admin, déclenchée depuis Administration >
     * Sauvegardes ou la tâche planifiée quotidienne) — tout fichier présent
     * ici au démarrage d'une nouvelle sauvegarde est forcément un reliquat
     * d'une exécution passée, jamais un fichier en cours d'utilisation par
     * une autre exécution.
     */
    protected function nettoyerDebrisAnciens(string $dossier): void
    {
        foreach (glob($dossier.DIRECTORY_SEPARATOR.'*') ?: [] as $chemin) {
            if (is_file($chemin)) {
                $this->supprimerFichierTemporaire($chemin);
            }
        }
    }

    /** Ajoute récursivement tous les fichiers d'un dossier à l'archive, sous le préfixe donné. */
    protected function ajouterDossier(ZipArchive $archive, string $dossier, string $prefixe): void
    {
        if (! is_dir($dossier)) {
            return;
        }

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile()) {
                $cheminRelatif = $prefixe.'/'.substr($fichier->getPathname(), strlen($dossier) + 1);
                $archive->addFile($fichier->getPathname(), $cheminRelatif);
            }
        }
    }

    /**
     * `! str_starts_with($chemin, 'projet-')` : depuis l'ajout de la
     * sauvegarde du PROJET (voir App\Console\Commands\CreerSauvegardeProjet,
     * déposée sur ce même disque sous ce préfixe), cette purge ne doit
     * jamais compter ni supprimer les archives de l'autre commande — sinon
     * une rafale de sauvegardes du projet (potentiellement plus fréquentes)
     * pourrait expulser trop tôt des sauvegardes de données, bien plus
     * critiques à conserver. Les noms antérieurs à cet ajout (qui ne suivent
     * aucune convention de préfixe particulière) restent, eux, comptés comme
     * avant : seul le nouveau préfixe "projet-" est explicitement exclu.
     */
    protected function purgerAnciennes(int $nombreAConserver): void
    {
        if ($nombreAConserver <= 0) {
            return;
        }

        $fichiers = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $chemin) => str_ends_with($chemin, '.zip'))
            ->filter(fn (string $chemin) => ! str_starts_with($chemin, 'projet-'))
            ->sortByDesc(fn (string $chemin) => Storage::disk('sauvegardes')->lastModified($chemin))
            ->values();

        $fichiers->slice($nombreAConserver)->each(
            fn (string $chemin) => Storage::disk('sauvegardes')->delete($chemin)
        );
    }
}
