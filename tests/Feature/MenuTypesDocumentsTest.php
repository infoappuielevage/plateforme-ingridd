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

    /**
     * Demande explicite du client ("n'est-il pas temps d'enlever ce qui est
     * en bas... du menu") : les types à écran dédié (voir
     * App\Support\TypesEcranDedie) n'apparaissent plus du tout dans ce
     * sous-menu "Types de documents" — ni regroupés sous "PV" (ancien
     * comportement), ni en entrée individuelle — puisqu'ils mènent chacun à
     * leur propre écran de gestion (liens fixes de la section "Documents"
     * plus haut dans la sidebar, inchangés) plutôt qu'au filtre générique
     * "Tous les documents" que ce sous-menu construit.
     */
    public function test_le_groupe_pv_et_les_types_a_ecran_dedie_napparaissent_plus_dans_ce_sous_menu(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'actif' => true]);
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'actif' => true]);
        $releveNotes = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES', 'libelle' => 'Relevés de notes (référentiel)', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $releveNotes->id, 'libelle' => 'Licence', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            // L'ancien groupe dépliable "PV" (id="sidebarMenugroupe-pv", voir
            // gestion.blade.php) ne doit plus être généré du tout.
            ->assertDontSee('id="sidebarMenugroupe-pv"', false)
            ->assertDontSee('id="sidebarMenutype-'.$releveNotes->id.'"', false)
            ->assertDontSee('Relevés de notes (référentiel)');
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
