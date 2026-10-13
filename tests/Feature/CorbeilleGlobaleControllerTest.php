<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\Reunion;
use App\Models\TypeContrat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\CorbeilleGlobaleController — vue d'ensemble de toutes
 * les corbeilles (voir le docblock de App\Support\CorbeillesGlobales). Ces
 * tests couvrent seulement l'écran (accès admin, agrégation) ; les
 * comportements de corbeille propres à chaque module (restauration,
 * suppression définitive...) restent couverts par les tests de chaque
 * contrôleur (ex. ReunionControllerTest, ContratControllerTest).
 */
class CorbeilleGlobaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_lecran(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.corbeilles.index'))
            ->assertOk();
    }

    public function test_un_non_admin_ne_peut_pas_acceder(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.corbeilles.index'))
            ->assertForbidden();
    }

    /**
     * Vérifie que l'agrégation remonte bien des éléments à la corbeille
     * venant de DEUX modules différents (un écran "simple" — Réunions — et
     * un écran à catégories ouvertes — Contrats, voir CorbeillesGlobales::
     * categoriesContrats()) et que le total correspond à leur somme.
     */
    public function test_lecran_agrege_les_corbeilles_de_plusieurs_modules(): void
    {
        $admin = User::factory()->admin()->create();

        Reunion::factory()->create()->delete();
        Reunion::factory()->create()->delete();

        $typeContrat = TypeContrat::factory()->create(['libelle' => 'Partenariat', 'actif' => true]);
        Contrat::factory()->create(['type_contrat_id' => $typeContrat->id])->delete();

        $reponse = $this->actingAs($admin)->get(route('gestion.corbeilles.index'));

        $reponse->assertOk();
        $reponse->assertViewHas('total', 3);
        $reponse->assertSee('Partenariat', false);
    }
}
