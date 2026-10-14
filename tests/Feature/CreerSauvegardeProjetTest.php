<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * App\Console\Commands\CreerSauvegardeProjet — voir son docblock pour le
 * contexte complet (sauvegarde du CODE du projet, distincte de
 * App\Console\Commands\CreerSauvegarde qui couvre base de données +
 * documents). Même style de tests que tests/Feature/CreerSauvegardeTest.php.
 */
class CreerSauvegardeProjetTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_commande_cree_une_archive_zip_prefixee_projet(): void
    {
        Storage::fake('sauvegardes');

        $this->artisan('ingridd:sauvegarde-projet')->assertSuccessful();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_starts_with($f, 'projet-') && str_ends_with($f, '.zip'));

        $this->assertCount(1, $archives);
    }

    /** vendor/, node_modules/, .git/ et tout fichier .env* ne doivent jamais se retrouver dans l'archive (voir CreerSauvegardeProjet::DOSSIERS_EXCLUS/FICHIERS_EXCLUS). */
    public function test_larchive_exclut_vendor_et_les_fichiers_env(): void
    {
        Storage::fake('sauvegardes');

        $dossierFactice = base_path('vendor/paquet-de-test');
        if (! is_dir($dossierFactice)) {
            mkdir($dossierFactice, 0755, true);
        }
        file_put_contents($dossierFactice.'/fichier.php', '<?php // factice');

        $envFactice = base_path('.env');
        $envExistaitDeja = is_file($envFactice);
        if (! $envExistaitDeja) {
            file_put_contents($envFactice, "APP_KEY=secret\n");
        }

        try {
            $this->artisan('ingridd:sauvegarde-projet')->assertSuccessful();

            $nomArchive = collect(Storage::disk('sauvegardes')->files())
                ->first(fn (string $f) => str_starts_with($f, 'projet-') && str_ends_with($f, '.zip'));

            $this->assertNotNull($nomArchive);

            $archive = new ZipArchive;
            $archive->open(Storage::disk('sauvegardes')->path($nomArchive));

            $this->assertFalse($archive->locateName('vendor/paquet-de-test/fichier.php'));
            $this->assertFalse($archive->locateName('.env'));
            // Le code applicatif, lui, doit bien être présent.
            $this->assertNotFalse($archive->locateName('composer.json'));

            $archive->close();
        } finally {
            @unlink($dossierFactice.'/fichier.php');
            @rmdir($dossierFactice);
            if (! $envExistaitDeja) {
                @unlink($envFactice);
            }
        }
    }

    public function test_le_dossier_temporaire_prive_ne_garde_aucun_fichier_apres_une_sauvegarde_reussie(): void
    {
        Storage::fake('sauvegardes');

        $this->artisan('ingridd:sauvegarde-projet')->assertSuccessful();

        $dossier = storage_path('app/sauvegardes-tmp');
        $this->assertDirectoryExists($dossier);
        $this->assertSame([], glob($dossier.DIRECTORY_SEPARATOR.'*'));
    }

    public function test_la_commande_purge_ses_propres_archives_au_dela_du_nombre_a_conserver(): void
    {
        Storage::fake('sauvegardes');

        $this->artisan('ingridd:sauvegarde-projet', ['--conserver' => 2])->assertSuccessful();
        $this->artisan('ingridd:sauvegarde-projet', ['--conserver' => 2])->assertSuccessful();
        $this->artisan('ingridd:sauvegarde-projet', ['--conserver' => 2])->assertSuccessful();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_starts_with($f, 'projet-') && str_ends_with($f, '.zip'));

        $this->assertCount(2, $archives);
    }

    /**
     * La purge de CreerSauvegardeProjet ne doit jamais toucher aux archives
     * de CreerSauvegarde (préfixe "sauvegarde-"), même en forçant
     * "--conserver=0" côté projet — et réciproquement (voir le docblock de
     * CreerSauvegarde::purgerAnciennes()).
     */
    public function test_la_purge_du_projet_ne_touche_jamais_aux_sauvegardes_de_donnees(): void
    {
        Storage::fake('sauvegardes');

        Storage::disk('sauvegardes')->put('sauvegarde-2026-01-01-000000-abcdef.zip', 'contenu');

        $this->artisan('ingridd:sauvegarde-projet', ['--conserver' => 0])->assertSuccessful();

        Storage::disk('sauvegardes')->assertExists('sauvegarde-2026-01-01-000000-abcdef.zip');
    }
}
