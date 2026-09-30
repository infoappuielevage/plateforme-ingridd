<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Filiere;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\DashboardController — remplace App\Filament\Widgets\
 * StatsOverviewWidget (cartes de chiffres clés) et les 4 *ChartWidget
 * (graphiques par mois/type/filière, retraits des diplômes/attestations),
 * plus de Filament. Porte à l'identique, en vraies requêtes HTTP plutôt
 * qu'en Livewire::test(), les garanties qu'avait tests/Feature/WidgetsTest.php.
 *
 * Les données des graphiques sont vérifiées via assertViewHas() plutôt
 * qu'en cherchant du texte dans le HTML rendu : elles sont dessinées côté
 * client par Chart.js à partir du JSON embarqué (voir dashboard.blade.php),
 * bien plus fragile à cibler avec un simple assertSee.
 *
 * Non repris ici (connu, accepté) : l'ancien widget "Activité récente"
 * (aperçu de l'historique sur le tableau de bord) — remplacé par la liste
 * "Documents récemment ajoutés" déjà présente ; l'historique complet reste
 * consultable depuis Administration > Historique (voir AuditLogController).
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tableau_de_bord_saffiche_sans_erreur(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.dashboard'))->assertOk();
    }

    public function test_le_widget_de_statistiques_compte_les_documents_et_la_corbeille(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        // "Documents" ne compte que les documents actifs : sur 3 créés puis
        // 1 mis à la corbeille, il en reste 2 actifs et 1 en corbeille.
        $documents = Document::factory()->count(3)->create();
        $documents->first()->delete();

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Documents')
            ->assertSee('2')
            ->assertSee('En corbeille')
            ->assertSee('1');
    }

    /**
     * Carte "Espace de stockage" — demande explicite du client, qui voulait
     * un vrai indicateur d'espace disque restant, pas seulement le volume
     * déjà archivé (déjà affiché avant, voir App\Http\Controllers\
     * DashboardController::espaceDisque()). Pas de valeur exacte assertée
     * ici : l'espace disque réel dépend de la machine qui exécute les tests,
     * seule la PRÉSENCE d'un indicateur cohérent est vérifiée.
     */
    public function test_la_carte_espace_de_stockage_affiche_un_indicateur_disque(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertViewHas('cartes', function (array $cartes) {
                $carte = collect($cartes)->firstWhere('label', 'Espace de stockage');

                $this->assertNotNull($carte);
                $this->assertArrayHasKey('alerte', $carte);
                $this->assertIsBool($carte['alerte']);
                $this->assertIsString($carte['description']);

                return true;
            });
    }

    /** Un consultant sans droit de suppression ne doit pas voir la carte "En corbeille" — elle n'a de sens que pour qui peut restaurer/supprimer définitivement. */
    public function test_la_carte_corbeille_est_masquee_sans_droit_de_suppression(): void
    {
        Storage::fake('sauvegardes');

        Role::query()->create([
            'slug' => 'lecteur_tableau_de_bord',
            'name' => 'Lecteur tableau de bord',
            'permissions' => ['documents' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_tableau_de_bord']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('En corbeille');
    }

    public function test_le_widget_de_statistiques_compte_les_diplomes_non_retires(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $typeAttestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);

        Document::factory()->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->create(['type_document_id' => $typeAttestation->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_RETIRE]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Diplômes/attestations non retirés')
            ->assertSee('2');
    }

    /** Sans référentiel "Diplôme"/"Attestation" configuré, ni la carte ni le graphique dédiés ne doivent apparaître — plutôt qu'un chiffre/graphique à 0 sans queue ni tête. */
    public function test_les_widgets_de_retrait_sont_masques_sans_type_diplome_ou_attestation(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertDontSee('Diplômes/attestations non retirés')
            ->assertViewHas('graphiques', function (array $graphiques) {
                $this->assertArrayNotHasKey('diplomesRetrait', $graphiques);

                return true;
            });
    }

    public function test_le_graphique_par_filiere_reflete_le_nombre_de_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create(['libelle' => 'Informatique']);
        Document::factory()->count(2)->create(['filiere_id' => $filiere->id]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertViewHas('graphiques', function (array $graphiques) {
                $index = array_search('Informatique', $graphiques['parFiliere']['labels'], true);
                $this->assertNotFalse($index);
                $this->assertSame(2, $graphiques['parFiliere']['donnees'][$index]);

                return true;
            });
    }

    public function test_le_graphique_par_type_reflete_le_nombre_de_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['libelle' => 'Contrats']);
        Document::factory()->count(3)->create(['type_document_id' => $type->id]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertViewHas('graphiques', function (array $graphiques) {
                $index = array_search('Contrats', $graphiques['parType']['labels'], true);
                $this->assertNotFalse($index);
                $this->assertSame(3, $graphiques['parType']['donnees'][$index]);

                return true;
            });
    }

    public function test_le_graphique_par_mois_couvre_les_douze_derniers_mois(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->create();

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertViewHas('graphiques', function (array $graphiques) {
                $this->assertCount(12, $graphiques['parMois']['labels']);
                $this->assertCount(12, $graphiques['parMois']['donnees']);
                // Le document créé "maintenant" (factory, pas de created_at
                // explicite) doit être compté dans le dernier mois de la série.
                $this->assertGreaterThanOrEqual(1, end($graphiques['parMois']['donnees']));

                return true;
            });
    }

    public function test_le_graphique_des_retraits_reflete_les_statuts(): void
    {
        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);

        Document::factory()->count(3)->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE]);
        Document::factory()->count(2)->create(['type_document_id' => $typeDiplome->id, 'statut_retrait' => Document::STATUT_RETRAIT_RETIRE]);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertViewHas('graphiques', function (array $graphiques) {
                $this->assertSame(['Non retiré', 'Retiré'], $graphiques['diplomesRetrait']['labels']);
                $this->assertSame([3, 2], $graphiques['diplomesRetrait']['donnees']);

                return true;
            });
    }

    public function test_les_documents_recents_saffichent_sur_le_tableau_de_bord(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->create(['nom_original' => 'contrat-recent.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Documents récemment ajoutés')
            ->assertSee('contrat-recent.pdf');
    }
}
