<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sous-menu "Types de documents" du layout (resources/views/layouts/
 * gestion.blade.php, $menuTypesDocuments) — rendu bout en bout via le
 * tableau de bord (n'importe quelle page utilisant le layout ferait
 * l'affaire). La construction elle-même (regroupement "PV", seau "Autres
 * types"...) est testée précisément dans tests/Feature/MenuDocumentsTest.php
 * (App\Support\MenuDocuments) ; ces tests-ci vérifient seulement que le
 * layout affiche bien ce que cette classe lui donne.
 */
class MenuTypesDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_type_avec_sous_types_affiche_chaque_sous_type_dans_le_menu(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $attestation = TypeDocument::factory()->create(['libelle' => 'Attestation', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Inscription', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Succès', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Attestation')
            ->assertSee('Inscription')
            ->assertSee('Succès');
    }

    /** Rangé dans le seau "Autres types" (voir App\Support\MenuDocuments) plutôt qu'en lien direct — sur demande explicite du client, tant que ses vraies sous-catégories ne sont pas recensées. */
    public function test_un_type_sans_sous_type_apparait_dans_autres_types(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['libelle' => 'Courrier', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Autres types')
            ->assertSee('Courrier');
    }

    /** Délibération et PV de soutenance restent deux types distincts (champs dédiés différents) mais apparaissent regroupés sous "PV" dans le menu. */
    public function test_deliberation_et_soutenance_apparaissent_regroupes_sous_pv(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'actif' => true]);
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('PV')
            ->assertSee('Délibération')
            ->assertSee('PV de soutenance');
    }

    public function test_un_sous_type_inactif_ne_saffiche_pas(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['libelle' => 'Relevé de notes', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Licence', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Ancien cursus', 'actif' => false]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Licence')
            ->assertDontSee('Ancien cursus');
    }

    public function test_un_type_inactif_ne_saffiche_pas(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['libelle' => 'Type archivé', 'actif' => false]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('Type archivé');
    }

    /** Sans droit de voir les documents, le sous-menu ne doit apporter aucune information sur les types existants. */
    public function test_le_sous_menu_est_masque_sans_droit_de_voir_les_documents(): void
    {
        Storage::fake('sauvegardes');

        Role::query()->create([
            'slug' => 'sans_droit_documents_menu',
            'name' => 'Sans droit documents',
            'permissions' => ['documents' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droit_documents_menu']);
        TypeDocument::factory()->create(['libelle' => 'Bordereau', 'actif' => true]);

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('Bordereau');
    }
}
