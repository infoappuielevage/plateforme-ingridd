<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\TypeContrat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Console\Commands\PurgerCorbeilles — purge automatique de la corbeille
 * (voir Administration > Paramètres > Corbeilles et le docblock de la
 * commande testée). Chaque ressource a déjà son propre test "Vider la
 * corbeille" qui couvre sa cascade/son garde-fou propre (ex.
 * tests/Feature/ReunionControllerTest.php, TypeContratControllerTest.php) —
 * purgerAnciennes() reprend exactement la même logique, simplement filtrée
 * par ancienneté. Ce fichier couvre donc seulement ce qui est propre à CETTE
 * commande : le réglage désactivé par défaut, le filtre par ancienneté
 * (deleted_at), et qu'une cascade/un garde-fou représentatifs de chaque
 * famille (simple et "encore utilisé") sont bien appliqués à travers elle.
 */
class PurgerCorbeillesTest extends TestCase
{
    use RefreshDatabase;

    /** Réglage par défaut (purge_corbeille_jours NULL) : la corbeille n'est jamais purgée tant que l'administrateur ne l'a pas explicitement activée. */
    public function test_ne_fait_rien_si_la_purge_automatique_est_desactivee(): void
    {
        $reunion = Reunion::factory()->create();
        $reunion->delete();
        $reunion->forceFill(['deleted_at' => now()->subYears(5)])->saveQuietly();

        $this->artisan('ingridd:purger-corbeilles')->assertSuccessful();

        $this->assertDatabaseHas('reunions', ['id' => $reunion->id]);
    }

    /**
     * Une fois le réglage activé : un élément à la corbeille depuis plus
     * longtemps que le seuil est supprimé définitivement (avec la même
     * cascade que "Vider la corbeille" — le document attaché est
     * soft-supprimé, jamais orphelin), un élément plus récent est épargné,
     * et chaque administrateur reçoit une notification.
     */
    public function test_purge_les_elements_plus_anciens_que_le_seuil_avec_cascade(): void
    {
        Storage::fake('documents');
        Parametre::actuel()->fill(['purge_corbeille_jours' => 30])->save();

        $reunionAncienne = Reunion::factory()->create();
        $document = Document::factory()->create();
        ReunionFichier::factory()->create([
            'reunion_id' => $reunionAncienne->id,
            'document_id' => $document->id,
        ]);
        $reunionAncienne->delete();
        $reunionAncienne->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();

        $reunionRecente = Reunion::factory()->create();
        $reunionRecente->delete();
        $reunionRecente->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

        $admin = User::factory()->admin()->create();

        $this->artisan('ingridd:purger-corbeilles')->assertSuccessful();

        $this->assertDatabaseMissing('reunions', ['id' => $reunionAncienne->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
        $this->assertDatabaseHas('reunions', ['id' => $reunionRecente->id, 'deleted_at' => $reunionRecente->deleted_at]);
        $this->assertSame(1, $admin->notifications()->count());
    }

    /** Même garde-fou que TypeContratControllerTest::test_vider_la_corbeille_ignore_les_categories_encore_utilisees() : jamais purgée, même après le seuil, si encore référencée. */
    public function test_ignore_les_elements_encore_utilises_meme_apres_le_seuil(): void
    {
        Parametre::actuel()->fill(['purge_corbeille_jours' => 30])->save();

        $typePurgeable = TypeContrat::factory()->create();
        $typePurgeable->delete();
        $typePurgeable->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();

        $typeEncoreUtilise = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeEncoreUtilise->id]);
        $contrat->delete();
        $typeEncoreUtilise->delete();
        $typeEncoreUtilise->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();

        $this->artisan('ingridd:purger-corbeilles')->assertSuccessful();

        $this->assertDatabaseMissing('types_contrats', ['id' => $typePurgeable->id]);
        $this->assertDatabaseHas('types_contrats', ['id' => $typeEncoreUtilise->id]);
    }
}
