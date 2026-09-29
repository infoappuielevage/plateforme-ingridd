<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * resources/views/layouts/gestion.blade.php — vérifie que les liens
 * "Référentiels"/"Administration"/"Mon profil" pointent maintenant vers de
 * vraies pages (fin du portage Filament -> Blade, tâche "Brancher tous les
 * nouveaux écrans dans le menu latéral"), plus plafonnés "bientôt" comme
 * avant. Rendu par le tableau de bord, comme SecretariatControllerTest le
 * fait déjà pour le menu Secrétariat.
 */
class MenuLateralTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_voit_tous_les_liens_referentiels_et_administration(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertDontSee('bientôt')
            ->assertSee(route('gestion.types-documents.index'), false)
            ->assertSee(route('gestion.referentiels.index', ['referentiel' => 'filieres']), false)
            ->assertSee(route('gestion.referentiels.index', ['referentiel' => 'niveaux']), false)
            ->assertSee(route('gestion.referentiels.index', ['referentiel' => 'annees-academiques']), false)
            ->assertSee(route('gestion.roles.index'), false)
            ->assertSee(route('gestion.utilisateurs.index'), false)
            ->assertSee(route('gestion.sauvegardes.index'), false)
            ->assertSee(route('gestion.parametres.index'), false)
            ->assertSee(route('gestion.historique.index'), false)
            ->assertSee(route('gestion.profil.edit'), false);
    }

    public function test_un_role_sans_aucun_droit_ne_voit_ni_referentiels_ni_administration(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_menu',
            'name' => 'Sans droits',
            'permissions' => [],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_menu']);

        $reponse = $this->actingAs($utilisateur)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertDontSee('Référentiels')
            ->assertDontSee('Administration')
            // "Mon profil" reste visible : chaque utilisateur connecté peut modifier son propre compte, quel que soit son rôle.
            ->assertSee(route('gestion.profil.edit'), false);
    }

    /** Historique passe par ResourceAccess (voir AuditLogController), pas réservé en dur à l'admin comme le reste d'Administration. */
    public function test_un_role_avec_uniquement_le_droit_historique_voit_administration_mais_pas_les_ecrans_reserves_a_ladmin(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_historique',
            'name' => 'Lecteur historique',
            'permissions' => ['audit_logs' => ['view' => true]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_historique']);

        $reponse = $this->actingAs($utilisateur)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertSee('Administration')
            ->assertSee(route('gestion.historique.index'), false)
            ->assertDontSee(route('gestion.roles.index'), false)
            ->assertDontSee(route('gestion.utilisateurs.index'), false)
            ->assertDontSee(route('gestion.sauvegardes.index'), false)
            ->assertDontSee(route('gestion.parametres.index'), false);
    }
}
