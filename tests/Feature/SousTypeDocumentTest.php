<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Models\SousTypeDocument — comportement au niveau du modèle (relations,
 * suppression). Voir aussi tests/Feature/SousTypesRelationManagerTest.php
 * pour la gestion depuis l'administration, et DocumentResourceTest pour le
 * champ "Sous-type" sur le formulaire des documents.
 */
class SousTypeDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_sous_type_appartient_a_un_type_de_document(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'ATTESTATION']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Succès']);

        $this->assertTrue($sousType->typeDocument->is($type));
        $this->assertTrue($type->sousTypes->contains($sousType));
    }

    /**
     * `documents.sous_type_document_id` est nullable et ->nullOnDelete() :
     * supprimer un sous-type encore utilisé ne doit jamais bloquer ni
     * laisser une référence cassée, juste vider le champ sur les documents
     * concernés (contrairement aux référentiels de premier niveau, qui
     * bloquent la suppression si encore utilisés).
     */
    public function test_supprimer_un_sous_type_encore_utilise_vide_simplement_la_reference(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'ATTESTATION']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id]);
        $document = Document::factory()->create([
            'type_document_id' => $type->id,
            'sous_type_document_id' => $sousType->id,
        ]);

        $sousType->delete();

        $this->assertDatabaseMissing('sous_types_documents', ['id' => $sousType->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'sous_type_document_id' => null]);
    }

    /**
     * `type_document_id` a ->cascadeOnDelete() sur `sous_types_documents` :
     * un sous-type n'a pas de sens sans son type parent.
     */
    public function test_supprimer_definitivement_un_type_supprime_ses_sous_types(): void
    {
        $type = TypeDocument::factory()->create();
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id]);

        $type->forceDelete();

        $this->assertDatabaseMissing('sous_types_documents', ['id' => $sousType->id]);
    }
}
