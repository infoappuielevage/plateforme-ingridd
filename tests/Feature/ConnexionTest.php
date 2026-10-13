<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\Auth\AuthController::connexion() — en particulier le
 * rejet d'un compte bloqué par un administrateur (voir UserController::
 * basculerBlocage() et le docblock de App\Http\Middleware\
 * RejeterUtilisateurBloque, qui couvre le cas complémentaire d'un compte
 * bloqué alors qu'il est déjà connecté).
 */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_actif_peut_se_connecter(): void
    {
        $utilisateur = User::factory()->create(['email' => 'actif@ingridd.test']);

        $this->post(route('gestion.connexion.soumettre'), [
            'email' => 'actif@ingridd.test',
            'password' => 'password',
        ])->assertRedirect(route('gestion.dashboard'));

        $this->assertAuthenticatedAs($utilisateur);
    }

    /**
     * Identifiants corrects mais compte bloqué : la connexion doit échouer
     * avec un message explicite, sans compter comme un échec de mot de
     * passe (voir le commentaire dans AuthController::connexion()).
     */
    public function test_un_compte_bloque_ne_peut_pas_se_connecter(): void
    {
        $utilisateur = User::factory()->create(['email' => 'bloque@ingridd.test']);
        $utilisateur->forceFill(['bloque_at' => now()])->save();

        $this->post(route('gestion.connexion.soumettre'), [
            'email' => 'bloque@ingridd.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Un compte bloqué PENDANT qu'il est déjà connecté (ex. un onglet resté
     * ouvert) doit être déconnecté dès sa requête suivante, sans attendre
     * une nouvelle tentative de connexion — voir
     * App\Http\Middleware\RejeterUtilisateurBloque.
     */
    public function test_un_utilisateur_bloque_en_cours_de_session_est_deconnecte_a_la_requete_suivante(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk();

        $utilisateur->forceFill(['bloque_at' => now()])->save();

        $this->get(route('gestion.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
