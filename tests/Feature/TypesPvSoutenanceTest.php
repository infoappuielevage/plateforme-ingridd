<?php

namespace Tests\Feature;

use App\Models\TypeDocument;
use App\Support\TypesPvSoutenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TypesPvSoutenance::ids() — utilisé par DocumentController/DocumentResource
 * (form/table) pour repérer les documents "PV de soutenance" sans dépendre
 * d'un id fixe.
 *
 * Piloté par la colonne `champs_soutenance_actifs` de App\Models\TypeDocument
 * (voir la migration 2026_09_29_000021_..., même principe que
 * TypesDiplomeTest) : un administrateur peut désormais activer ce
 * comportement sur n'importe quel type depuis l'administration.
 */
class TypesPvSoutenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_un_tableau_vide_si_aucun_type_na_le_drapeau_actif(): void
    {
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);

        $this->assertSame([], TypesPvSoutenance::ids());
    }

    public function test_retourne_lid_du_type_ayant_le_drapeau_actif(): void
    {
        $pvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'champs_soutenance_actifs' => true]);
        // Un type sans le drapeau ne doit pas apparaître dans la liste.
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $this->assertSame([$pvSoutenance->id], TypesPvSoutenance::ids());
    }

    /** Même principe que TypesDiplomeTest : n'importe quel type peut activer ce comportement. */
    public function test_fonctionne_pour_un_type_quelconque_du_moment_que_le_drapeau_est_actif(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'PV_REUNION', 'champs_soutenance_actifs' => true]);

        $this->assertSame([$type->id], TypesPvSoutenance::ids());
    }
}
