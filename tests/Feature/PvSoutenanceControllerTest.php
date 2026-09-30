<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Filiere;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\PvSoutenanceController — structure dédiée au PV de
 * soutenance (un enregistrement = un étudiant), demandée explicitement par
 * le client à partir d'un exemple de registre papier. Voir le docblock de la
 * migration 2026_10_04_000001_create_pv_soutenances_table.php.
 */
class PvSoutenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function donneesValides(array $surcharge = []): array
    {
        $filiere = $surcharge['filiere_id'] ?? Filiere::factory()->create()->id;

        return array_merge([
            'niveau' => PvSoutenance::NIVEAU_LICENCE,
            'date_soutenance' => '2026-06-15',
            'numero_ordre_registre' => '42',
            'nom' => 'Ouédraogo',
            'prenoms' => 'Aïcha',
            'date_naissance' => '2000-01-01',
            'lieu_naissance' => 'Ouagadougou',
            'numero_pv' => 'PV-2026-001',
            'filiere_id' => $filiere,
            'theme' => 'Étude comparative des systèmes de gestion des archives',
            'decision_jury' => PvSoutenance::DECISION_ADMIS,
            'moyenne' => '14.50',
            'mention' => PvSoutenance::MENTION_BIEN,
            'president_jury' => 'Pr. Jean Kaboré',
        ], $surcharge);
    }

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.pv-soutenances.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_soutenance',
            'name' => 'Sans droits',
            'permissions' => ['pv_soutenances' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_soutenance']);

        $this->actingAs($utilisateur)->get(route('gestion.pv-soutenances.index'))->assertForbidden();
    }

    /** Diplôme + image du registre : les deux cases fixes, toujours créées. */
    public function test_la_creation_dun_pv_precree_les_deux_cases_fixes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides())
            ->assertRedirect();

        $pv = PvSoutenance::query()->firstOrFail();

        $this->assertSame(2, $pv->fichiers()->count());
        $this->assertSame(
            [PvSoutenanceFichier::ROLE_DIPLOME, PvSoutenanceFichier::ROLE_IMAGE_REGISTRE],
            $pv->fichiers()->pluck('role')->all()
        );
    }

    /** Numéro d'ordre du registre unique PAR NIVEAU — même numéro réutilisable sur un autre niveau (registres papier distincts). */
    public function test_le_numero_dordre_registre_est_unique_par_niveau_mais_pas_entre_niveaux(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_LICENCE, 'numero_ordre_registre' => '10']);

        // Même niveau, même numéro : refusé.
        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides([
                'niveau' => PvSoutenance::NIVEAU_LICENCE,
                'numero_ordre_registre' => '10',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('numero_ordre_registre');

        // Niveau différent, même numéro : accepté (registre papier distinct).
        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides([
                'niveau' => PvSoutenance::NIVEAU_MASTER,
                'numero_ordre_registre' => '10',
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, PvSoutenance::query()->count());
    }

    /** Numéro de PV unique pour TOUTE la plateforme. */
    public function test_le_numero_de_pv_est_unique_pour_toute_la_plateforme(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['numero_pv' => 'PV-UNIQUE']);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides(['numero_pv' => 'PV-UNIQUE']))
            ->assertRedirect()
            ->assertSessionHasErrors('numero_pv');

        $this->assertSame(1, PvSoutenance::query()->count());
    }

    /** Le fichier déposé devient un vrai App\Models\Document, réutilisant le type PV_SOUTENANCE déjà existant. */
    public function test_deposer_un_fichier_sur_une_case_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);
        $pv = PvSoutenance::factory()->create();
        $caseDiplome = PvSoutenanceFichier::factory()->create([
            'pv_soutenance_id' => $pv->id,
            'role' => PvSoutenanceFichier::ROLE_DIPLOME,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.fichiers.store', ['pvSoutenance' => $pv->id, 'fichier' => $caseDiplome->id]), [
                'fichier' => UploadedFile::fake()->image('diplome.png'),
            ])
            ->assertRedirect();

        $caseDiplome->refresh();
        $this->assertNotNull($caseDiplome->document_id);

        $document = Document::query()->findOrFail($caseDiplome->document_id);
        $this->assertSame($type->id, $document->type_document_id);
        $this->assertNull($document->sous_type_document_id);
        $this->assertSame($pv->filiere_id, $document->filiere_id);
        $this->assertSame($admin->id, $document->uploaded_by);
    }

    /**
     * Enregistrer le retrait du diplôme capture QUI est venu le chercher
     * (l'étudiant lui-même) et À QUELLE DATE, en plus de qui/quand
     * l'utilisateur connecté l'a saisi — voir le docblock de
     * PvSoutenanceController::enregistrerRetraitDiplome().
     */
    public function test_enregistrer_le_retrait_du_diplome_par_letudiant_lui_meme(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
                'diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT,
                'diplome_date_retrait' => '2026-07-01',
            ])
            ->assertRedirect();

        $pv->refresh();
        $this->assertTrue($pv->estDiplomeRetire());
        $this->assertSame(PvSoutenance::RETIRANT_ETUDIANT, $pv->diplome_retirant);
        $this->assertNull($pv->diplome_nom_retirant);
        $this->assertSame('2026-07-01', $pv->diplome_date_retrait->format('Y-m-d'));
        $this->assertSame($admin->id, $pv->diplome_retrait_enregistre_par_id);
        $this->assertNotNull($pv->diplome_retrait_enregistre_le);
    }

    /** Quand ce n'est pas l'étudiant, le nom de la personne venue est obligatoire. */
    public function test_le_nom_est_obligatoire_quand_ce_nest_pas_letudiant(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
                'diplome_retirant' => PvSoutenance::RETIRANT_AUTRE,
                'diplome_date_retrait' => '2026-07-01',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('diplome_nom_retirant');

        $this->assertFalse($pv->fresh()->estDiplomeRetire());

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
                'diplome_retirant' => PvSoutenance::RETIRANT_AUTRE,
                'diplome_nom_retirant' => 'Moussa Ouédraogo (frère)',
                'diplome_date_retrait' => '2026-07-01',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $pv->refresh();
        $this->assertTrue($pv->estDiplomeRetire());
        $this->assertSame('Moussa Ouédraogo (frère)', $pv->diplome_nom_retirant);
        $this->assertSame('Moussa Ouédraogo (frère)', $pv->diplomeRetirantLibelle());
    }

    /** Annuler un retrait enregistré par erreur remet tout à zéro, y compris l'auteur et la date d'enregistrement. */
    public function test_annuler_le_retrait_du_diplome(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $this->actingAs($admin)->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
            'diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT,
            'diplome_date_retrait' => '2026-07-01',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('gestion.pv-soutenances.retrait-diplome.destroy', $pv->id))
            ->assertRedirect();

        $pv->refresh();
        $this->assertFalse($pv->estDiplomeRetire());
        $this->assertNull($pv->diplome_retirant);
        $this->assertNull($pv->diplome_nom_retirant);
        $this->assertNull($pv->diplome_retrait_enregistre_par_id);
        $this->assertNull($pv->diplome_retrait_enregistre_le);
    }

    /** Ré-enregistrer le retrait corrige la saisie précédente (pas de verrou après un premier enregistrement). */
    public function test_reenregistrer_le_retrait_corrige_la_saisie_precedente(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $this->actingAs($admin)->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
            'diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT,
            'diplome_date_retrait' => '2026-07-01',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.retrait-diplome.store', $pv->id), [
                'diplome_retirant' => PvSoutenance::RETIRANT_AUTRE,
                'diplome_nom_retirant' => 'Une voisine',
                'diplome_date_retrait' => '2026-07-03',
            ])
            ->assertRedirect();

        $pv->refresh();
        $this->assertSame(PvSoutenance::RETIRANT_AUTRE, $pv->diplome_retirant);
        $this->assertSame('Une voisine', $pv->diplome_nom_retirant);
        $this->assertSame('2026-07-03', $pv->diplome_date_retrait->format('Y-m-d'));
    }

    /** La liste peut être filtrée par statut de retrait du diplôme (remplace l'ancien écran Documents filtré par type). */
    public function test_la_liste_peut_etre_filtree_par_statut_de_retrait_du_diplome(): void
    {
        $admin = User::factory()->admin()->create();
        $pvRetire = PvSoutenance::factory()->create(['diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT, 'diplome_date_retrait' => '2026-07-01']);
        $pvNonRetire = PvSoutenance::factory()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['diplome_retrait' => 'retire']));
        $reponse->assertOk();
        $reponse->assertSee($pvRetire->numero_pv);
        $reponse->assertDontSee($pvNonRetire->numero_pv);
    }

    /** Déposer un nouveau fichier sur une case déjà remplie met l'ancien document à la corbeille. */
    public function test_remplacer_le_fichier_dune_case_met_lancien_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);

        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $case = PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pv->id, 'role' => PvSoutenanceFichier::ROLE_IMAGE_REGISTRE]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.fichiers.store', ['pvSoutenance' => $pv->id, 'fichier' => $case->id]), [
                'fichier' => UploadedFile::fake()->image('v1.png'),
            ])
            ->assertRedirect();
        $case->refresh();
        $ancienDocumentId = $case->document_id;

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.fichiers.store', ['pvSoutenance' => $pv->id, 'fichier' => $case->id]), [
                'fichier' => UploadedFile::fake()->image('v2.png'),
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertNotSame($ancienDocumentId, $case->document_id);
        $this->assertNotNull(Document::onlyTrashed()->find($ancienDocumentId));
    }

    /** Une case d'un AUTRE PV ne doit jamais être modifiable via l'URL d'un PV différent. */
    public function test_une_case_dun_autre_pv_ne_peut_pas_etre_modifiee(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $pvA = PvSoutenance::factory()->create();
        $pvB = PvSoutenance::factory()->create();
        $caseDeA = PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pvA->id]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.fichiers.store', ['pvSoutenance' => $pvB->id, 'fichier' => $caseDeA->id]), [
                'fichier' => UploadedFile::fake()->image('intrus.png'),
            ])
            ->assertNotFound();
    }

    public function test_mise_a_la_corbeille_et_restauration_dun_pv(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.pv-soutenances.destroy', $pv->id))
            ->assertRedirect();

        $this->assertNotNull($pv->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.restore', $pv->id))
            ->assertRedirect();

        $this->assertNull($pv->fresh()->deleted_at);
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés aux cases. */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);

        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $case = PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pv->id]);

        $this->actingAs($admin)->post(route('gestion.pv-soutenances.fichiers.store', ['pvSoutenance' => $pv->id, 'fichier' => $case->id]), [
            'fichier' => UploadedFile::fake()->image('diplome.png'),
        ])->assertRedirect();
        $documentId = $case->refresh()->document_id;

        $this->actingAs($admin)->delete(route('gestion.pv-soutenances.destroy', $pv->id));

        $this->actingAs($admin)
            ->delete(route('gestion.pv-soutenances.force-destroy', $pv->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('pv_soutenances', ['id' => $pv->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /**
     * Contrairement au PV de délibération/Relevé de notes (description
     * uniquement modifiable après création) : TOUS les champs restent
     * modifiables ici — voir le docblock de PvSoutenanceController::update().
     */
    public function test_tous_les_champs_sont_modifiables_apres_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create(['nom' => 'Ancien nom', 'moyenne' => 10.0]);

        $this->actingAs($admin)
            ->put(route('gestion.pv-soutenances.update', $pv->id), $this->donneesValides([
                'filiere_id' => $pv->filiere_id,
                'nom' => 'Nouveau nom',
                'moyenne' => '16.75',
                'numero_ordre_registre' => $pv->numero_ordre_registre,
                'numero_pv' => $pv->numero_pv,
                'niveau' => $pv->niveau,
            ]))
            ->assertRedirect();

        $pv->refresh();
        $this->assertSame('Nouveau nom', $pv->nom);
        $this->assertEquals(16.75, $pv->moyenne);
    }
}
