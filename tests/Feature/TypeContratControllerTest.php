<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\Role;
use App\Models\TypeContrat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\TypeContratController — référentiel des catégories de
 * contrats (voir le docblock de la classe testée). MÊME PRINCIPE que
 * tests/Feature/TypePieceEtudiantControllerTest.php pour la structure
 * générale (corbeille, permissions, code figé après création), MAIS SANS
 * synchronisation rétroactive (un contrat est créé à la demande, pas une
 * ligne par année académique) : le point testé ici à la place est le garde-
 * fou propre aux contrats — une catégorie encore utilisée (contrat actif OU
 * déjà à la corbeille, puisqu'un contrat porte de vrais documents) ne peut
 * ni être mise à la corbeille ni être supprimée définitivement.
 */
class TypeContratControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.types-contrats.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_types_contrats',
            'name' => 'Sans droits',
            'permissions' => ['types_contrats' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_types_contrats']);

        $this->actingAs($utilisateur)->get(route('gestion.types-contrats.index'))->assertForbidden();
    }

    public function test_creation_dune_categorie(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-contrats.store'), [
                'code' => 'PARTENARIAT',
                'libelle' => 'Partenariat',
                'ordre' => 1,
                'actif' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('types_contrats', [
            'code' => 'PARTENARIAT',
            'libelle' => 'Partenariat',
            'ordre' => 1,
            'actif' => 1,
        ]);
    }

    public function test_la_validation_exige_les_champs_obligatoires(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-contrats.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['code', 'libelle', 'ordre']);

        $this->assertSame(0, TypeContrat::query()->count());
    }

    /** Le code doit être unique parmi les catégories (actives ou à la corbeille). */
    public function test_le_code_doit_etre_unique(): void
    {
        $admin = User::factory()->admin()->create();
        TypeContrat::factory()->create(['code' => 'BAIL']);

        $this->actingAs($admin)
            ->post(route('gestion.types-contrats.store'), [
                'code' => 'BAIL',
                'libelle' => 'Bail / location (doublon)',
                'ordre' => 2,
                'actif' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertSame(1, TypeContrat::query()->where('code', 'BAIL')->count());
    }

    /** Le code ne se modifie plus après création — seuls libellé/ordre/actif restent modifiables. */
    public function test_le_code_reste_fige_a_la_modification(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create(['code' => 'PARTENARIAT', 'libelle' => 'Partenariat', 'ordre' => 1]);

        $this->actingAs($admin)
            ->put(route('gestion.types-contrats.update', $type->id), [
                'code' => 'AUTRE_CODE',
                'libelle' => 'Partenariat (mis à jour)',
                'ordre' => 5,
                'actif' => '0',
            ])
            ->assertRedirect();

        $type->refresh();
        $this->assertSame('PARTENARIAT', $type->code);
        $this->assertSame('Partenariat (mis à jour)', $type->libelle);
        $this->assertSame(5, $type->ordre);
        $this->assertFalse($type->actif);
    }

    /** Mise à la corbeille bloquée si au moins un contrat ACTIF existe encore dans cette catégorie. */
    public function test_mise_a_la_corbeille_bloquee_si_un_contrat_actif_existe_encore(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create();
        Contrat::factory()->create(['type_contrat_id' => $type->id]);

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.destroy', $type->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($type->fresh()->deleted_at);
    }

    /** Bloquée aussi si le seul contrat restant est déjà à la corbeille — un contrat porte de vrais documents, jamais "forcément vide". */
    public function test_mise_a_la_corbeille_bloquee_si_un_contrat_est_deja_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $type->id]);
        $contrat->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.destroy', $type->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($type->fresh()->deleted_at);
    }

    public function test_mise_a_la_corbeille_et_restauration_dune_categorie_sans_contrat(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.destroy', $type->id))
            ->assertRedirect();

        $this->assertNotNull($type->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.types-contrats.restore', $type->id))
            ->assertRedirect();

        $this->assertNull($type->fresh()->deleted_at);
    }

    /** Garde-fou défensif (abort_if 409) — la contrainte SQL restrictOnDelete ne peut en pratique jamais se déclencher, destroy() ayant déjà vérifié qu'aucun contrat ne subsiste. */
    public function test_suppression_definitive_bloquee_si_un_contrat_existe_encore(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $type->id]);
        $type->delete();
        $contrat->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.force-destroy', $type->id))
            ->assertStatus(409);

        $this->assertDatabaseHas('types_contrats', ['id' => $type->id]);
    }

    public function test_suppression_definitive_dune_categorie_sans_contrat(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeContrat::factory()->create();
        $type->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.force-destroy', $type->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('types_contrats', ['id' => $type->id]);
    }

    /**
     * "Vider la corbeille" — demande explicite du client (voir le docblock
     * de TypeContratController::viderCorbeille()) : purge TOUTES les
     * catégories corbeillées en un clic, SAUF celles encore utilisées par au
     * moins un contrat (même garde-fou que forceDestroy(), mais ignorée
     * plutôt que de faire échouer toute l'opération — jamais d'abort_if()
     * dans une boucle).
     */
    public function test_vider_la_corbeille_ignore_les_categories_encore_utilisees(): void
    {
        $admin = User::factory()->admin()->create();

        $typePurgeable = TypeContrat::factory()->create();
        $typePurgeable->delete();

        $typeEncoreUtilise = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeEncoreUtilise->id]);
        $contrat->delete();
        $typeEncoreUtilise->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.types-contrats.corbeille.vider'))
            ->assertRedirect();

        $this->assertDatabaseMissing('types_contrats', ['id' => $typePurgeable->id]);
        $this->assertDatabaseHas('types_contrats', ['id' => $typeEncoreUtilise->id]);
    }
}
