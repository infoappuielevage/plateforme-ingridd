<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Models\Document;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ListDocuments::getTabs() — onglets par famille logique de type de document
 * (Tous / Académique / Diplômes-Attestations / Gouvernance / RH-Personnel /
 * Administratif), sur demande du client pour faciliter la gestion sur une
 * liste de 26+ types (voir TypeDocumentSeeder). Voir le docblock de
 * ListDocuments pour le détail du regroupement par `code`.
 */
class DocumentListTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_longlet_academique_ne_montre_que_les_types_academiques(): void
    {
        $admin = User::factory()->admin()->create();

        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);
        $typePvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $releve = Document::factory()->create(['type_document_id' => $typeReleve->id]);
        $pv = Document::factory()->create(['type_document_id' => $typePvSoutenance->id]);
        $contrat = Document::factory()->create(['type_document_id' => $typeContrat->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'academique')
            ->assertCanSeeTableRecords([$releve, $pv])
            ->assertCanNotSeeTableRecords([$contrat]);
    }

    public function test_longlet_diplomes_attestations_ne_montre_que_ces_deux_types(): void
    {
        $admin = User::factory()->admin()->create();

        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME']);
        $typeAttestation = TypeDocument::factory()->create(['code' => 'ATTESTATION']);
        $typePvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);

        $diplome = Document::factory()->create(['type_document_id' => $typeDiplome->id]);
        $attestation = Document::factory()->create(['type_document_id' => $typeAttestation->id]);
        $pv = Document::factory()->create(['type_document_id' => $typePvSoutenance->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'diplomes_attestations')
            ->assertCanSeeTableRecords([$diplome, $attestation])
            ->assertCanNotSeeTableRecords([$pv]);
    }

    public function test_longlet_administratif_ne_montre_que_les_types_generiques(): void
    {
        $admin = User::factory()->admin()->create();

        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        $contrat = Document::factory()->create(['type_document_id' => $typeContrat->id]);
        $releve = Document::factory()->create(['type_document_id' => $typeReleve->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'administratif')
            ->assertCanSeeTableRecords([$contrat])
            ->assertCanNotSeeTableRecords([$releve]);
    }

    public function test_longlet_gouvernance_ne_montre_que_les_instances_et_dossiers_institutionnels(): void
    {
        $admin = User::factory()->admin()->create();

        $typeConseil = TypeDocument::factory()->create(['code' => 'CONSEIL_SCIENTIFIQUE']);
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $conseil = Document::factory()->create(['type_document_id' => $typeConseil->id]);
        $contrat = Document::factory()->create(['type_document_id' => $typeContrat->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'gouvernance')
            ->assertCanSeeTableRecords([$conseil])
            ->assertCanNotSeeTableRecords([$contrat]);
    }

    public function test_longlet_rh_personnel_ne_montre_que_les_types_rh(): void
    {
        $admin = User::factory()->admin()->create();

        $typeConge = TypeDocument::factory()->create(['code' => 'CONGE']);
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $conge = Document::factory()->create(['type_document_id' => $typeConge->id]);
        $contrat = Document::factory()->create(['type_document_id' => $typeContrat->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'rh_personnel')
            ->assertCanSeeTableRecords([$conge])
            ->assertCanNotSeeTableRecords([$contrat]);
    }

    public function test_longlet_tous_montre_tous_les_types(): void
    {
        $admin = User::factory()->admin()->create();

        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        $contrat = Document::factory()->create(['type_document_id' => $typeContrat->id]);
        $releve = Document::factory()->create(['type_document_id' => $typeReleve->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->set('activeTab', 'tous')
            ->assertCanSeeTableRecords([$contrat, $releve]);
    }
}
