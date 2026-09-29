<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La page d'accueil ("/") n'a aucun contenu propre — sur demande du client,
 * elle renvoie directement vers la connexion (ou, pour un utilisateur déjà
 * connecté, droit vers son tableau de bord plutôt que de lui remontrer
 * l'écran de connexion) — voir routes/web.php.
 */
class PageAccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_un_utilisateur_connecte_est_renvoye_vers_le_tableau_de_bord(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get('/')
            ->assertRedirect(route('gestion.dashboard'));
    }
}
