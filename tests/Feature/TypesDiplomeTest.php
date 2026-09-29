<?php

namespace Tests\Feature;

use App\Models\TypeDocument;
use App\Support\TypesDiplome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TypesDiplome::ids() — utilisé par DocumentController/DocumentResource
 * (form/table) et les widgets de tableau de bord pour repérer les documents
 * "Diplôme"/"Attestation" sans dépendre d'un id fixe.
 *
 * Piloté par la colonne `champs_diplome_actifs` de App\Models\TypeDocument
 * (voir la migration 2026_09_29_000021_..., qui remplace l'ancienne liste de
 * codes figée dans le code) : sur demande explicite du client, un
 * administrateur peut désormais activer ce comportement sur N'IMPORTE QUEL
 * type de document depuis l'administration, sans redéploiement — c'est
 * pourquoi ces tests activent le drapeau explicitement plutôt que de
 * dépendre d'un code particulier.
 */
class TypesDiplomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_un_tableau_vide_si_aucun_type_na_le_drapeau_actif(): void
    {
        TypeDocument::factory()->create(['code' => 'DIPLOME']);
        TypeDocument::factory()->create(['code' => 'ATTESTATION']);

        $this->assertSame([], TypesDiplome::ids());
    }

    public function test_retourne_les_id_des_types_ayant_le_drapeau_actif(): void
    {
        $diplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $attestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);
        // Un type sans le drapeau ne doit pas apparaître dans la liste,
        // même avec un code proche.
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $ids = TypesDiplome::ids();

        $this->assertCount(2, $ids);
        $this->assertContains($diplome->id, $ids);
        $this->assertContains($attestation->id, $ids);
    }

    public function test_fonctionne_meme_si_un_seul_type_a_le_drapeau_actif(): void
    {
        $diplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        TypeDocument::factory()->create(['code' => 'ATTESTATION']);

        $this->assertSame([$diplome->id], TypesDiplome::ids());
    }

    /**
     * N'importe quel type — pas seulement DIPLOME/ATTESTATION — peut
     * recevoir les champs complémentaires : c'est exactement le
     * comportement demandé par le client ("tout doit pouvoir être flexible
     * via l'administrateur").
     */
    public function test_fonctionne_pour_un_type_quelconque_du_moment_que_le_drapeau_est_actif(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'BORDEREAU', 'champs_diplome_actifs' => true]);

        $this->assertSame([$type->id], TypesDiplome::ids());
    }
}
