<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\ContratFichier;
use App\Models\Document;
use App\Models\Role;
use App\Models\TypeContrat;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CaptureExportExcel;
use Tests\TestCase;

/**
 * App\Http\Controllers\ContratController — même principe général que
 * tests/Feature/ReunionControllerTest.php (voir son docblock) pour la
 * structure (corbeille, permissions, actions groupées, fichiers qui
 * deviennent de vrais App\Models\Document), avec les mêmes deux
 * caractéristiques assumées par ce contrôleur : aucun champ ne fige un
 * nombre de cases à la création (tout reste modifiable), et UNE case fixe
 * ("contrat_signe") plus une liste d'avenants EN NOMBRE LIBRE.
 *
 * Particularités propres aux contrats, testées ici en plus :
 * - la catégorie (`{typeContrat:code}`) doit être active pour accéder à
 *   l'écran (voir ContratController::index()/create()/store()) ;
 * - le montant est normalisé en NULL (jamais "0.00") quand il est laissé
 *   vide (voir ContratController::valider()) ;
 * - `date_fin` n'est comparée à `date_debut` que si ce dernier est
 *   lui-même renseigné (Rule::when(...)) ;
 * - le verrouillage d'un document (App\Support\Verrouillage) bloque bien
 *   le remplacement/retrait du contrat signé et le retrait d'un avenant.
 */
