<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AlerteSauvegardeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Console\Commands\VerifierAlertes — planifiée quotidiennement (voir
 * routes/console.php), notifie chaque administrateur si la dernière
 * sauvegarde est absente/en retard, mêmes conditions que la carte "Dernière
 * sauvegarde" du tableau de bord (voir DashboardController::
 * derniereSauvegarde()). L'alerte "espace disque faible"
 * (App\Notifications\AlerteEspaceDisqueNotification) n'est volontairement
 * PAS testée ici : elle dépend de l'espace disque réel de la machine qui
 * exécute les tests (même choix que DashboardControllerTest::
 * test_la_carte_espace_de_stockage_affiche_un_indicateur_disque()).
 */
class VerifierAlertesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifie_les_administrateurs_si_aucune_sauvegarde_nexiste(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $nonAdmin = User::factory()->create();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->where('type', AlerteSauvegardeNotification::class)->count());
        // Jamais les non-administrateurs : cette alerte ne les concerne pas.
        $this->assertSame(0, $nonAdmin->notifications()->count());
    }

    public function test_notifie_les_administrateurs_si_la_derniere_sauvegarde_remonte_a_plus_dune_semaine(): void
    {
        Storage::fake('sauvegardes');
        Storage::disk('sauvegardes')->put('sauvegarde-vieille.zip', 'contenu');
        touch(Storage::disk('sauvegardes')->path('sauvegarde-vieille.zip'), now()->subWeeks(2)->timestamp);

        $admin = User::factory()->admin()->create();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->where('type', AlerteSauvegardeNotification::class)->count());
    }

    public function test_ne_notifie_pas_si_une_sauvegarde_recente_existe(): void
    {
        Storage::fake('sauvegardes');
        Storage::disk('sauvegardes')->put('sauvegarde-recente.zip', 'contenu');

        $admin = User::factory()->admin()->create();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();

        $this->assertSame(0, $admin->notifications()->where('type', AlerteSauvegardeNotification::class)->count());
    }

    /**
     * Garde-fou essentiel (voir le docblock de VerifierAlertes::
     * notifierAdmins()) : sans lui, cette commande planifiée QUOTIDIENNEMENT
     * empilerait une notification identique chaque jour tant que personne
     * n'a créé de nouvelle sauvegarde.
     */
    public function test_ne_notifie_pas_deux_fois_tant_que_la_precedente_nest_pas_lue(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();
        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->where('type', AlerteSauvegardeNotification::class)->count());
    }

    /** Une fois l'alerte précédente lue, une nouvelle exécution peut en renvoyer une — le problème est peut-être toujours là. */
    public function test_renotifie_apres_que_lalerte_precedente_a_ete_lue(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();
        $admin->unreadNotifications->markAsRead();

        $this->artisan('ingridd:verifier-alertes')->assertSuccessful();

        $this->assertSame(2, $admin->notifications()->where('type', AlerteSauvegardeNotification::class)->count());
    }
}
