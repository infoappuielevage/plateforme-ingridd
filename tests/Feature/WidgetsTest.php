<?php

namespace Tests\Feature;

use App\Filament\Resources\TypeDocumentResource\Pages\CreateTypeDocument;
use App\Filament\Widgets\ActiviteRecenteWidget;
use App\Filament\Widgets\DiplomesRetraitChartWidget;
use App\Filament\Widgets\DocumentsParFiliereChartWidget;
use App\Filament\Widgets\DocumentsParMoisChartWidget;
use App\Filament\Widgets\DocumentsParTypeChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le tableau de bord (Filament\Pages\Dashboard, page par défaut) affiche
 * automatiquement tous les widgets du dossier app/Filament/Widgets — voir
 * AdminPanelProvider::discoverWidgets(). Ces tests couvrent le rendu de
 * chaque widget individuellement (comme ReferentielResourcesTest le fait
 * pour les écrans de liste : un test de création seul ne révèle pas une
 * relation ou une requête manquante, seul le rendu réel le fait) et le
 * contenu des chiffres clés.
 */
class WidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_widgets_du_tableau_de_bord_saffichent_sans_erreur(): void
    {
        // StatsOverviewWidget interroge le disque `sauvegardes` (carte
        // "Dernière sauvegarde", admin uniquement) — voir SauvegardesTest.php
        // pour le même besoin de Storage::fake() sur ce disque.
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(StatsOverviewWidget::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(DocumentsParMoisChartWidget::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(DocumentsParTypeChartWidget::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(DocumentsParFiliereChartWidget::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ActiviteRecenteWidget::class)->assertSuccessful();
        // Testé directement (sans référentiel "Diplôme"/"Attestation" seedé
        // ici) : doit s'afficher sans erreur même à 0/0 — voir
        // DiplomesRetraitChartWidget::getData(), qui gère un tableau d'id
        // vide sans planter.
        Livewire::actingAs($admin)->test(DiplomesRetraitChartWidget::class)->assertSuccessful();
    }

    public function test_le_widget_de_statistiques_compte_les_documents_et_la_corbeille(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        // "Documents" ne compte que les documents actifs (comme la liste,
        // qui masque la corbeille par défaut — voir TrashedFilter) : sur 3
        // créés puis 1 mis à la corbeille, il en reste 2 actifs et 1 en
        // corbeille.
        $documents = Document::factory()->count(3)->create();
        $documents->first()->delete();

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSee('Documents')
            ->assertSee('2')
            ->assertSee('En corbeille')
            ->assertSee('1');
    }

    /**
     * Un consultant sans droit de suppression ne doit pas voir la carte
     * "En corbeille" (elle n'a de sens que pour qui peut restaurer/supprimer
     * définitivement) — voir StatsOverviewWidget::getStats().
     */
    public function test_la_carte_corbeille_est_masquee_sans_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_widgets',
            'name' => 'Lecteur widgets',
            'permissions' => ['documents' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_widgets']);

        Livewire::actingAs($utilisateur)
            ->test(StatsOverviewWidget::class)
            ->assertDontSee('En corbeille');
    }

    /**
     * On vérifie directement les données produites par getData() (via
     * réflexion) plutôt que le HTML rendu : un ChartWidget dessine son
     * graphique en JS (Chart.js/Alpine) à partir d'un JSON embarqué — trop
     * fragile à cibler avec un simple assertSee, alors que les données
     * elles-mêmes sont ce qui compte réellement ici.
     */
    public function test_le_graphique_par_filiere_reflete_le_nombre_de_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create(['libelle' => 'Informatique']);
        Document::factory()->count(2)->create(['filiere_id' => $filiere->id]);

        $this->actingAs($admin);

        $widget = new DocumentsParFiliereChartWidget;
        $donnees = (function () {
            return $this->getData();
        })->call($widget);

        $index = array_search('Informatique', $donnees['labels'], true);

        $this->assertNotFalse($index);
        $this->assertSame(2, $donnees['datasets'][0]['data'][$index]);
    }

    /**
     * Répond au besoin de repérer en un coup d'œil les diplômes/attestations
     * pas encore récupérés (voir StatsOverviewWidget::getStats() et le
     * bouton "Marquer comme retiré" sur DocumentResource::table()).
     */
    public function test_le_widget_de_statistiques_compte_les_diplomes_non_retires(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $typeAttestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);

        Document::factory()->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->create(['type_document_id' => $typeAttestation->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_RETIRE]);

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSee('Diplômes/attestations non retirés')
            ->assertSee('2');
    }

    /**
     * Sans référentiel "Diplôme"/"Attestation" (types renommés/supprimés),
     * ni la carte ni le graphique dédiés ne doivent apparaître — plutôt
     * qu'un chiffre à 0 ou un graphique vide sans queue ni tête.
     */
    public function test_les_widgets_de_retrait_sont_masques_sans_type_diplome_ou_attestation(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertDontSee('Diplômes/attestations non retirés');

        $this->assertFalse(DiplomesRetraitChartWidget::canView());
    }

    public function test_le_graphique_des_retraits_reflete_les_statuts(): void
    {
        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);

        Document::factory()->count(3)->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->count(2)->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_RETIRE]);

        $this->actingAs($admin);

        $widget = new DiplomesRetraitChartWidget;
        $donnees = (function () {
            return $this->getData();
        })->call($widget);

        $this->assertSame(['Non retiré', 'Retiré'], $donnees['labels']);
        $this->assertSame([3, 2], $donnees['datasets'][0]['data']);
    }

    public function test_lactivite_recente_affiche_les_dernieres_actions(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateTypeDocument::class)
            ->fillForm(['code' => 'WDG', 'libelle' => 'Type widget', 'ordre' => 1, 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(ActiviteRecenteWidget::class)
            ->assertSee('Création de TypeDocument');
    }
}
