<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que le composant <x-champ-mot-de-passe> (resources/views/
 * components/champ-mot-de-passe.blade.php) — et donc le bouton
 * "afficher/masquer" qu'il introduit — apparaît bien sur chacun des écrans
 * où un champ mot de passe a été migré vers ce composant, à la demande du
 * client ("pour les mots de passe permettre choix si la personne veut voir
 * le mot de passe saisi ou pas").
 *
 * Ces tests ne vérifient pas le comportement JavaScript du bouton (le
 * basculement password/text se fait côté navigateur, voir le script
 * partagé dans layouts/gestion.blade.php et layouts/invite.blade.php) :
 * ils garantissent seulement que le composant est bien rendu sur chaque
 * page, pour détecter une régression si l'un de ces écrans était un jour
 * modifié pour revenir à un <input type="password"> brut.
 */
class ChampMotDePasseComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_bouton_afficher_masquer_est_present_sur_la_page_de_connexion(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('toggle-mot-de-passe', false);
    }

    public function test_le_bouton_afficher_masquer_est_present_sur_la_page_de_reinitialisation_du_mot_de_passe(): void
    {
        $this->get(route('mot-de-passe.reinitialiser', ['token' => 'jeton-de-test']))
            ->assertOk()
            ->assertSee('toggle-mot-de-passe', false);
    }

    public function test_le_bouton_afficher_masquer_est_present_sur_le_formulaire_de_creation_dun_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.utilisateurs.create'))
            ->assertOk()
            ->assertSee('toggle-mot-de-passe', false);
    }

    public function test_le_bouton_afficher_masquer_est_present_sur_mon_profil(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.profil.edit'))
            ->assertOk()
            ->assertSee('toggle-mot-de-passe', false);
    }

    public function test_le_bouton_afficher_masquer_est_present_sur_la_section_mail_des_parametres(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.parametres.index', ['section' => 'mail']))
            ->assertOk()
            ->assertSee('toggle-mot-de-passe', false);
    }
}
