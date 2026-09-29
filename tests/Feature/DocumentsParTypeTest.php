<?php

namespace Tests\Feature;

use App\Filament\Pages\DocumentsParType;
use App\Models\Document;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menu "Secrétariat" (voir AdminPanelProvider::elementsNavigationSecretariat())
 * et App\Filament\Pages\DocumentsParType : une page dédiée par type de
 * document (et par sous-type pour "Attestation"), sur demande explicite du
 * client — "que chaque type de document ait son tableau dissocié des
 * autres". Le formulaire d'ajout/modification reste partagé avec
 * DocumentResource (voir le docblock de la page).
 */
class DocumentsParTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_dun_type_ne_montre_que_les_documents_de_ce_type(): void
    {
        $admin = User::factory()->admin()->create();
        $contrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $courrier = TypeDocument::factory()->create(['code' => 'COURRIER']);

        $documentContrat = Document::factory()->create(['type_document_id' => $contrat->id]);
        $documentCourrier = Document::factory()->create(['type_document_id' => $courrier->id]);

        Livewire::actingAs($admin)
            ->test(DocumentsParType::class, ['code' => 'CONTRAT'])
            ->assertCanSeeTableRecords([$documentContrat])
            ->assertCanNotSeeTableRecords([$documentCourrier]);
    }

    /**
     * "Attestation" est le seul type découpé en une page par sous-type (sur
     * demande explicite du client) : la page de "Succès" ne doit montrer que
     * les attestations de sous-type "Succès", pas celles de "Vacation" ni
     * les documents d'un autre type.
     */
    public function test_la_page_dun_sous_type_dattestation_ne_montre_que_ce_sous_type(): void
    {
        $admin = User::factory()->admin()->create();
        $attestation = TypeDocument::factory()->create(['code' => 'ATTESTATION']);
        $succes = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Succès']);
        $vacation = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Vacation']);
        $autreType = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $documentSucces = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $succes->id,
        ]);
        $documentVacation = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $vacation->id,
        ]);
        $documentAutreType = Document::factory()->create(['type_document_id' => $autreType->id]);

        Livewire::actingAs($admin)
            ->test(DocumentsParType::class, ['code' => 'ATTESTATION', 'sousType' => $succes->id])
            ->assertCanSeeTableRecords([$documentSucces])
            ->assertCanNotSeeTableRecords([$documentVacation, $documentAutreType]);
    }

    /**
     * Un type désactivé (ex. "Diplôme", retiré du référentiel — voir
     * TypeDocumentSeeder) garde quand même sa page dédiée, sur demande
     * explicite du client : les documents déjà classés dedans doivent rester
     * faciles à retrouver, même si on ne peut plus en créer de nouveaux.
     */
    public function test_la_page_dun_type_desactive_reste_accessible(): void
    {
        $admin = User::factory()->admin()->create();
        $diplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'actif' => false]);
        $document = Document::factory()->create(['type_document_id' => $diplome->id]);

        Livewire::actingAs($admin)
            ->test(DocumentsParType::class, ['code' => 'DIPLOME'])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$document]);
    }

    /**
     * Régression : l'URL générée par le menu "Secrétariat" pour un type SANS
     * sous-type (ex. "Relevé de notes") doit rester accessible par une VRAIE
     * requête HTTP, pas seulement via Livewire::test() (qui appelle mount()
     * directement, sans passer par le routeur de Laravel). C'est précisément
     * ce chemin — résolution de route + liaison implicite des paramètres —
     * qui déclenchait "Cannot use positional argument after named argument"
     * quand `{sousType}` était un segment optionnel absent de l'URL ; voir le
     * docblock de DocumentsParType::getSlug().
     */
    public function test_la_page_dun_type_sans_sous_type_est_accessible_par_une_vraie_requete_http(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $this->actingAs($admin)
            ->get(DocumentsParType::getUrl(['code' => 'CONTRAT', 'sousType' => DocumentsParType::AUCUN_SOUS_TYPE]))
            ->assertOk();
    }

    public function test_un_code_de_type_inconnu_renvoie_une_erreur_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(DocumentsParType::class, ['code' => 'CODE_INEXISTANT']);
    }

    /**
     * abort_unless(..., 403) dans mount() ne remonte PAS comme une exception
     * PHP brute jusqu'au test : Livewire intercepte les erreurs HTTP de ce
     * type pendant Livewire::test() et les transforme en statut consultable
     * (->assertForbidden()), contrairement à une exception "normale" comme
     * ModelNotFoundException (voir le test 404 ci-dessus, qui lui utilise
     * expectException() — cette différence est un comportement de Livewire,
     * pas une incohérence entre les deux tests).
     */
    public function test_un_role_sans_droit_de_vue_sur_les_documents_ne_peut_pas_acceder_a_la_page(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_secretariat',
            'name' => 'Sans droits secrétariat',
            'permissions' => ['documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_secretariat']);
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        Livewire::actingAs($utilisateur)
            ->test(DocumentsParType::class, ['code' => $type->code])
            ->assertForbidden();
    }
}
