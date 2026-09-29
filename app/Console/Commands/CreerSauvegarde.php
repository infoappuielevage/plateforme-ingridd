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
        if (! class_exists(ZipArchive::class)) {
            $this->error('L\'extension PHP "zip" n\'est pas installée sur ce serveur : impossible de créer une sauvegarde.');

            return self::FAILURE;
        }

        // Le suffixe (6 caractères de uniqid()) évite une collision de nom
        // si deux sauvegardes sont lancées dans la même seconde (double-clic,
        // nouvelle tentative après une manuelle juste après la planifiée...).
        $nomFichier = 'sauvegarde-'.now()->format('Y-m-d-His').'-'.substr(uniqid(), -6).'.zip';
        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'ingridd-sauvegarde-');

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

        if ($this->cheminDumpTemporaire) {
            @unlink($this->cheminDumpTemporaire);
        }

        if (! $baseIncluse) {
            @unlink($cheminTemporaire);
            $this->error('Sauvegarde annulée : la base de données n\'a pas pu être incluse (voir le message ci-dessus). Les documents seuls ne suffisent pas à restaurer la plateforme.');

            return self::FAILURE;
        }

        Storage::disk('sauvegardes')->put($nomFichier, file_get_contents($cheminTemporaire));
        unlink($cheminTemporaire);

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
     * processus du serveur.
     *
     * @return bool true si l'export a réussi et a été ajouté à l'archive.
     */
    protected function sauvegarderMysql(ZipArchive $archive): bool
    {
        $config = config('database.connections.mysql');
        $binaire = Parametre::actuel()->mysqldump_chemin ?: 'mysqldump';

        $cheminDump = tempnam(sys_get_temp_dir(), 'ingridd-mysqldump-');

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

        try {
            $processus = new Process($commande, null, ['MYSQL_PWD' => $config['password']]);
            $processus->setTimeout(300);
            $processus->run();

            if (! $processus->isSuccessful()) {
                $this->error("Échec de mysqldump : ".trim($processus->getErrorOutput() ?: $processus->getOutput()));
                $this->avertirCheminMysqldump();
                @unlink($cheminDump);

                return false;
            }
        } catch (ProcessException $exception) {
            $this->error("Impossible de lancer \"{$binaire}\" : ".$exception->getMessage());
            $this->avertirCheminMysqldump();
            @unlink($cheminDump);

            return false;
        }

        if (! is_file($cheminDump) || filesize($cheminDump) === 0) {
            $this->error('mysqldump n\'a produit aucun contenu.');
            @unlink($cheminDump);

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

    protected function purgerAnciennes(int $nombreAConserver): void
    {
        if ($nombreAConserver <= 0) {
            return;
        }

        $fichiers = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $chemin) => str_ends_with($chemin, '.zip'))
            ->sortByDesc(fn (string $chemin) => Storage::disk('sauvegardes')->lastModified($chemin))
            ->values();

        $fichiers->slice($nombreAConserver)->each(
            fn (string $chemin) => Storage::disk('sauvegardes')->delete($chemin)
        );
    }
}
