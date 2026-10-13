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

    /**
     * Faille de sécurité corrigée sur demande explicite du client ("revoir
     * la sécurité pour éviter l'accessibilité à des pages confidentielles
     * sans autorisation") : rien ici ne vérifiait auparavant le droit
     * "documents" -> "Voir" — un rôle n'en ayant AUCUN (ex. les rôles
     * "Direction"/"Secrétariat"/... créés sans permission par
     * database/seeders/RoleSeeder.php, en attendant d'être configurés)
     * voyait quand même un nom de fichier réel dans "Documents récemment
     * ajoutés" ainsi que la carte "Documents" rien qu'en arrivant sur son
     * tableau de bord.
     */
    public function test_les_documents_recents_et_la_carte_documents_sont_masques_sans_droit_de_vue(): void
    {
        Storage::fake('sauvegardes');

        Role::query()->create([
            'slug' => 'sans_droits_documents_tableau_de_bord',
            'name' => 'Sans droits documents',
            'permissions' => ['documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_documents_tableau_de_bord']);

        Document::factory()->create(['nom_original' => 'confidentiel.pdf']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('confidentiel.pdf')
            ->assertViewHas('cartes', function (array $cartes) {
                $this->assertNull(collect($cartes)->firstWhere('label', 'Documents'));
                $this->assertNull(collect($cartes)->firstWhere('label', 'Diplômes/attestations non retirés'));

                return true;
            })
            ->assertViewHas('graphiques', fn (array $graphiques) => $graphiques === []);
    }

    /**
     * Les pièces d'un dossier étudiant/d'un PV de soutenance... (voir
     * App\Support\TypesEcranDedie) ne doivent pas non plus influencer les
     * chiffres/graphiques visibles avec le seul droit générique "documents"
     * — même correction que DocumentController::index().
     */
    public function test_les_documents_dun_type_a_ecran_dedie_sont_exclus_des_chiffres_et_graphiques(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();
        $typeDossierEtudiant = TypeDocument::factory()->create(['code' => 'DOSSIER_ETUDIANT', 'libelle' => 'Dossier étudiant']);
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats']);

        Document::factory()->create(['type_document_id' => $typeDossierEtudiant->id, 'nom_original' => 'cnib-etudiant.pdf']);
        Document::factory()->create(['type_document_id' => $typeContrat->id, 'nom_original' => 'contrat.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertDontSee('cnib-etudiant.pdf')
            ->assertSee('contrat.pdf')
            ->assertViewHas('cartes', function (array $cartes) {
                $carte = collect($cartes)->firstWhere('label', 'Documents');
                $this->assertSame(1, $carte['valeur']);

                return true;
            })
            ->assertViewHas('graphiques', function (array $graphiques) {
                $this->assertNotContains('Dossier étudiant', $graphiques['parType']['labels']);
                $this->assertContains('Contrats', $graphiques['parType']['labels']);

                return true;
            });
    }

    /**
     * "Mon compte" — demande explicite du client : la personne connectée
     * doit voir son nom, son rôle et ses droits d'accès réels directement
     * sur le tableau de bord (voir DashboardController::monCompte()).
     */
    public function test_mon_compte_affiche_le_nom_et_le_role_de_lutilisateur_connecte(): void
    {
        Storage::fake('sauvegardes');

        Role::query()->create([
            'slug' => 'secretariat_test',
            'name' => 'Secrétariat',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['name' => 'Fatou Traoré', 'role' => 'secretariat_test']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Fatou Traoré')
            ->assertSee('Secrétariat')
            ->assertViewHas('monCompte', function (array $monCompte) {
                $this->assertFalse($monCompte['estAdmin']);
                $this->assertTrue($monCompte['permissions']['documents']['view']);
                $this->assertFalse($monCompte['permissions']['documents']['create']);

                return true;
            });
    }

    /** Un administrateur n'a pas de ligne de permissions à afficher : juste la mention "accès complet" (voir ResourceAccess::can(), qui lui donne toujours tous les droits). */
    public function test_mon_compte_indique_un_acces_complet_pour_un_administrateur(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create(['name' => 'Admin Test']);

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee('Accès complet à toutes les fonctionnalités de la plateforme.')
            ->assertViewHas('monCompte', fn (array $monCompte) => $monCompte['estAdmin'] === true);
    }

    /**
     * Calendrier du mois en cours — demande explicite du client, volontairement
     * "simple, sans évènements" (voir DashboardController::calendrier()).
     */
    public function test_le_calendrier_affiche_le_mois_en_cours_avec_le_jour_du_jour_marque(): void
    {
        Storage::fake('sauvegardes');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.dashboard'))
            ->assertOk()
            ->assertSee(ucfirst(now()->translatedFormat('F Y')))
            ->assertViewHas('calendrier', function (array $calendrier) {
                $jours = collect($calendrier['semaines'])->flatten(1)->filter();

                // Toutes les semaines ont 7 cases (jours réels ou cases vides).
                foreach ($calendrier['semaines'] as $semaine) {
                    $this->assertCount(7, $semaine);
                }

                // Exactement un jour est marqué "aujourd'hui", et c'est le bon.
                $aujourdhui = $jours->filter(fn (array $jour) => $jour['estAujourdhui']);
                $this->assertCount(1, $aujourdhui);
                $this->assertSame(now()->day, $aujourdhui->first()['numero']);

                // Tous les jours du mois sont bien présents, une seule fois chacun.
                $this->assertSame(
                    range(1, now()->daysInMonth),
                    $jours->pluck('numero')->sort()->values()->all()
                );

                return true;
            });
    }
}
