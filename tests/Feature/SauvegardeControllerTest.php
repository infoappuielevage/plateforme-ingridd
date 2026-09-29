<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\SauvegardeController — remplace App\Filament\Pages\
 * Sauvegardes sur demande du client (plus de Filament). La commande
 * `ingridd:sauvegarde` elle-même est déjà couverte par tests/Feature/
 * SauvegardesTest.php : ces tests-ci couvrent seulement l'écran (liste,
 * bouton "créer", suppression, contrôle d'accès admin).
 */
class SauvegardeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_creer_une_sauvegarde_depuis_lecran(): void
    {
        Storage::fake('sauvegardes');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sauvegardes.store'))
            ->assertRedirect();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        $this->assertCount(1, $archives);
    }

    public function test_un_non_admin_ne_peut_pas_acceder_aux_sauvegardes(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.sauvegardes.index'))
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.sauvegardes.store'))
            ->assertForbidden();
    }

    public function test_la_liste_affiche_les_fichiers_zip_du_disque_sauvegardes(): void
    {
        Storage::fake('sauvegardes');
        Storage::disk('sauvegardes')->put('ingridd_2026-01-01.zip', 'contenu');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.sauvegardes.index'))
            ->assertOk()
            ->assertSee('ingridd_2026-01-01.zip');
    }

    public function test_un_admin_peut_supprimer_une_sauvegarde(): void
    {
        Storage::fake('sauvegardes');
        Storage::disk('sauvegardes')->put('a_supprimer.zip', 'contenu');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.sauvegardes.destroy', 'a_supprimer.zip'))
            ->assertRedirect();

        Storage::disk('sauvegardes')->assertMissing('a_supprimer.zip');
    }

    /** Même garde que la route de téléchargement (routes/web.php) : un nom contenant un chemin ne doit jamais être accepté. */
    public function test_un_nom_de_fichier_contenant_un_chemin_est_refuse(): void
    {
        Storage::fake('sauvegardes');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.sauvegardes.destroy', ['nom' => '..\\.env']))
            ->assertForbidden();
    }
}
