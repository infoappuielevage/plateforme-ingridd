<?php

namespace Tests\Feature;

use App\Filament\Pages\Sauvegardes;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Storage::fake('sauvegardes') redirige tout ce qui passe par le disque
 * (écriture, listing, suppression) vers un disque de test temporaire — la
 * commande y écrit bien via Storage::disk('sauvegardes')->put(...), donc
 * aucun fichier réel n'est créé pendant les tests. Seule la lecture des
 * fichiers À SAUVEGARDER (base de données, disque `documents`) se fait par
 * accès disque direct (voir App\Console\Commands\CreerSauvegarde) : en
 * environnement de test (base ":memory:", disque `documents` généralement
 * vide), la commande le détecte et continue sans erreur — ":memory:" est
 * traité comme un cas normal (pas d'erreur), voir
 * CreerSauvegarde::sauvegarderSqlite().
 */
class SauvegardesTest extends TestCase
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

    public function test_un_admin_peut_creer_une_sauvegarde_depuis_lecran(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Sauvegardes::class)
            ->call('creer')
            ->assertSuccessful();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        $this->assertCount(1, $archives);
    }

    public function test_un_non_admin_ne_peut_pas_acceder_aux_sauvegardes(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->assertFalse(Sauvegardes::canAccess());

        Livewire::actingAs($utilisateur);
        $this->assertFalse(Sauvegardes::canAccess());
    }

    /**
     * Régression du bug corrigé dans cette même série de changements : au
     * passage de SQLite à MySQL (voir README.md, "Choix techniques"), cette
     * commande ne savait sauvegarder qu'un fichier .sqlite — sur MySQL, elle
     * continuait silencieusement sans la base, en rapportant quand même un
     * succès. Elle doit maintenant échouer clairement (FAILURE) si la base
     * n'a pas pu être incluse, plutôt que de produire une archive
     * incomplète en le laissant croire à une sauvegarde réussie.
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

        // La connexion par défaut passe à "mysql" (comme en production)
        // pour déclencher la branche mysqldump de la commande. Pas de vrai
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
}
