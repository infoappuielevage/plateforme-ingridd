<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecretariatController — remplace App\Filament\Pages\DocumentsParType sur
 * demande du client (plus de Filament). Une page dédiée par type de document
 * "épinglé" (voir App\Models\TypeDocument::$epingle_secretariat), qui
 * réutilise le formulaire/les actions de DocumentController — voir le
 * docblock du contrôleur.
 *
 * Contrairement à tests/Feature/DocumentsParTypeTest.php (Livewire), ces
 * tests passent par de VRAIES requêtes HTTP (`$this->get(route(...))`) :
 * SecretariatController est un contrôleur Laravel classique, pas un
 * composant Livewire.
 */
class SecretariatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_dun_type_ne_montre_que_les_documents_de_ce_type(): void
    {
        $admin = User::factory()->admin()->create();
        $contrat = TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats']);
        $courrier = TypeDocument::factory()->create(['code' => 'COURRIER', 'libelle' => 'Courrier']);

        $documentContrat = Document::factory()->create(['type_document_id' => $contrat->id, 'nom_original' => 'contrat-visible.pdf']);
        $documentCourrier = Document::factory()->create(['type_document_id' => $courrier->id, 'nom_original' => 'courrier-absent.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'CONTRAT']))
            ->assertOk()
            ->assertSee('contrat-visible.pdf')
            ->assertDontSee('courrier-absent.pdf');
    }

    /**
     * Un type avec des sous-types actifs ET `champs_diplome_actifs` (comme
     * "Attestation") explose en une page par sous-type — même logique que
     * l'ancien menu Filament, désormais pilotée par ces colonnes plutôt que
     * par le code "ATTESTATION" en dur.
     */
    public function test_la_page_dun_sous_type_ne_montre_que_ce_sous_type(): void
    {
        $admin = User::factory()->admin()->create();
        $attestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);
        $succes = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Succès', 'actif' => true]);
        $vacation = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Vacation', 'actif' => true]);

        $documentSucces = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $succes->id,
            'nom_original' => 'succes-visible.pdf',
        ]);
        $documentVacation = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $vacation->id,
            'nom_original' => 'vacation-absent.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'ATTESTATION', 'sousType' => $succes->id]))
            ->assertOk()
            ->assertSee('succes-visible.pdf')
            ->assertDontSee('vacation-absent.pdf');
    }

    public function test_un_code_de_type_inconnu_renvoie_une_erreur_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'CODE_INEXISTANT']))
            ->assertNotFound();
    }

    public function test_un_role_sans_droit_de_vue_sur_les_documents_ne_peut_pas_acceder_a_la_page(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_secretariat',
            'name' => 'Sans droits secrétariat',
            'permissions' => ['documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_secretariat']);
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.secretariat', ['code' => $type->code]))
            ->assertForbidden();
    }

    public function test_le_mode_corbeille_ne_montre_que_les_documents_supprimes(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'champs_soutenance_actifs' => true]);

        $actif = Document::factory()->create(['type_document_id' => $type->id, 'nom_original' => 'actif.pdf']);
        $corbeille = Document::factory()->create(['type_document_id' => $type->id, 'nom_original' => 'corbeille.pdf']);
        $corbeille->delete();

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'PV_SOUTENANCE', 'corbeille' => 1]))
            ->assertOk()
            ->assertSee('corbeille.pdf')
            ->assertDontSee('actif.pdf');
    }

    /**
     * Le bouton "Ajouter un document" d'une page Secrétariat renvoie vers le
     * formulaire habituel de DocumentController avec le type déjà
     * présélectionné — voir DocumentController::create()/donneesFormulaire().
     */
    public function test_le_formulaire_dajout_presectionne_le_type_venant_du_secretariat(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.documents.create', ['type_document_id' => $type->id]))
            ->assertOk()
            ->assertViewHas('typePreselectionneId', $type->id);
    }

    /**
     * Le menu "Secrétariat" (voir resources/views/layouts/gestion.blade.php)
     * ne liste que les types épinglés — un type "générique" non épinglé ne
     * doit pas y apparaître, même s'il existe et a des documents.
     */
    public function test_le_menu_secretariat_ne_liste_que_les_types_epingles(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'epingle_secretariat' => true]);
        TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats Génériques', 'epingle_secretariat' => false]);

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertSee('Délibération')
            ->assertDontSee('Contrats Génériques');
    }
}
