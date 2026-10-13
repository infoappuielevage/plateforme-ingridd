<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * App\Http\Controllers\Auth\AuthController::formulaireOubli() /
 * envoyerLienReinitialisation() / formulaireReinitialisation() /
 * reinitialiserMotDePasse() — "mot de passe oublié", demandé par le client
 * ("ok, vas-y" sur l'écran mot de passe oublié). S'appuie sur le broker
 * standard de Laravel déjà présent (config/auth.php, table
 * password_reset_tokens) : aucune nouvelle migration nécessaire.
 */
class ReinitialisationMotDePasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_formulaire_mot_de_passe_oublie_est_accessible_sans_connexion(): void
    {
        $this->get(route('mot-de-passe.oublie'))->assertOk();
    }

    /**
     * Password::sendResetLink() déclenche normalement la notification
     * ResetPassword de Laravel — on vérifie qu'elle part bien vers le bon
     * utilisateur, sans dépendre d'un vrai envoi SMTP (MAIL_MAILER=log en
     * local, voir .env.example).
     */
    public function test_un_email_connu_recoit_une_notification_de_reinitialisation(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create(['email' => 'connu@ingridd.test']);

        $this->post(route('mot-de-passe.envoyer'), ['email' => 'connu@ingridd.test'])
            ->assertSessionHas('status');

        Notification::assertSentTo($utilisateur, ResetPassword::class);
    }

    /**
     * Sécurité : le message de retour ne doit JAMAIS trahir si un email
     * existe ou non dans la base (énumération de comptes) — même message,
     * aucune notification envoyée, aucune erreur visible.
     */
    public function test_un_email_inconnu_recoit_le_meme_message_sans_erreur(): void
    {
        Notification::fake();

        $reponse = $this->post(route('mot-de-passe.envoyer'), ['email' => 'inconnu@ingridd.test']);

        $reponse->assertSessionHas('status');
        $reponse->assertSessionDoesntHaveErrors();
        Notification::assertNothingSent();
    }

    public function test_le_lien_recu_par_email_permet_de_definir_un_nouveau_mot_de_passe(): void
    {
        $utilisateur = User::factory()->create(['email' => 'reset@ingridd.test']);
        $jeton = Password::createToken($utilisateur);

        $this->post(route('mot-de-passe.mettre-a-jour'), [
            'token' => $jeton,
            'email' => 'reset@ingridd.test',
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NouveauMotDePasse1', $utilisateur->fresh()->password));
    }

    public function test_un_jeton_invalide_est_refuse(): void
    {
        $utilisateur = User::factory()->create(['email' => 'reset2@ingridd.test']);
        $ancienHash = $utilisateur->password;

        $this->post(route('mot-de-passe.mettre-a-jour'), [
            'token' => 'jeton-invalide',
            'email' => 'reset2@ingridd.test',
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
        ])->assertSessionHasErrors('email');

        $this->assertSame($ancienHash, $utilisateur->fresh()->password);
    }

    /**
     * Même politique de mot de passe que partout ailleurs sur la
     * plateforme (Password::default(), voir AppServiceProvider::boot()) —
     * pas de règle plus faible ici juste parce qu'il s'agit d'un
     * formulaire public avant connexion.
     */
    public function test_un_mot_de_passe_trop_simple_est_refuse_a_la_reinitialisation(): void
    {
        $utilisateur = User::factory()->create(['email' => 'reset3@ingridd.test']);
        $jeton = Password::createToken($utilisateur);
        $ancienHash = $utilisateur->password;

        $this->post(route('mot-de-passe.mettre-a-jour'), [
            'token' => $jeton,
            'email' => 'reset3@ingridd.test',
            'password' => 'uniquementdeslettres',
            'password_confirmation' => 'uniquementdeslettres',
        ])->assertSessionHasErrors('password');

        $this->assertSame($ancienHash, $utilisateur->fresh()->password);
    }

    /**
     * Même garde-fou anti-abus que la connexion : 5 demandes par minute
     * maximum (email + IP), sinon un visiteur pourrait déclencher l'envoi
     * en boucle d'emails vers une adresse qui ne lui appartient pas.
     */
    public function test_trop_de_demandes_de_reinitialisation_sont_bloquees(): void
    {
        Notification::fake();
        RateLimiter::clear('cible@ingridd.test|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('mot-de-passe.envoyer'), ['email' => 'cible@ingridd.test'])
                ->assertSessionDoesntHaveErrors();
        }

        $this->post(route('mot-de-passe.envoyer'), ['email' => 'cible@ingridd.test'])
            ->assertSessionHasErrors('email');
    }
}
