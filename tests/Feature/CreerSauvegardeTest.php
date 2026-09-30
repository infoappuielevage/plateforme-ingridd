<?php

namespace Tests\Feature;

use App\Models\Parametre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Console\Commands\CreerSauvegarde — porte les tests de l'ancien tests/
 * Feature/SauvegardesTest.php qui portaient sur la COMMANDE elle-même
 * (indépendants de Filament, retiré) : la commande `ingridd:sauvegarde`
 * existait déjà avant Filament et continue de fonctionner à l'identique.
 *
 * Les 2 tests de l'ancien fichier qui passaient par Livewire::test(Sauvegardes)
 * (accès à l'écran, contrôle d'accès admin) sont, eux, couverts par tests/
 * Feature/SauvegardeControllerTest.php — seuls ceux-ci, propres à la commande,
 * sont repris ici pour ne pas perdre leur couverture en supprimant l'ancien
 * fichier (indépendamment de l'écran qui l'appelle, voir
 * SauvegardeController::store()).
 */
class CreerSauvegardeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_commande_cree_une_archive_zip(): void
    {
        Storage::fake('sauvegardes');

        $this->artisan('ingridd:sauvegarde')->assertSuccessful();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        $this->assertCount(1, $archives);
    }

    public function test_la_commande_purge_les_sauvegardes_au_dela_du_nombre_a_conserver(): void
    {
        Storage::fake('sauvegardes');

        $this->artisan('ingridd:sauvegarde', ['--conserver' => 2])->assertSuccessful();
        $this->artisan('ingridd:sauvegarde', ['--conserver' => 2])->assertSuccessful();
        $this->artisan('ingridd:sauvegarde', ['--conserver' => 2])->assertSuccessful();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        $this->assertCount(2, $archives);
    }

    /**
     * Régression : au passage de SQLite à MySQL, cette commande ne savait
     * sauvegarder qu'un fichier .sqlite — sur MySQL, elle continuait
     * silencieusement sans la base, en rapportant quand même un succès. Elle
     * doit échouer clairement (FAILURE) si la base n'a pas pu être incluse,
     * plutôt que de produire une archive incomplète en laissant croire à une
     * sauvegarde réussie.
     *
     * Un chemin `mysqldump` volontairement inexistant rend l'échec
     * déterministe, sans dépendre d'un vrai serveur MySQL disponible dans
     * l'environnement de test.
     */
    public function test_la_commande_echoue_clairement_si_mysqldump_est_introuvable(): void
    {
        Storage::fake('sauvegardes');

        Parametre::actuel()->fill([
            'mysqldump_chemin' => '/chemin/qui/nexiste/pas/mysqldump',
        ])->save();

        // La connexion par défaut passe à "mysql" (comme en production) pour
        // déclencher la branche mysqldump de la commande. Pas de vrai
        // serveur MySQL dans l'environnement de test : on réutilise la
        // connexion sqlite de test SOUS le nom "mysql" pour les requêtes
        // Eloquent incidentes (Parametre::actuel(), appelée par la commande
        // elle-même) — seul mysqldump, un vrai processus externe lancé avec
        // un chemin volontairement inexistant, doit échouer ici.
        app('db')->extend('mysql', fn () => app('db')->connection('sqlite'));
        config(['database.default' => 'mysql']);

        $this->artisan('ingridd:sauvegarde')->assertFailed();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        $this->assertCount(0, $archives);
    }

    /**
     * Filet de sécurité (voir CreerSauvegarde::handle()) : une erreur
     * complètement inattendue (ici, le disque `sauvegardes` lui-même qui
     * lève une exception à l'écriture) ne doit jamais remonter telle quelle
     * — appelée depuis une requête web (SauvegardeController::store()), une
     * exception non rattrapée y deviendrait une page d'erreur invisible pour
     * l'utilisateur plutôt qu'un message clair.
     */
    public function test_une_exception_inattendue_est_rattrapee_et_journalisee(): void
    {
        Storage::shouldReceive('disk')
            ->with('sauvegardes')
            ->andThrow(new \RuntimeException('Disque de sauvegarde inaccessible.'));

        $this->artisan('ingridd:sauvegarde')->assertFailed();
    }
}
