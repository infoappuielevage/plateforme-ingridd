<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\RoleController — remplace App\Filament\Resources\
 * RoleResource sur demande du client (plus de Filament). Réservé en dur au
 * rôle admin, comme la Resource Filament d'origine (voir son docblock).
 */
class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_aux_roles(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.roles.index'))
            ->assertForbidden();
    }

    public function test_un_admin_peut_creer_un_role_avec_sa_matrice_de_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.roles.store'), [
                'slug' => 'archiviste',
                'name' => 'Archiviste',
                'permissions' => [
                    'documents' => ['view' => '1', 'create' => '1'],
                    'filieres' => ['view' => '1'],
                ],
            ])
            ->assertRedirect(route('gestion.roles.index'));

        $role = Role::query()->where('slug', 'archiviste')->firstOrFail();

        $this->assertTrue($role->permissions['documents']['view']);
        $this->assertTrue($role->permissions['documents']['create']);
        $this->assertFalse($role->permissions['documents']['edit']);
        $this->assertFalse($role->permissions['documents']['delete']);
        $this->assertTrue($role->permissions['filieres']['view']);
        $this->assertFalse($role->permissions['niveaux']['view']);
        // "Historique" n'a que "Voir" (lecture seule partout) — voir RoleController::valider().
        $this->assertArrayNotHasKey('create', $role->permissions['audit_logs']);
    }

    /** Le champ Identifiant (slug) est désactivé (disabled) en modification, donc jamais envoyé — voir RoleController::valider(). */
    public function test_la_modification_fonctionne_sans_envoyer_le_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::query()->create([
            'slug' => 'archiviste',
            'name' => 'Ancien nom',
            'permissions' => [],
        ]);

        $this->actingAs($admin)
            ->put(route('gestion.roles.update', $role->id), [
                'name' => 'Nouveau nom',
                'permissions' => ['documents' => ['view' => '1']],
            ])
            ->assertRedirect(route('gestion.roles.index'));

        $role->refresh();
        $this->assertSame('Nouveau nom', $role->name);
        $this->assertSame('archiviste', $role->slug);
        $this->assertTrue($role->permissions['documents']['view']);
    }

    /**
     * Une case décochée n'est jamais envoyée par le navigateur : modifier un
     * rôle sans renvoyer `permissions` du tout doit remettre TOUTE la
     * matrice à false, pas laisser les anciennes valeurs inchangées.
     */
    public function test_ne_rien_envoyer_pour_permissions_desactive_tous_les_droits(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::query()->create([
            'slug' => 'archiviste',
            'name' => 'Archiviste',
            'permissions' => ['documents' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true]],
        ]);

        $this->actingAs($admin)
            ->put(route('gestion.roles.update', $role->id), [
                'name' => 'Archiviste',
            ])
            ->assertRedirect();

        $this->assertFalse($role->fresh()->permissions['documents']['view']);
    }

    public function test_le_role_admin_ne_peut_jamais_etre_supprime(): void
    {
        $admin = User::factory()->admin()->create();
        $roleAdmin = Role::query()->create(['slug' => User::ROLE_ADMIN, 'name' => 'Administrateur', 'permissions' => []]);

        $this->actingAs($admin)
            ->delete(route('gestion.roles.destroy', $roleAdmin->id))
            ->assertForbidden();

        $this->assertNotNull($roleAdmin->fresh());
    }

    public function test_un_role_non_admin_peut_etre_supprime(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::query()->create(['slug' => 'archiviste', 'name' => 'Archiviste', 'permissions' => []]);

        $this->actingAs($admin)
            ->delete(route('gestion.roles.destroy', $role->id))
            ->assertRedirect();

        $this->assertNull(Role::find($role->id));
    }
}
