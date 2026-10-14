<?php

namespace App\Console\Commands;

use App\Models\Parametre;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Sauvegarde du PROJET lui-même (code source de l'application) dans une
 * archive .zip — demande explicite du client, en plus de la sauvegarde
 * habituelle (voir App\Console\Commands\CreerSauvegarde, qui ne contient que
 * la base de données et les documents, JAMAIS le code) : "j'aimerais une
 * avoir une option de sauvegarde zip qui consiste a creer une sauvegarde de
 * tout le projet". Permet par exemple de garder une copie du code tel qu'il
 * tournait à un instant donné, ou de redéployer l'application ailleurs.
 *
 * Déposée sur le MÊME disque privé `sauvegardes` que l'autre type
 * d'archive (voir SauvegardeController), mais sous un préfixe de nom
 * différent ("projet-" au lieu de "sauvegarde-") : les deux apparaissent
 * ensemble dans Administration > Sauvegardes, mais purgerAnciennes()
 * ci-dessous ne purge JAMAIS les fichiers de l'autre commande, et
 * réciproquement (voir le commentaire dans
 * CreerSauvegarde::purgerAnciennes()) — sinon une rafale de sauvegardes du
 * projet (plus volumineuses, donc plus fréquentes en pratique si le client
 * code lui-même dessus) pourrait expulser trop tôt des sauvegardes de
 * données, bien plus critiques à conserver.
 *
 * Volontairement EXCLUS de l'archive (voir DOSSIERS_EXCLUS/FICHIERS_EXCLUS) :
 * - vendor/, node_modules/, .git/ : régénérables (composer install /
 *   npm install) ou hors du périmètre d'une sauvegarde de CODE, et
 *   potentiellement très volumineux.
 * - storage/framework/{cache,sessions,views,testing}, storage/logs,
 *   bootstrap/cache : fichiers de travail/caches régénérés automatiquement
 *   par Laravel, aucun intérêt à les conserver.
 * - storage/app/documents, storage/app/sauvegardes(-tmp), database/
 *   database.sqlite : les DONNÉES de la plateforme — déjà couvertes par
 *   CreerSauvegarde, pas dupliquées ici pour garder cette archive légère et
 *   rapide à produire (c'est une sauvegarde du CODE, pas une troisième copie
 *   des documents).
 * - tout fichier ".env*" : contient les secrets de la plateforme (mots de
 *   passe base de données, identifiants SMTP, clé de chiffrement
 *   APP_KEY...) — jamais inclus dans une archive téléchargeable, même
 *   réservée aux administrateurs (même prudence que le reste de
 *   l'application, voir par exemple App\Http\Controllers\
 *   ParametresController pour la configuration mail). Un administrateur qui
 *   redéploie le projet ailleurs doit reconstituer son .env séparément.
 */
class CreerSauvegardeProjet extends Command
{
    protected $signature = 'ingridd:sauvegarde-projet
        {--conserver= : Nombre de sauvegardes du projet à conserver (par défaut : Administration > Paramètres)}';

    protected $description = "Crée une archive .zip du code source du projet (sans la base de données ni les documents) et purge les plus anciennes au-delà du nombre à conserver";

    protected const DOSSIERS_EXCLUS = [
        'vendor',
        'node_modules',
        '.git',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/testing',
        'storage/logs',
        'storage/app/documents',
        'storage/app/sauvegardes',
        'storage/app/sauvegardes-tmp',
        'bootstrap/cache',
    ];

    protected const FICHIERS_EXCLUS = [
        'database/database.sqlite',
    ];

    public function handle(): int
    {
        // Même raisonnement que CreerSauvegarde::handle() (appelée de façon
        // synchrone depuis une requête web, voir SauvegardeController) : une
        // archive du projet entier peut prendre plusieurs dizaines de
        // secondes selon sa taille, bien au-delà de la limite par défaut de
        // PHP sur une config Apache/Windows classique.
        set_time_limit(0);

        try {
            return $this->creerLarchive();
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Erreur inattendue pendant la sauvegarde du projet : '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function creerLarchive(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('L\'extension PHP "zip" n\'est pas installée sur ce serveur : impossible de créer une sauvegarde.');

            return self::FAILURE;
        }

        $nomFichier = 'projet-'.now()->format('Y-m-d-His').'-'.substr(uniqid(), -6).'.zip';
        $dossierTemporaire = $this->dossierTemporaire();
        $this->nettoyerDebrisAnciens($dossierTemporaire);

        $cheminTemporaire = tempnam($dossierTemporaire, 'ingridd-sauvegarde-projet-');

        $archive = new ZipArchive;

        if ($archive->open($cheminTemporaire, ZipArchive::OVERWRITE) !== true) {
            $this->error('Impossible de créer l\'archive temporaire.');

            return self::FAILURE;
        }

        $nombreFichiers = $this->ajouterProjet($archive, base_path());

        $archive->close();

        // Voir le commentaire détaillé dans CreerSauvegarde::creerLarchive()
        // sur ce unset() : sous Windows, ZipArchive::close() ne relâche pas
        // immédiatement son verrou sur le fichier tant que l'objet PHP reste
        // en mémoire — sans ce unset(), le unlink() ci-dessous échouerait
        // systématiquement.
        unset($archive);

        if ($nombreFichiers === 0) {
            $this->supprimerFichierTemporaire($cheminTemporaire);
            $this->error('Sauvegarde annulée : aucun fichier trouvé à inclure.');

            return self::FAILURE;
        }

        // En flux (fopen), jamais file_get_contents() — même raisonnement
        // que CreerSauvegarde::creerLarchive() : une archive du projet
        // entier peut facilement dépasser la limite mémoire par défaut de
        // PHP si elle était chargée d'un bloc.
        $flux = fopen($cheminTemporaire, 'r');
        Storage::disk('sauvegardes')->put($nomFichier, $flux);
        if (is_resource($flux)) {
            fclose($flux);
        }
        $this->supprimerFichierTemporaire($cheminTemporaire);

        $this->info("Sauvegarde du projet créée : {$nomFichier} ({$nombreFichiers} fichiers).");

        $nombreAConserver = $this->option('conserver') !== null
            ? (int) $this->option('conserver')
            : (Parametre::actuel()->retention_sauvegardes_nombre ?? 10);

        $this->purgerAnciennes($nombreAConserver);

        return self::SUCCESS;
    }

    /**
     * Ajoute récursivement tous les fichiers du projet à l'archive, en
     * sautant entièrement les dossiers de DOSSIERS_EXCLUS (jamais même
     * parcourus, via RecursiveCallbackFilterIterator — important pour
     * vendor/node_modules, potentiellement très volumineux) et tout fichier
     * dont le nom commence par ".env" ou figure dans FICHIERS_EXCLUS.
     *
     * @return int Nombre de fichiers effectivement ajoutés.
     */
    protected function ajouterProjet(ZipArchive $archive, string $racine): int
    {
        $dossiersExclus = array_map(
            fn (string $relatif) => $racine.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relatif),
            self::DOSSIERS_EXCLUS
        );
        $fichiersExclus = array_map(
            fn (string $relatif) => $racine.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relatif),
            self::FICHIERS_EXCLUS
        );

        $brut = new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS);

        $filtre = new RecursiveCallbackFilterIterator($brut, function ($fichier) use ($dossiersExclus, $fichiersExclus) {
            if ($fichier->isDir()) {
                return ! in_array($fichier->getPathname(), $dossiersExclus, true);
            }

            if (str_starts_with($fichier->getFilename(), '.env')) {
                return false;
            }

            return ! in_array($fichier->getPathname(), $fichiersExclus, true);
        });

        $iterateur = new RecursiveIteratorIterator($filtre);

        $nombre = 0;

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile()) {
                continue;
            }

            $cheminRelatif = substr($fichier->getPathname(), strlen($racine) + 1);
            $archive->addFile($fichier->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $cheminRelatif));
            $nombre++;
        }

        return $nombre;
    }

    /** Identique à CreerSauvegarde::dossierTemporaire() — voir son docblock (même choix, délibérément dupliqué, voir le docblock de cette classe). */
    protected function dossierTemporaire(): string
    {
        $dossier = storage_path('app/sauvegardes-tmp');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        return $dossier;
    }

    /** Identique à CreerSauvegarde::supprimerFichierTemporaire() — voir son docblock. */
    protected function supprimerFichierTemporaire(string $chemin): void
    {
        for ($tentative = 0; $tentative < 5; $tentative++) {
            if (! is_file($chemin) || @unlink($chemin)) {
                return;
            }

            usleep(100_000); // 100 ms
        }

        report(new \RuntimeException("Impossible de supprimer le fichier temporaire de sauvegarde du projet : {$chemin}"));
    }

    /** Identique à CreerSauvegarde::nettoyerDebrisAnciens() — voir son docblock. */
    protected function nettoyerDebrisAnciens(string $dossier): void
    {
        foreach (glob($dossier.DIRECTORY_SEPARATOR.'*') ?: [] as $chemin) {
            if (is_file($chemin)) {
                $this->supprimerFichierTemporaire($chemin);
            }
        }
    }

    /**
     * Comme CreerSauvegarde::purgerAnciennes(), mais limitée aux fichiers
     * "projet-*.zip" (voir le docblock de la classe) : ne touche jamais aux
     * sauvegardes base de données + documents de l'autre commande, même
     * partagées sur le même disque.
     */
    protected function purgerAnciennes(int $nombreAConserver): void
    {
        if ($nombreAConserver <= 0) {
            return;
        }

        $fichiers = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $chemin) => str_starts_with($chemin, 'projet-') && str_ends_with($chemin, '.zip'))
            ->sortByDesc(fn (string $chemin) => Storage::disk('sauvegardes')->lastModified($chemin))
            ->values();

        $fichiers->slice($nombreAConserver)->each(
            fn (string $chemin) => Storage::disk('sauvegardes')->delete($chemin)
        );
    }
}
