<?php

namespace Tests\Feature;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use Database\Seeders\SousTypeDocumentSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SousTypeDocumentSeeder — sous-types recensés par le client pour les types
 * qui en ont (Attestation, Autorisation de congé, Bilan des cours en ligne,
 * Bordereau, Délibération). Liste de départ, complétable depuis
 * l'administration sans toucher au code (voir son docblock).
 */
class SousTypeDocumentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_sous_types_sont_rattaches_au_bon_type(): void
    {
        (new TypeDocumentSeeder)->run();
        (new SousTypeDocumentSeeder)->run();

        $attestation = TypeDocument::query()->where('code', 'ATTESTATION')->firstOrFail();

        $this->assertDatabaseHas('sous_types_documents', ['type_document_id' => $attestation->id, 'libelle' => 'Succès']);
        $this->assertDatabaseHas('sous_types_documents', ['type_document_id' => $attestation->id, 'libelle' => 'Correction']);
        $this->assertSame(8, SousTypeDocument::query()->where('type_document_id', $attestation->id)->count());

        $bordereau = TypeDocument::query()->where('code', 'BORDEREAU')->firstOrFail();
        $this->assertDatabaseHas('sous_types_documents', ['type_document_id' => $bordereau->id, 'libelle' => 'CNSS']);

        // RELEVE_NOTES et PV_DELIBERATION ont chacun leurs propres
        // sous-types "Licence"/"Master" — deux types distincts, aucun lien
        // entre les deux (voir le docblock de la classe).
        $releveNotes = TypeDocument::query()->where('code', 'RELEVE_NOTES')->firstOrFail();
        $this->assertDatabaseHas('sous_types_documents', ['type_document_id' => $releveNotes->id, 'libelle' => 'Licence']);
        $this->assertDatabaseHas('sous_types_documents', ['type_document_id' => $releveNotes->id, 'libelle' => 'Master']);
        $this->assertSame(2, SousTypeDocument::query()->where('type_document_id', $releveNotes->id)->count());
    }

    /** Le référentiel des types n'a pas été seedé : aucun sous-type ne doit être créé, sans erreur. */
    public function test_ignore_silencieusement_si_le_type_parent_nexiste_pas(): void
    {
        (new SousTypeDocumentSeeder)->run();

        $this->assertSame(0, SousTypeDocument::query()->count());
    }

    public function test_rejouer_le_seeder_ne_duplique_rien(): void
    {
        (new TypeDocumentSeeder)->run();
        (new SousTypeDocumentSeeder)->run();
        $premierTotal = SousTypeDocument::query()->count();

        (new SousTypeDocumentSeeder)->run();
        $secondTotal = SousTypeDocument::query()->count();

        $this->assertSame($premierTotal, $secondTotal);
    }
}
