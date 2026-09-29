<?php

namespace Tests\Feature;

use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\ParametresController — remplace App\Filament\Pages\
 * Parametres sur demande du client (plus de Filament). Voir tests/Feature/
 * ParametresTest.php pour l'équivalent Filament (encore en place le temps de
 * la transition) : mêmes garanties vérifiées ici côté HTTP classique.
 */
class ParametresControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_modifier_les_parametres(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update'), [
                'nom_application' => 'Archives Test',
                'couleur_principale' => '#22c55e',
                'taille_max_document_mo' => 50,
                'taille_max_avatar_mo' => 5,
                'retention_audit_jours' => 365,
                'retention_sauvegardes_nombre' => 5,
                'frequence_sauvegarde_auto' => 'hebdomadaire',
                'mysqldump_chemin' => 'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe',
                'types_fichiers_autorises' => ['documents', 'images'],
            ])
            ->assertRedirect(route('gestion.parametres.index'));

        $parametres = Parametre::actuel();

        $this->assertSame('Archives Test', $parametres->nom_application);
        $this->assertSame(50, $parametres->taille_max_document_mo);
        $this->assertSame(365, $parametres->retention_audit_jours);
        $this->assertSame('hebdomadaire', $parametres->frequence_sauvegarde_auto);
        $this->assertSame(['documents', 'images'], $parametres->types_fichiers_autorises);
    }

    public function test_un_non_admin_ne_peut_pas_acceder_aux_parametres(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.parametres.index'))
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->put(route('gestion.parametres.update'), ['nom_application' => 'Tentative'])
            ->assertForbidden();
    }

    /**
     * Même piège que côté Filament (voir tests/Feature/ParametresTest.php) :
     * une ligne "parametres" créée avant la colonne types_fichiers_autorises
     * doit précocher toutes les familles, pas afficher un formulaire vide.
     */
    public function test_le_formulaire_precoche_toutes_les_familles_si_le_reglage_nest_pas_encore_enregistre(): void
    {
        $admin = User::factory()->admin()->create();
        Parametre::actuel();
        Parametre::query()->update(['types_fichiers_autorises' => null]);

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index'));

        $reponse->assertOk();
        foreach (TypesFichiers::toutesLesCles() as $cle) {
            $reponse->assertSee('id="famille_'.$cle.'"', false);
        }
    }

    public function test_le_logo_peut_etre_televerse_et_remplace_lancien(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Parametre::actuel()->fill(['logo' => 'logo/ancien.png'])->save();
        Storage::disk('public')->put('logo/ancien.png', 'contenu');

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update'), [
                'nom_application' => 'INGRIDD',
                'couleur_principale' => '#6366f1',
                'taille_max_document_mo' => 20,
                'taille_max_avatar_mo' => 2,
                'retention_audit_jours' => 365,
                'retention_sauvegardes_nombre' => 5,
                'frequence_sauvegarde_auto' => 'quotidienne',
                'types_fichiers_autorises' => ['documents'],
                'logo' => UploadedFile::fake()->image('nouveau-logo.png'),
            ])
            ->assertRedirect(route('gestion.parametres.index'));

        $parametres = Parametre::actuel();
        $this->assertNotSame('logo/ancien.png', $parametres->logo);
        Storage::disk('public')->assertExists($parametres->logo);
        Storage::disk('public')->assertMissing('logo/ancien.png');
    }

    /** Au moins une famille de fichiers doit rester cochée — sans quoi plus aucun document ne pourrait jamais être ajouté. */
    public function test_au_moins_une_famille_de_fichiers_doit_etre_cochee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update'), [
                'nom_application' => 'INGRIDD',
                'couleur_principale' => '#6366f1',
                'taille_max_document_mo' => 20,
                'taille_max_avatar_mo' => 2,
                'retention_audit_jours' => 365,
                'retention_sauvegardes_nombre' => 5,
                'frequence_sauvegarde_auto' => 'quotidienne',
                'types_fichiers_autorises' => [],
            ])
            ->assertSessionHasErrors('types_fichiers_autorises');
    }
}
