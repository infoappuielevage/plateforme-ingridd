<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\AuditLogController — remplace App\Filament\Resources\
 * AuditLogResource sur demande du client (plus de Filament). Lecture seule,
 * passe par App\Support\ResourceAccess (clé "audit_logs") comme la Resource
 * d'origine — pas réservé en dur à l'admin, contrairement à Rôles/
 * Utilisateurs/Paramètres/Sauvegardes.
 */
class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_voit_lhistorique(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create([
            'user_name' => 'Alice',
            'action' => 'creation',
            'resume' => 'A créé le document "contrat-visible.pdf"',
        ]);

        $this->actingAs($admin)
            ->get(route('gestion.historique.index'))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('contrat-visible.pdf');
    }

    public function test_un_role_sans_droit_de_vue_sur_lhistorique_ne_peut_pas_y_acceder(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_historique',
            'name' => 'Sans droits',
            'permissions' => ['audit_logs' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_historique']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.historique.index'))
            ->assertForbidden();
    }

    public function test_un_role_avec_droit_de_vue_sur_lhistorique_peut_y_acceder(): void
    {
        Role::query()->create([
            'slug' => 'avec_droits_historique',
            'name' => 'Avec droits',
            'permissions' => ['audit_logs' => ['view' => true]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'avec_droits_historique']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.historique.index'))
            ->assertOk();
    }

    public function test_le_filtre_par_action_fonctionne(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create(['user_name' => 'Alice', 'action' => 'creation', 'resume' => 'Résumé création']);
        AuditLog::query()->create(['user_name' => 'Bob', 'action' => 'suppression', 'resume' => 'Résumé suppression']);

        $this->actingAs($admin)
            ->get(route('gestion.historique.index', ['action' => 'creation']))
            ->assertOk()
            ->assertSee('Résumé création')
            ->assertDontSee('Résumé suppression');
    }

    public function test_la_recherche_filtre_par_utilisateur_ou_resume(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create(['user_name' => 'Alice Martin', 'action' => 'connexion', 'resume' => 'Connexion réussie']);
        AuditLog::query()->create(['user_name' => 'Bob Traore', 'action' => 'connexion', 'resume' => 'Connexion réussie']);

        $this->actingAs($admin)
            ->get(route('gestion.historique.index', ['recherche' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Martin')
            ->assertDontSee('Bob Traore');
    }
}