class ContratControllerTest extends TestCase
{
    use CaptureExportExcel;
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->get(route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]))
            ->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_contrats',
            'name' => 'Sans droits',
            'permissions' => ['contrats' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_contrats']);
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]))
            ->assertForbidden();
    }

    /** Une catégorie inactive (voir App\Models\TypeContrat::actif) n'est plus accessible, même directement par son code. */
    public function test_une_categorie_inactive_renvoie_une_404(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create(['actif' => false]);

        $this->actingAs($admin)
            ->get(route('gestion.contrats.index', ['typeContrat' => $typeContrat->code]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('gestion.contrats.create', ['typeContrat' => $typeContrat->code]))
            ->assertNotFound();
    }

    /** La case "contrat_signe" est pré-créée vide à la création — voir ContratController::creerCasesVides(). */
    public function test_la_creation_dun_contrat_precree_la_case_contrat_signe_vide(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]), [
                'partenaire_prestataire' => 'Association XYZ',
                'objet' => 'Partenariat pédagogique',
                'statut' => Contrat::STATUT_ACTIF,
            ])
            ->assertRedirect();

        $contrat = Contrat::query()->firstOrFail();

        $this->assertSame($typeContrat->id, $contrat->type_contrat_id);
        $this->assertSame($admin->id, $contrat->created_by);
        $this->assertSame(1, $contrat->fichiers()->count());
        $caseContratSigne = $contrat->fichiers()->firstOrFail();
        $this->assertSame(ContratFichier::ROLE_CONTRAT_SIGNE, $caseContratSigne->role);
        $this->assertNull($caseContratSigne->document_id);
    }

    public function test_la_validation_exige_les_champs_obligatoires(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['partenaire_prestataire', 'objet', 'statut']);

        $this->assertSame(0, Contrat::query()->count());
    }

    /** Un montant laissé vide ("") doit être enregistré comme NULL, jamais "0.00" (piège du cast decimal:2). */
    public function test_le_montant_vide_est_enregistre_comme_null(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]), [
                'partenaire_prestataire' => 'Association XYZ',
                'objet' => 'Partenariat pédagogique',
                'statut' => Contrat::STATUT_ACTIF,
                'montant' => '',
            ])
            ->assertRedirect();

        $contrat = Contrat::query()->firstOrFail();
        $this->assertNull($contrat->montant);
    }

    /** `date_fin` doit être postérieure ou égale à `date_debut`, mais UNIQUEMENT si ce dernier est renseigné. */
    public function test_date_fin_doit_etre_posterieure_ou_egale_a_date_debut_quand_les_deux_sont_renseignees(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]), [
                'partenaire_prestataire' => 'Association XYZ',
                'objet' => 'Bail',
                'statut' => Contrat::STATUT_ACTIF,
                'date_debut' => '2026-10-01',
                'date_fin' => '2026-01-01',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('date_fin');

        $this->assertSame(0, Contrat::query()->count());
    }

    /** `date_debut` étant facultatif, le laisser vide ne doit jamais faire échouer la validation de `date_fin`. */
    public function test_date_fin_seule_sans_date_debut_nest_pas_bloquee(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.store', ['typeContrat' => $typeContrat->code]), [
                'partenaire_prestataire' => 'Association XYZ',
                'objet' => 'Bail',
                'statut' => Contrat::STATUT_ACTIF,
                'date_fin' => '2026-01-01',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Contrat::query()->count());
    }

    /** Un contrat ne doit jamais être accessible via le code d'une AUTRE catégorie que la sienne. */
    public function test_show_404_si_le_contrat_nappartient_pas_a_la_categorie_de_lurl(): void
    {
        $admin = User::factory()->admin()->create();
        $typeA = TypeContrat::factory()->create();
        $typeB = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeA->id]);

        $this->actingAs($admin)
            ->get(route('gestion.contrats.show', ['typeContrat' => $typeB->code, 'contrat' => $contrat->id]))
            ->assertNotFound();
    }

    /** Le fichier déposé devient un vrai App\Models\Document, réutilisant le type CONTRAT déjà existant. */
    public function test_deposer_le_contrat_signe_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $caseContratSigne = ContratFichier::factory()->create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->assertRedirect();

        $caseContratSigne->refresh();
        $this->assertNotNull($caseContratSigne->document_id);

        $document = Document::query()->findOrFail($caseContratSigne->document_id);
        $this->assertSame($type->id, $document->type_document_id);
        $this->assertSame($admin->id, $document->uploaded_by);
    }

    /** Remplacer le contrat signé met l'ancien document à la corbeille (jamais supprimé tout de suite). */
    public function test_remplacer_le_contrat_signe_met_lancien_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $caseContratSigne = ContratFichier::factory()->create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
        ]);

        $this->actingAs($admin)->post(route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('v1.png'),
        ])->assertRedirect();
        $ancienDocumentId = $caseContratSigne->refresh()->document_id;

        $this->actingAs($admin)->post(route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('v2.png'),
        ])->assertRedirect();

        $caseContratSigne->refresh();
        $this->assertNotSame($ancienDocumentId, $caseContratSigne->document_id);
        $this->assertNotNull(Document::onlyTrashed()->find($ancienDocumentId));
    }

    /** Retirer le contrat signé vide la case sans la supprimer — elle reste disponible pour un nouveau dépôt. */
    public function test_retirer_le_contrat_signe_vide_la_case_sans_la_supprimer(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $caseContratSigne = ContratFichier::factory()->create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
        ]);

        $this->actingAs($admin)->post(route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('contrat.png'),
        ])->assertRedirect();
        $documentId = $caseContratSigne->refresh()->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.contrat-signe.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]))
            ->assertRedirect();

        $caseContratSigne->refresh();
        $this->assertNull($caseContratSigne->document_id);
        $this->assertDatabaseHas('contrat_fichiers', ['id' => $caseContratSigne->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Un document déjà verrouillé (App\Support\Verrouillage) bloque son propre remplacement/retrait. */
    public function test_le_contrat_signe_verrouille_ne_peut_pas_etre_remplace_ni_retire(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $document = Document::factory()->create(['verrouille_le' => now()]);
        $caseContratSigne = ContratFichier::factory()->create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
                'fichier' => UploadedFile::fake()->image('remplacement.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('verrouillage');

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.contrat-signe.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('verrouillage');

        $caseContratSigne->refresh();
        $this->assertSame($document->id, $caseContratSigne->document_id);
    }

    /** Ajouter un avenant crée une NOUVELLE ligne déjà remplie — aucune case vide pré-créée pour ce rôle. */
    public function test_ajouter_un_avenant_cree_une_nouvelle_ligne_remplie(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($admin)
            ->post(route('gestion.contrats.avenants.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
                'libelle' => 'Avenant n°1 — prolongation',
                'fichier' => UploadedFile::fake()->image('avenant.png'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $contrat->fichiers()->where('role', ContratFichier::ROLE_AVENANT)->count());
        $avenant = $contrat->fichiers()->where('role', ContratFichier::ROLE_AVENANT)->firstOrFail();
        $this->assertSame(1, $avenant->numero);
        $this->assertSame('Avenant n°1 — prolongation', $avenant->libelle);
        $this->assertNotNull($avenant->document_id);
    }

    /** Chaque nouvel avenant reçoit le numéro suivant (simple compteur d'affichage, pas de case figée). */
    public function test_ajouter_plusieurs_avenants_incremente_le_numero(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($admin)->post(route('gestion.contrats.avenants.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('avenant-1.png'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('gestion.contrats.avenants.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('avenant-2.png'),
        ])->assertRedirect();

        $this->assertSame(
            [1, 2],
            $contrat->fichiers()->where('role', ContratFichier::ROLE_AVENANT)->orderBy('numero')->pluck('numero')->all()
        );
    }

    /** Retirer un avenant supprime la LIGNE elle-même (pas de case à garder disponible) et met le document à la corbeille. */
    public function test_retirer_un_avenant_supprime_la_ligne_et_met_le_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($admin)->post(route('gestion.contrats.avenants.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
            'fichier' => UploadedFile::fake()->image('avenant.png'),
        ])->assertRedirect();
        $avenant = $contrat->fichiers()->where('role', ContratFichier::ROLE_AVENANT)->firstOrFail();
        $documentId = $avenant->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.avenants.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id, 'fichier' => $avenant->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('contrat_fichiers', ['id' => $avenant->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Un avenant d'un AUTRE contrat ne doit jamais être retirable via l'URL d'un contrat différent. */
    public function test_un_avenant_dun_autre_contrat_ne_peut_pas_etre_retire(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contratA = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratB = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $avenantDeA = ContratFichier::factory()->create([
            'contrat_id' => $contratA->id,
            'role' => ContratFichier::ROLE_AVENANT,
            'numero' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.avenants.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contratB->id, 'fichier' => $avenantDeA->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('contrat_fichiers', ['id' => $avenantDeA->id]);
    }

    public function test_mise_a_la_corbeille_et_restauration_dun_contrat(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]))
            ->assertRedirect();

        $this->assertNotNull($contrat->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.contrats.restore', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]))
            ->assertRedirect();

        $this->assertNull($contrat->fresh()->deleted_at);
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés (contrat signé + avenants). */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $document = Document::factory()->create();
        ContratFichier::factory()->create([
            'contrat_id' => $contrat->id,
            'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
            'document_id' => $document->id,
        ]);
        $contrat->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.contrats.force-destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('contrats', ['id' => $contrat->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
    }

    /** Aucun champ ne détermine un nombre de cases à figer : tous restent modifiables après création (voir le docblock du contrôleur). */
    public function test_tous_les_champs_restent_modifiables_apres_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create([
            'type_contrat_id' => $typeContrat->id,
            'statut' => Contrat::STATUT_EN_NEGOCIATION,
        ]);

        $this->actingAs($admin)
            ->put(route('gestion.contrats.update', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]), [
                'partenaire_prestataire' => 'Nouveau partenaire',
                'objet' => 'Objet corrigé',
                'statut' => Contrat::STATUT_ACTIF,
                'montant' => '150000',
            ])
            ->assertRedirect();

        $contrat->refresh();
        $this->assertSame('Nouveau partenaire', $contrat->partenaire_prestataire);
        $this->assertSame('Objet corrigé', $contrat->objet);
        $this->assertSame(Contrat::STATUT_ACTIF, $contrat->statut);
        $this->assertSame('150000.00', $contrat->montant);
    }

    /** export() respecte les mêmes filtres que index() (même requeteListe() partagée, voir le docblock d'export()). */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        Contrat::factory()->create(['type_contrat_id' => $typeContrat->id, 'partenaire_prestataire' => 'Association Alpha']);
        Contrat::factory()->create(['type_contrat_id' => $typeContrat->id, 'partenaire_prestataire' => 'Entreprise Beta']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.contrats.export', ['typeContrat' => $typeContrat->code, 'recherche' => 'Alpha']))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame('Association Alpha', $lignes[0][0]);
    }

    /**
     * Actions groupées (sélection multiple) — même principe que
     * tests/Feature/ReunionControllerTest.php.
     */
    public function test_mise_a_la_corbeille_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contratA = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratB = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratHorsSelection = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($admin)
            ->post(route('gestion.contrats.corbeille-groupee', ['typeContrat' => $typeContrat->code]), [
                'contrats' => [$contratA->id, $contratB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($contratA->fresh()->deleted_at);
        $this->assertNotNull($contratB->fresh()->deleted_at);
        $this->assertNull($contratHorsSelection->fresh()->deleted_at);
    }

    public function test_restauration_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contratA = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratB = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratA->delete();
        $contratB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.corbeille.restaurer-groupe', ['typeContrat' => $typeContrat->code]), [
                'contrats' => [$contratA->id, $contratB->id],
            ])
            ->assertRedirect();

        $this->assertNull($contratA->fresh()->deleted_at);
        $this->assertNull($contratB->fresh()->deleted_at);
    }

    /** Même raisonnement que forceDestroy() individuel : les documents encore attachés sont mis à la corbeille avant de supprimer les contrats. */
    public function test_suppression_definitive_groupee_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();
        $contratA = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $contratB = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);
        $documentA = Document::factory()->create();
        $documentB = Document::factory()->create();
        ContratFichier::factory()->create(['contrat_id' => $contratA->id, 'document_id' => $documentA->id]);
        ContratFichier::factory()->create(['contrat_id' => $contratB->id, 'document_id' => $documentB->id]);
        $contratA->delete();
        $contratB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.corbeille.force-destroy-groupe', ['typeContrat' => $typeContrat->code]), [
                'contrats' => [$contratA->id, $contratB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('contrats', ['id' => $contratA->id]);
        $this->assertDatabaseMissing('contrats', ['id' => $contratB->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));
        $this->assertNotNull(Document::onlyTrashed()->find($documentB->id));
    }

    /** Une sélection vide (ou absente) doit être refusée côté serveur, pas seulement empêchée par le JS du bouton. */
    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();
        $typeContrat = TypeContrat::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.contrats.corbeille-groupee', ['typeContrat' => $typeContrat->code]), ['contrats' => []])
            ->assertRedirect()
            ->assertSessionHasErrors('contrats');
    }

    /** Sans le droit "delete" sur "contrats", les trois actions groupées restent interdites — même droit que les actions individuelles. */
    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'contrats_lecture_seule',
            'name' => 'Lecture seule contrats',
            'permissions' => ['contrats' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'contrats_lecture_seule']);
        $typeContrat = TypeContrat::factory()->create();
        $contrat = Contrat::factory()->create(['type_contrat_id' => $typeContrat->id]);

        $this->actingAs($utilisateur)
            ->post(route('gestion.contrats.corbeille-groupee', ['typeContrat' => $typeContrat->code]), ['contrats' => [$contrat->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.contrats.corbeille.restaurer-groupe', ['typeContrat' => $typeContrat->code]), ['contrats' => [$contrat->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.contrats.corbeille.force-destroy-groupe', ['typeContrat' => $typeContrat->code]), ['contrats' => [$contrat->id]])
            ->assertForbidden();
    }
}
