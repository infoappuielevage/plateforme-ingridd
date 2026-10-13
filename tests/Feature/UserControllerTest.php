<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteBloqueNotification;
use App\Notifications\MotDePasseReinitialiseParAdminNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * App\Http\Controllers\UserController — remplace App\Filament\Resources\
 * UserResource sur demande du client (plus de Filament). Réservé en dur au
 * rôle admin, comme la Resource Filament d'origine (voir son docblock).
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function creerRoleGestionnaire(): Role
    {
        return Role::query()->create(['slug' => 'gestionnaire', 'name' => 'Gestionnaire', 'permissions' => []]);
    }

    public function test_un_non_admin_ne_peut_pas_acceder_aux_utilisateurs(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.utilisateurs.index'))
            ->assertForbidden();
    }

    /**
     * Demande explicite du client : tous les tableaux de l'espace de gestion
     * limités à 8 lignes par page, le reste accessible par numéro de page.
     * Cet écran n'utilisait auparavant aucune pagination (->get(), tout
     * affiché d'un coup) — voir UserController::index().
     */
    public function test_la_liste_des_utilisateurs_est_paginee_a_huit_par_page(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(10)->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.utilisateurs.index'));

        $reponse->assertOk();
        $reponse->assertViewHas('utilisateurs', fn ($utilisateurs) => $utilisateurs->perPage() === 8 && $utilisateurs->total() === 11);
    }

    public function test_un_admin_peut_creer_un_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.store'), [
                'name' => 'Nouvel utilisateur',
                'email' => 'nouveau@ingridd.test',
                'role' => 'gestionnaire',
                'password' => 'MotDePasse123',
            ])
            ->assertRedirect(route('gestion.utilisateurs.index'));

        $utilisateur = User::query()->where('email', 'nouveau@ingridd.test')->firstOrFail();
        $this->assertSame('gestionnaire', $utilisateur->role);
        $this->assertTrue(Hash::check('MotDePasse123', $utilisateur->password));
    }

    public function test_le_mot_de_passe_est_obligatoire_a_la_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.store'), [
                'name' => 'Nouvel utilisateur',
                'email' => 'nouveau@ingridd.test',
                'role' => 'gestionnaire',
            ])
            ->assertSessionHasErrors('password');
    }

    /**
     * Un mot de passe trop simple (uniquement des lettres minuscules, pas de
     * chiffre, trop court) reste refusé pour un compte donnant accès à des
     * dossiers confidentiels — revue de sécurité demandée par le client.
     * Reprend désormais EXACTEMENT la même politique que
     * ProfilController::update() (voir App\Providers\AppServiceProvider::boot()
     * et Password::default()) : min 10, majuscule + minuscule, au moins un
     * chiffre.
     */
    public function test_un_mot_de_passe_trop_simple_est_refuse(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.store'), [
                'name' => 'Nouvel utilisateur',
                'email' => 'nouveau@ingridd.test',
                'role' => 'gestionnaire',
                'password' => 'uniquementdeslettres',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nouveau@ingridd.test']);
    }

    /** Laisser le mot de passe vide en modification ne doit pas toucher au mot de passe existant — voir UserResource::form() d'origine. */
    public function test_laisser_le_mot_de_passe_vide_en_modification_le_conserve(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();
        $ancienHash = Hash::make('ancien-mot-de-passe');
        $utilisateur = User::factory()->create(['role' => 'gestionnaire', 'password' => $ancienHash]);

        $this->actingAs($admin)
            ->put(route('gestion.utilisateurs.update', $utilisateur->id), [
                'name' => 'Nom modifié',
                'email' => $utilisateur->email,
                'role' => 'gestionnaire',
            ])
            ->assertRedirect(route('gestion.utilisateurs.index'));

        $utilisateur->refresh();
        $this->assertSame('Nom modifié', $utilisateur->name);
        $this->assertSame($ancienHash, $utilisateur->password);
    }

    public function test_renvoyer_un_mot_de_passe_en_modification_le_change(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();
        $utilisateur = User::factory()->create(['role' => 'gestionnaire']);

        $this->actingAs($admin)
            ->put(route('gestion.utilisateurs.update', $utilisateur->id), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'role' => 'gestionnaire',
                'password' => 'Nouveau-Mot-De-Passe-1',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('Nouveau-Mot-De-Passe-1', $utilisateur->fresh()->password));
    }

    /** Même garde-fou que UserResource::canDelete() : un admin ne peut pas se supprimer lui-même. */
    public function test_un_admin_ne_peut_pas_se_supprimer_lui_meme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.utilisateurs.destroy', $admin->id))
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_un_admin_peut_supprimer_un_autre_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();
        $autre = User::factory()->gestionnaire()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.utilisateurs.destroy', $autre->id))
            ->assertRedirect();

        $this->assertNull(User::find($autre->id));
    }

    /**
     * UserController::basculerBlocage() — demande explicite du client
     * ("l'administration doit pouvoir réinitialiser un compte ou le
     * bloquer"). `bloque_at` n'est pas dans $fillable (voir son docblock) :
     * seule cette action peut le modifier.
     */
    public function test_un_admin_peut_bloquer_un_compte(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.bloquer', $utilisateur->id))
            ->assertRedirect(route('gestion.utilisateurs.index'));

        $this->assertTrue($utilisateur->fresh()->estBloque());

        // Cloche de notifications (voir layouts/gestion.blade.php) — la
        // personne concernée doit être prévenue (voir le docblock de
        // App\Notifications\CompteBloqueNotification).
        Notification::assertSentTo($utilisateur, CompteBloqueNotification::class);
    }

    public function test_bloquer_un_compte_deja_bloque_le_debloque(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->gestionnaire()->create();
        // `bloque_at` n'est pas dans User::$fillable (voir son docblock) :
        // forceFill() est nécessaire ici, comme dans
        // UserController::basculerBlocage() lui-même.
        $utilisateur->forceFill(['bloque_at' => now()])->save();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.bloquer', $utilisateur->id))
            ->assertRedirect(route('gestion.utilisateurs.index'));

        $this->assertFalse($utilisateur->fresh()->estBloque());
    }

    /** Même garde-fou que pour la suppression : un admin ne peut pas se bloquer lui-même. */
    public function test_un_admin_ne_peut_pas_se_bloquer_lui_meme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.bloquer', $admin->id))
            ->assertForbidden();

        $this->assertFalse($admin->fresh()->estBloque());
    }

    public function test_un_non_admin_ne_peut_pas_bloquer_un_compte(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $autre = User::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('gestion.utilisateurs.bloquer', $autre->id))
            ->assertForbidden();

        $this->assertFalse($autre->fresh()->estBloque());
    }

    /**
     * UserController::reinitialiserMotDePasse() — même notification
     * ResetPassword que le "mot de passe oublié" public (voir
     * tests/Feature/ReinitialisationMotDePasseTest.php), déclenchée cette
     * fois par un administrateur depuis l'écran Utilisateurs plutôt que par
     * l'utilisateur lui-même.
     */
    public function test_un_admin_peut_reinitialiser_le_mot_de_passe_dun_compte(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.reinitialiser-mot-de-passe', $utilisateur->id))
            ->assertRedirect(route('gestion.utilisateurs.index'));

        Notification::assertSentTo($utilisateur, ResetPassword::class);
        // Cloche de notifications (voir layouts/gestion.blade.php) — simple
        // rappel dans l'application, en plus du lien envoyé par email
        // ci-dessus (voir le docblock de App\Notifications\
        // MotDePasseReinitialiseParAdminNotification).
        Notification::assertSentTo($utilisateur, MotDePasseReinitialiseParAdminNotification::class);
    }

    public function test_un_non_admin_ne_peut_pas_reinitialiser_le_mot_de_passe_dun_compte(): void
    {
        Notification::fake();

        $gestionnaire = User::factory()->gestionnaire()->create();
        $autre = User::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('gestion.utilisateurs.reinitialiser-mot-de-passe', $autre->id))
            ->assertForbidden();

        Notification::assertNothingSent();
    }
}
