<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AnnonceAdministrateurNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cloche de notifications (voir layouts/gestion.blade.php) et "Mes
 * notifications" (App\Http\Controllers\NotificationController) — demande
 * explicite du client. Chacun ne gère que SES PROPRES notifications : voir
 * test_un_utilisateur_ne_peut_pas_marquer_comme_lue_la_notification_dun_autre.
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_mes_notifications_affiche_les_notifications_de_lutilisateur_connecte(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->notify(new AnnonceAdministrateurNotification('Bienvenue', 'Ceci est un test.'));

        $this->actingAs($utilisateur)
            ->get(route('gestion.notifications.index'))
            ->assertOk()
            ->assertSee('Bienvenue')
            ->assertSee('Ceci est un test.');
    }

    public function test_un_utilisateur_ne_voit_pas_les_notifications_dun_autre(): void
    {
        $utilisateur = User::factory()->create();
        $autre = User::factory()->create();
        $autre->notify(new AnnonceAdministrateurNotification('Message privé', 'Pas pour vous.'));

        $this->actingAs($utilisateur)
            ->get(route('gestion.notifications.index'))
            ->assertOk()
            ->assertDontSee('Message privé');
    }

    public function test_marquer_une_notification_comme_lue(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->notify(new AnnonceAdministrateurNotification('Titre', 'Message'));
        $notification = $utilisateur->notifications()->firstOrFail();

        $this->assertNull($notification->read_at);

        $this->actingAs($utilisateur)
            ->post(route('gestion.notifications.marquer-comme-lue', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** Même un administrateur ne doit jamais pouvoir marquer comme lue la notification d'un autre compte depuis cet écran. */
    public function test_un_utilisateur_ne_peut_pas_marquer_comme_lue_la_notification_dun_autre(): void
    {
        $admin = User::factory()->admin()->create();
        $autre = User::factory()->create();
        $autre->notify(new AnnonceAdministrateurNotification('Titre', 'Message'));
        $notification = $autre->notifications()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('gestion.notifications.marquer-comme-lue', $notification->id))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_tout_marquer_comme_lu(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->notify(new AnnonceAdministrateurNotification('Un', 'Message'));
        $utilisateur->notify(new AnnonceAdministrateurNotification('Deux', 'Message'));

        $this->assertSame(2, $utilisateur->unreadNotifications()->count());

        $this->actingAs($utilisateur)
            ->post(route('gestion.notifications.tout-marquer-comme-lu'))
            ->assertRedirect();

        $this->assertSame(0, $utilisateur->unreadNotifications()->count());
    }

    /** Cloche de la barre de navigation — voir layouts/gestion.blade.php, visible sur n'importe quelle page de l'espace de gestion, pas seulement "Mes notifications". */
    public function test_la_cloche_affiche_le_nombre_de_notifications_non_lues_et_leur_contenu(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->notify(new AnnonceAdministrateurNotification('Annonce test', 'Un message.'));

        $reponse = $this->actingAs($utilisateur)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertSee('Annonce test')
            ->assertSee('<span class="notif-dot">1</span>', false);
    }

    /** Sans notification non lue, la pastille ne doit pas s'afficher du tout — pas de "0" confus dans le coin de l'icône. */
    public function test_la_pastille_de_la_cloche_est_absente_sans_notification_non_lue(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('notif-dot', false);
    }
}
