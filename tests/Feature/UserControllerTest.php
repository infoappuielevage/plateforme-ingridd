<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_un_admin_peut_creer_un_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();
        $this->creerRoleGestionnaire();

        $this->actingAs($admin)
            ->post(route('gestion.utilisateurs.store'), [
                'name' => 'Nouvel utilisateur',
                'email' => 'nouveau@ingridd.test',
                'role' => 'gestionnaire',
                'password' => 'motdepasse123',
            ])
            ->assertRedirect(route('gestion.utilisateurs.index'));

        $utilisateur = User::query()->where('email', 'nouveau@ingridd.test')->firstOrFail();
        $this->assertSame('gestionnaire', $utilisateur->role);
        $this->assertTrue(Hash::check('motdepasse123', $utilisateur->password));
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
                'password' => 'nouveau-mot-de-passe',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $utilisateur->fresh()->password));
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
}
