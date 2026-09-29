<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Filament\Resources\TypeDocumentResource\Pages\ListTypeDocuments;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vérifie que App\Support\ResourceAccess bloque bien l'accès quand la
 * permission n'est pas accordée, et l'autorise quand elle l'est — le même
 * principe qu'ingridd-platform, appliqué au périmètre "documents".
 */
class AccesRessourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_toujours_a_la_liste_des_documents(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->assertSuccessful();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_les_documents(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits',
            'name' => 'Sans droits',
            'permissions' => [
                'documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ]);

        $utilisateur = User::factory()->create(['role' => 'sans_droits']);

        $this->assertFalse(\App\Filament\Resources\DocumentResource::canViewAny());

        $this->actingAs($utilisateur);
        $this->assertFalse(\App\Filament\Resources\DocumentResource::canViewAny());
    }

    public function test_un_role_avec_permission_de_vue_peut_voir_les_types_de_document(): void
    {
        Role::query()->create([
            'slug' => 'lecteur',
            'name' => 'Lecteur',
            'permissions' => [
                'types_documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ]);

        $utilisateur = User::factory()->create(['role' => 'lecteur']);

        Livewire::actingAs($utilisateur)
            ->test(ListTypeDocuments::class)
            ->assertSuccessful();
    }
}
