<?php

namespace Tests\Feature;

use App\Filament\Resources\TypeDocumentResource\Pages\EditTypeDocument;
use App\Filament\Resources\TypeDocumentResource\RelationManagers\SousTypesRelationManager;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gestion des sous-types depuis la fiche d'un type de document
 * (Administration > Référentiels > Types de documents > ouvrir un type >
 * onglet "Sous-types") — voir SousTypesRelationManager. N'apparaît que sur
 * la page de modification (jamais à la création, un type devant d'abord
 * exister).
 */
class SousTypesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_seuls_les_sous_types_du_type_ouvert_sont_affiches(): void
    {
        $admin = User::factory()->admin()->create();
        $attestation = TypeDocument::factory()->create(['code' => 'ATTESTATION']);
        $bordereau = TypeDocument::factory()->create(['code' => 'BORDEREAU']);

        $succes = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Succès']);
        $cnss = SousTypeDocument::factory()->create(['type_document_id' => $bordereau->id, 'libelle' => 'CNSS']);

        Livewire::actingAs($admin)
            ->test(SousTypesRelationManager::class, [
                'ownerRecord' => $attestation,
                'pageClass' => EditTypeDocument::class,
            ])
            ->assertCanSeeTableRecords([$succes])
            ->assertCanNotSeeTableRecords([$cnss]);
    }

    public function test_un_admin_peut_ajouter_un_sous_type(): void
    {
        $admin = User::factory()->admin()->create();
        $bordereau = TypeDocument::factory()->create(['code' => 'BORDEREAU']);

        Livewire::actingAs($admin)
            ->test(SousTypesRelationManager::class, [
                'ownerRecord' => $bordereau,
                'pageClass' => EditTypeDocument::class,
            ])
            ->callTableAction('create', data: ['libelle' => 'BNSP', 'ordre' => 1, 'actif' => true])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('sous_types_documents', [
            'type_document_id' => $bordereau->id,
            'libelle' => 'BNSP',
        ]);
    }

    public function test_un_admin_peut_supprimer_un_sous_type(): void
    {
        $admin = User::factory()->admin()->create();
        $bordereau = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $bordereau->id]);

        Livewire::actingAs($admin)
            ->test(SousTypesRelationManager::class, [
                'ownerRecord' => $bordereau,
                'pageClass' => EditTypeDocument::class,
            ])
            ->callTableAction('delete', $sousType);

        $this->assertDatabaseMissing('sous_types_documents', ['id' => $sousType->id]);
    }
}
