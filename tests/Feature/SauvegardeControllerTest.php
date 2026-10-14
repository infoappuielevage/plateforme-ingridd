<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    /**
     * La commande `ingridd:sauvegarde` explique déjà précisément la cause
     * d'un échec (mysqldump introuvable, extension "zip" absente...) via ses
     * propres $this->error()/$this->warn() — cette sortie doit être relayée
     * à l'écran plutôt que remplacée par un message générique qui ne
     * permettrait pas de diagnostiquer quoi que ce soit depuis le
     * navigateur (voir SauvegardeController::store()).
     */
    public function test_le_message_dechec_de_sauvegarde_reprend_la_sortie_de_la_commande(): void
    {
        $admin = User::factory()->admin()->create();

        Artisan::shouldReceive('call')
            ->once()
            ->with('ingridd:sauvegarde')
            ->andReturn(1);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Impossible de lancer "mysqldump" : la commande n\'existe pas.');

        $this->actingAs($admin)
            ->post(route('gestion.sauvegardes.store'))
            ->assertRedirect()
            ->assertSessionHasErrors('sauvegarde');

        $this->assertStringContainsString('mysqldump', session('errors')->first('sauvegarde'));
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

    /**
     * Demande explicite du client : tous les tableaux de l'espace de gestion
     * limités à 8 lignes par page, le reste accessible par numéro de page.
     * Cet écran n'a pas de modèle Eloquent (simples fichiers sur disque,
     * voir le docblock de la classe) : SauvegardeController::paginerListe()
     * reproduit ->paginate(8) "à la main" via LengthAwarePaginator.
     */
    public function test_la_liste_des_sauvegardes_est_paginee_a_huit_par_page(): void
    {
        Storage::fake('sauvegardes');
        foreach (range(1, 10) as $i) {
            Storage::disk('sauvegardes')->put("sauvegarde-demo-{$i}.zip", 'contenu');
        }
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.sauvegardes.index'));

        $reponse->assertOk();
        $reponse->assertViewHas('sauvegardes', fn ($sauvegardes) => $sauvegardes->perPage() === 8 && $sauvegardes->total() === 10);
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

    /** Voir le docblock de la classe : second type de sauvegarde, demande explicite du client ("une sauvegarde de tout le projet"). */
    public function test_un_admin_peut_creer_une_sauvegarde_du_projet_depuis_lecran(): void
    {
        Storage::fake('sauvegardes');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sauvegardes.store-projet'))
            ->assertRedirect();

        $archives = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $f) => str_starts_with($f, 'projet-') && str_ends_with($f, '.zip'));

        $this->assertCount(1, $archives);
    }

    public function test_un_non_admin_ne_peut_pas_creer_une_sauvegarde_du_projet(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.sauvegardes.store-projet'))
            ->assertForbidden();
    }

    /** La liste distingue les deux types par préfixe de nom (voir SauvegardeController::lister()). */
    public function test_la_liste_distingue_les_sauvegardes_du_projet_des_sauvegardes_de_donnees(): void
    {
        Storage::fake('sauvegardes');
        Storage::disk('sauvegardes')->put('sauvegarde-2026-01-01-000000-abcdef.zip', 'contenu');
        Storage::disk('sauvegardes')->put('projet-2026-01-01-000000-abcdef.zip', 'contenu');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.sauvegardes.index'))
            ->assertOk()
            ->assertViewHas('sauvegardes', function ($sauvegardes) {
                $types = collect($sauvegardes->items())->pluck('type', 'nom');

                return $types['sauvegarde-2026-01-01-000000-abcdef.zip'] === 'donnees'
                    && $types['projet-2026-01-01-000000-abcdef.zip'] === 'projet';
            });
    }
}
