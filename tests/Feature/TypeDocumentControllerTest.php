<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CaptureExportExcel;
use Tests\TestCase;

/**
 * App\Http\Controllers\TypeDocumentController — remplace TypeDocumentResource
 * (formulaire + SousTypesRelationManager) sur demande du client (plus de
 * Filament). Contrôleur dédié plutôt que rattaché à ReferentielController :
 * voir son docblock pour pourquoi (quatre drapeaux en plus, sous-référentiel
 * des sous-types).
 */
class TypeDocumentControllerTest extends TestCase
{
    use CaptureExportExcel;
    use RefreshDatabase;

    /** export() respecte les mêmes filtres que index() (même requeteListe() partagée, voir le docblock d'export()). */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'VISIBLE', 'libelle' => 'Contrat visible']);
        TypeDocument::factory()->create(['code' => 'AUTRE', 'libelle' => 'Autre type']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.types-documents.export', ['recherche' => 'visible']))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame(['VISIBLE', 'Contrat visible'], array_slice($lignes[0], 0, 2));
    }

    public function test_la_liste_est_accessible_a_un_admin(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['libelle' => 'Contrat visible']);

        $this->actingAs($admin)
            ->get(route('gestion.types-documents.index'))
            ->assertOk()
            ->assertSee('Contrat visible');
    }

    public function test_un_role_sans_droit_de_vue_ne_peut_pas_acceder_a_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_types_documents',
            'name' => 'Sans droits',
            'permissions' => ['types_documents' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_types_documents']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.types-documents.index'))
            ->assertForbidden();
    }

    public function test_la_creation_enregistre_le_code_et_les_drapeaux(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-documents.store'), [
                'code' => 'NOUVEAU_TYPE',
                'libelle' => 'Nouveau type',
                'ordre' => 1,
                'actif' => '1',
                'epingle_secretariat' => '1',
                'champs_diplome_actifs' => '1',
                'champs_soutenance_actifs' => '0',
                'champ_semestre_actif' => '0',
            ])
            ->assertRedirect(route('gestion.types-documents.index'));

        $this->assertDatabaseHas('type_documents', [
            'code' => 'NOUVEAU_TYPE',
            'epingle_secretariat' => true,
            'champs_diplome_actifs' => true,
            'champs_soutenance_actifs' => false,
        ]);
    }

    /** Le champ Code est désactivé (disabled) en modification, donc jamais envoyé — voir TypeDocumentController::valider(). */
    public function test_la_modification_fonctionne_sans_envoyer_le_code(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $this->actingAs($admin)
            ->put(route('gestion.types-documents.update', $type->id), [
                'libelle' => 'Libellé modifié',
                'ordre' => 3,
                'actif' => '1',
                'champ_semestre_actif' => '1',
            ])
            ->assertRedirect(route('gestion.types-documents.index'));

        $type->refresh();
        $this->assertSame('Libellé modifié', $type->libelle);
        $this->assertSame('CONTRAT', $type->code);
        $this->assertTrue($type->champ_semestre_actif);
    }

    public function test_impossible_de_mettre_a_la_corbeille_un_type_encore_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        Document::factory()->create(['type_document_id' => $type->id]);

        $this->actingAs($admin)
            ->delete(route('gestion.types-documents.destroy', $type->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($type->fresh()->deleted_at);
    }

    public function test_un_sous_type_peut_etre_ajoute_modifie_et_supprime(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-documents.sous-types.store', $type->id), [
                'sous_type_libelle' => 'Succès',
                'sous_type_ordre' => 1,
                'sous_type_actif' => '1',
            ])
            ->assertRedirect();

        $sousType = SousTypeDocument::query()->where('libelle', 'Succès')->firstOrFail();
        $this->assertSame($type->id, $sousType->type_document_id);

        $this->actingAs($admin)
            ->put(route('gestion.types-documents.sous-types.update', ['type' => $type->id, 'sousType' => $sousType->id]), [
                'sous_type_libelle' => 'Succès (modifié)',
                'sous_type_ordre' => 2,
                'sous_type_actif' => '0',
            ])
            ->assertRedirect();

        $sousType->refresh();
        $this->assertSame('Succès (modifié)', $sousType->libelle);
        $this->assertFalse($sousType->actif);

        $this->actingAs($admin)
            ->delete(route('gestion.types-documents.sous-types.destroy', ['type' => $type->id, 'sousType' => $sousType->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('sous_types_documents', ['id' => $sousType->id]);
    }

    /**
     * Un sous-type qui n'appartient pas au type de la route ne doit jamais
     * être modifiable/supprimable via l'URL d'un autre type — voir le garde-fou
     * abort_unless(...) de TypeDocumentController::updateSousType()/destroySousType().
     */
    public function test_un_sous_type_dun_autre_type_ne_peut_pas_etre_modifie(): void
    {
        $admin = User::factory()->admin()->create();
        $typeA = TypeDocument::factory()->create();
        $typeB = TypeDocument::factory()->create();
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $typeA->id]);

        $this->actingAs($admin)
            ->put(route('gestion.types-documents.sous-types.update', ['type' => $typeB->id, 'sousType' => $sousType->id]), [
                'sous_type_libelle' => 'Tentative',
                'sous_type_ordre' => 1,
                'sous_type_actif' => '1',
            ])
            ->assertNotFound();
    }

    /** La suppression d'un sous-type est directe (pas de corbeille) : un document déjà classé dedans doit survivre, sous-type redevenu vide. */
    public function test_supprimer_un_sous_type_ne_supprime_pas_les_documents_qui_lutilisaient(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id]);
        $document = Document::factory()->create(['type_document_id' => $type->id, 'sous_type_document_id' => $sousType->id]);

        $this->actingAs($admin)
            ->delete(route('gestion.types-documents.sous-types.destroy', ['type' => $type->id, 'sousType' => $sousType->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'sous_type_document_id' => null]);
    }
}
