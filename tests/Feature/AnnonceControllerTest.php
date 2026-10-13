<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administration > Annonces (App\Http\Controllers\AnnonceController) —
 * demande explicite du client : pouvoir rédiger et envoyer lui-même un
 * message dans la cloche de notifications d'un utilisateur, d'un rôle, ou
 * de tout le monde. Réservé en dur au rôle admin.
 */
class AnnonceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_a_lecran_annonces(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->get(route('gestion.annonces.create'))->assertForbidden();
    }

    public function test_un_non_admin_ne_peut_pas_envoyer_une_annonce(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'tous',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertForbidden();
    }

    public function test_une_annonce_a_tout_le_monde_notifie_chaque_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();
        $autres = User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'tous',
                'titre' => 'Maintenance prévue',
                'message' => 'La plateforme sera indisponible dimanche de 2h à 4h.',
            ])
            ->assertRedirect(route('gestion.annonces.create'));

        // Admin inclus (4 comptes au total) : "tout le monde" ne doit exclure personne.
        foreach ($autres->push($admin) as $destinataire) {
            $this->assertSame(1, $destinataire->notifications()->count());
            $this->assertSame('Maintenance prévue', $destinataire->notifications()->first()->data['titre']);
        }
    }

    public function test_une_annonce_a_un_role_ne_notifie_que_ce_role(): void
    {
        $admin = User::factory()->admin()->create();

        Role::query()->create([
            'slug' => 'secretariat_annonce',
            'name' => 'Secrétariat',
            'permissions' => [],
        ]);
        $membreSecretariat = User::factory()->create(['role' => 'secretariat_annonce']);
        $membreAutreRole = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'role',
                'role' => 'secretariat_annonce',
                'titre' => 'Pour le secrétariat',
                'message' => 'Message ciblé.',
            ])
            ->assertRedirect(route('gestion.annonces.create'));

        $this->assertSame(1, $membreSecretariat->notifications()->count());
        $this->assertSame(0, $membreAutreRole->notifications()->count());
    }

    public function test_une_annonce_a_un_utilisateur_precis_ne_notifie_que_lui(): void
    {
        $admin = User::factory()->admin()->create();
        $destinataire = User::factory()->create();
        $autre = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'utilisateur',
                'utilisateur_id' => $destinataire->id,
                'titre' => 'Message personnel',
                'message' => 'Juste pour vous.',
            ])
            ->assertRedirect(route('gestion.annonces.create'));

        $this->assertSame(1, $destinataire->notifications()->count());
        $this->assertSame(0, $autre->notifications()->count());
    }

    public function test_le_role_est_obligatoire_quand_le_destinataire_est_un_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'role',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_lutilisateur_est_obligatoire_quand_le_destinataire_est_un_utilisateur_precis(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.annonces.store'), [
                'destinataire_type' => 'utilisateur',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertSessionHasErrors('utilisateur_id');
    }
}
