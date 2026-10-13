<?php

namespace Tests\Feature;

use Database\Seeders\SauvegardeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SauvegardeDemoSeeder — fichiers de sauvegarde factices, demandés par le
 * client pour que l'écran Administration > Sauvegardes ne soit pas vide à
 * l'installation (voir SauvegardeController::lister(), qui liste les
 * fichiers ".zip" du disque `sauvegardes`).
 */
class SauvegardeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_quatre_fichiers_zip(): void
    {
        Storage::fake('sauvegardes');

        (new SauvegardeDemoSeeder)->run();

        $fichiers = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'));

        $this->assertCount(4, $fichiers);
    }

    public function test_un_admin_voit_les_sauvegardes_factices_dans_lecran(): void
    {
        Storage::fake('sauvegardes');
        (new SauvegardeDemoSeeder)->run();

        $admin = \App\Models\User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.sauvegardes.index'));

        $reponse->assertOk();
        $reponse->assertSee('.zip');
    }
}
