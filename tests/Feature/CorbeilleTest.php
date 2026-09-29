<?php

namespace Tests\Feature;

use App\Filament\Resources\CorbeilleResource;
use App\Filament\Resources\CorbeilleResource\Pages\ListCorbeille;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Accès direct à la corbeille des documents depuis le menu (groupe de
 * navigation "Corbeille", séparé de "Documents") — sur demande du client,
 * avec sélection multiple, suppression multiple et "Vider la corbeille"
 * (tout supprimer définitivement en un clic). Voir App\Filament\Resources\CorbeilleResource.
 */
class CorbeilleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_corbeille_naffiche_que_les_documents_supprimes(): void
    {
        $admin = User::factory()->admin()->create();

        $actif = Document::factory()->create();
        $corbeille = Document::factory()->create();
        $corbeille->delete();

        Livewire::actingAs($admin)
            ->test(ListCorbeille::class)
            ->assertCanSeeTableRecords([$corbeille])
            ->assertCanNotSeeTableRecords([$actif]);
    }

    public function test_un_admin_peut_restaurer_un_document_depuis_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();

        $document = Document::factory()->create();
        $document->delete();

        Livewire::actingAs($admin)
            ->test(ListCorbeille::class)
            ->callTableAction('restore', $document);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_un_admin_peut_supprimer_definitivement_un_document_depuis_la_corbeille(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);
        $document->delete();

        Livewire::actingAs($admin)
            ->test(ListCorbeille::class)
            ->callTableAction('forceDelete', $document);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertMissing('uploads/test.pdf');
    }

    /**
     * "Vider la corbeille" (bouton en haut de l'écran, voir
     * ListCorbeille::getHeaderActions()) supprime définitivement TOUS les
     * documents corbeillés en un clic, sans avoir à les sélectionner un par
     * un — contrairement à la sélection multiple + suppression groupée
     * (déjà possible via les actions groupées de la table), qui reste aussi
     * disponible pour ne vider qu'une partie de la corbeille.
     */
    public function test_le_bouton_vider_la_corbeille_supprime_tout_definitivement(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/a.pdf', 'a');
        Storage::disk('documents')->put('uploads/b.pdf', 'b');

        $admin = User::factory()->admin()->create();

        $document1 = Document::factory()->create(['chemin' => 'uploads/a.pdf']);
        $document2 = Document::factory()->create(['chemin' => 'uploads/b.pdf']);
        $documentActif = Document::factory()->create();

        $document1->delete();
        $document2->delete();

        Livewire::actingAs($admin)
            ->test(ListCorbeille::class)
            ->callAction('vider_la_corbeille');

        $this->assertDatabaseMissing('documents', ['id' => $document1->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document2->id]);
        Storage::disk('documents')->assertMissing('uploads/a.pdf');
        Storage::disk('documents')->assertMissing('uploads/b.pdf');

        // Un document actif (pas corbeillé) n'est jamais concerné.
        $this->assertDatabaseHas('documents', ['id' => $documentActif->id]);
    }

    public function test_le_bouton_vider_la_corbeille_est_masque_quand_la_corbeille_est_vide(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListCorbeille::class)
            ->assertActionHidden('vider_la_corbeille');
    }

    /**
     * Réservée à qui peut supprimer des documents (même droit que les
     * actions Restaurer/Supprimer définitivement sur DocumentResource),
     * pas seulement à qui peut les voir — voir CorbeilleResource::canViewAny().
     */
    public function test_la_corbeille_nest_accessible_qua_qui_peut_supprimer_des_documents(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_sans_suppression',
            'name' => 'Lecteur sans suppression',
            'permissions' => [
                'documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ]);

        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_suppression']);

        $this->actingAs($utilisateur);
        $this->assertFalse(CorbeilleResource::canViewAny());

        Role::query()->create([
            'slug' => 'suppresseur',
            'name' => 'Suppresseur',
            'permissions' => [
                'documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => true],
            ],
        ]);

        $autreUtilisateur = User::factory()->create(['role' => 'suppresseur']);

        $this->actingAs($autreUtilisateur);
        $this->assertTrue(CorbeilleResource::canViewAny());
    }
}
