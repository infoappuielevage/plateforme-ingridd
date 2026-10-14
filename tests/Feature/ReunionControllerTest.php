<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CaptureExportExcel;
use Tests\TestCase;

/**
 * App\Http\Controllers\ReunionController — même principe que
 * tests/Feature/SessionReleveControllerTest.php (voir son docblock) pour la
 * structure générale (corbeille, permissions, fichiers qui deviennent de
 * vrais App\Models\Document), mais DEUX différences majeures assumées par le
 * contrôleur (voir son docblock) :
 *
 * - aucune combinaison de champs ne "figge" un nombre de cases à la création
 *   (pas de cycle/filière) : TOUS les champs restent modifiables après coup,
 *   contrairement aux PV de délibération/Relevés de notes ;
 * - une seule case fixe ("rapport") plus une liste de pièces jointes EN
 *   NOMBRE LIBRE, ajoutées/retirées une à une plutôt que préremplies par
 *   cycle.
 */
class ReunionControllerTest extends TestCase
{
    use CaptureExportExcel;
    use RefreshDatabase;

    /** export() respecte les mêmes filtres que index() (même requeteListe() partagée, voir le docblock d'export()). */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        Reunion::factory()->create(['description' => 'Réunion du conseil de direction']);
        Reunion::factory()->create(['description' => 'Réunion pédagogique']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.reunions.export', ['recherche' => 'conseil']))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame('Réunion du conseil de direction', $lignes[0][3]);
    }

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.reunions.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_reunions',
            'name' => 'Sans droits',
            'permissions' => ['reunions' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_reunions']);

        $this->actingAs($utilisateur)->get(route('gestion.reunions.index'))->assertForbidden();
    }

    /** La case "rapport" est pré-créée vide à la création — voir ReunionController::creerCasesVides(). */
    public function test_la_creation_dune_reunion_precree_la_case_rapport_vide(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_REUNION']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Réunion hebdomadaire']);

        $this->actingAs($admin)
            ->post(route('gestion.reunions.store'), [
                'annee_academique_id' => $annee->id,
                'date_reunion' => '2026-10-07',
                'sous_type_document_id' => $sousType->id,
                'description' => 'Point hebdomadaire',
            ])
            ->assertRedirect();

        $reunion = Reunion::query()->firstOrFail();

        $this->assertSame(1, $reunion->fichiers()->count());
        $caseRapport = $reunion->fichiers()->firstOrFail();
        $this->assertSame(ReunionFichier::ROLE_RAPPORT, $caseRapport->role);
        $this->assertNull($caseRapport->document_id);
        $this->assertSame($sousType->id, $reunion->sous_type_document_id);
    }

    public function test_la_validation_exige_les_champs_obligatoires(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['annee_academique_id', 'date_reunion', 'sous_type_document_id']);

        $this->assertSame(0, Reunion::query()->count());
    }

    /** `sous_type_document_id` doit appartenir au type PV_REUNION — un sous-type d'un autre type (ex. Bordereau) est refusé. */
    public function test_le_sous_type_document_id_doit_appartenir_au_type_pv_reunion(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);
        $autreType = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousTypeDunAutreType = SousTypeDocument::factory()->create(['type_document_id' => $autreType->id]);

        $this->actingAs($admin)
            ->post(route('gestion.reunions.store'), [
                'annee_academique_id' => $annee->id,
                'date_reunion' => '2026-10-07',
                'sous_type_document_id' => $sousTypeDunAutreType->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sous_type_document_id');

        $this->assertSame(0, Reunion::query()->count());
    }

    /** Le fichier déposé devient un vrai App\Models\Document, réutilisant le type PV_REUNION déjà existant. */
    public function test_deposer_le_rapport_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_REUNION']);
        $reunion = Reunion::factory()->create();
        $caseRapport = ReunionFichier::factory()->create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.reunions.rapport.store', $reunion->id), [
                'fichier' => UploadedFile::fake()->image('rapport.png'),
            ])
            ->assertRedirect();

        $caseRapport->refresh();
        $this->assertNotNull($caseRapport->document_id);

        $document = Document::query()->findOrFail($caseRapport->document_id);
        $this->assertSame($type->id, $document->type_document_id);
        $this->assertSame($reunion->annee_academique_id, $document->annee_academique_id);
        $this->assertSame($admin->id, $document->uploaded_by);
    }

    /** Remplacer le rapport met l'ancien document à la corbeille (jamais supprimé tout de suite). */
    public function test_remplacer_le_rapport_met_lancien_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();
        $caseRapport = ReunionFichier::factory()->create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.reunions.rapport.store', $reunion->id), [
                'fichier' => UploadedFile::fake()->image('v1.png'),
            ])
            ->assertRedirect();

        $caseRapport->refresh();
        $ancienDocumentId = $caseRapport->document_id;

        $this->actingAs($admin)
            ->post(route('gestion.reunions.rapport.store', $reunion->id), [
                'fichier' => UploadedFile::fake()->image('v2.png'),
            ])
            ->assertRedirect();

        $caseRapport->refresh();
        $this->assertNotSame($ancienDocumentId, $caseRapport->document_id);
        $this->assertNotNull(Document::onlyTrashed()->find($ancienDocumentId));
    }

    /** Retirer le rapport vide la case sans la supprimer — elle reste disponible pour un nouveau dépôt. */
    public function test_retirer_le_rapport_vide_la_case_sans_la_supprimer(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();
        $caseRapport = ReunionFichier::factory()->create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
        ]);

        $this->actingAs($admin)->post(route('gestion.reunions.rapport.store', $reunion->id), [
            'fichier' => UploadedFile::fake()->image('rapport.png'),
        ])->assertRedirect();
        $documentId = $caseRapport->refresh()->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.rapport.destroy', $reunion->id))
            ->assertRedirect();

        $caseRapport->refresh();
        $this->assertNull($caseRapport->document_id);
        $this->assertDatabaseHas('reunion_fichiers', ['id' => $caseRapport->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Ajouter une pièce jointe crée une NOUVELLE ligne déjà remplie — aucune case vide pré-créée pour ce rôle. */
    public function test_ajouter_une_piece_jointe_cree_une_nouvelle_ligne_remplie(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.pieces-jointes.store', $reunion->id), [
                'libelle' => 'Liste de présence',
                'fichier' => UploadedFile::fake()->image('presence.png'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $reunion->fichiers()->where('role', ReunionFichier::ROLE_PIECE_JOINTE)->count());
        $pieceJointe = $reunion->fichiers()->where('role', ReunionFichier::ROLE_PIECE_JOINTE)->firstOrFail();
        $this->assertSame(1, $pieceJointe->numero);
        $this->assertSame('Liste de présence', $pieceJointe->libelle);
        $this->assertNotNull($pieceJointe->document_id);
    }

    /** Chaque nouvelle pièce jointe reçoit le numéro suivant (simple compteur d'affichage, pas de case figée). */
    public function test_ajouter_plusieurs_pieces_jointes_incremente_le_numero(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();

        $this->actingAs($admin)->post(route('gestion.reunions.pieces-jointes.store', $reunion->id), [
            'fichier' => UploadedFile::fake()->image('annexe-1.png'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('gestion.reunions.pieces-jointes.store', $reunion->id), [
            'fichier' => UploadedFile::fake()->image('annexe-2.png'),
        ])->assertRedirect();

        $this->assertSame(
            [1, 2],
            $reunion->fichiers()->where('role', ReunionFichier::ROLE_PIECE_JOINTE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    /** Retirer une pièce jointe supprime la LIGNE elle-même (pas de case à garder disponible) et met le document à la corbeille. */
    public function test_retirer_une_piece_jointe_supprime_la_ligne_et_met_le_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();

        $this->actingAs($admin)->post(route('gestion.reunions.pieces-jointes.store', $reunion->id), [
            'fichier' => UploadedFile::fake()->image('annexe.png'),
        ])->assertRedirect();
        $pieceJointe = $reunion->fichiers()->where('role', ReunionFichier::ROLE_PIECE_JOINTE)->firstOrFail();
        $documentId = $pieceJointe->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.pieces-jointes.destroy', ['reunion' => $reunion->id, 'fichier' => $pieceJointe->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('reunion_fichiers', ['id' => $pieceJointe->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Une pièce jointe d'une AUTRE réunion ne doit jamais être retirable via l'URL d'une réunion différente. */
    public function test_une_piece_jointe_dune_autre_reunion_ne_peut_pas_etre_retiree(): void
    {
        $admin = User::factory()->admin()->create();
        $reunionA = Reunion::factory()->create();
        $reunionB = Reunion::factory()->create();
        $pieceDeA = ReunionFichier::factory()->create([
            'reunion_id' => $reunionA->id,
            'role' => ReunionFichier::ROLE_PIECE_JOINTE,
            'numero' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.pieces-jointes.destroy', ['reunion' => $reunionB->id, 'fichier' => $pieceDeA->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('reunion_fichiers', ['id' => $pieceDeA->id]);
    }

    public function test_mise_a_la_corbeille_et_restauration_dune_reunion(): void
    {
        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.destroy', $reunion->id))
            ->assertRedirect();

        $this->assertNotNull($reunion->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.reunions.restore', $reunion->id))
            ->assertRedirect();

        $this->assertNull($reunion->fresh()->deleted_at);
    }

    /**
     * Cascade MISE À LA CORBEILLE / RESTAURATION — demande explicite du
     * client ("cascade incohérente") : voir le docblock de
     * ReunionController::mettreFichiersALaCorbeille(). Avant ce correctif,
     * seule la suppression DÉFINITIVE emportait les documents attachés ;
     * la simple mise à la corbeille les laissait actifs.
     */
    public function test_mise_a_la_corbeille_dune_reunion_met_aussi_son_document_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();
        $document = Document::factory()->create();
        ReunionFichier::factory()->create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.destroy', $reunion->id))
            ->assertRedirect();

        $this->assertNotNull($reunion->fresh()->deleted_at);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));

        $this->actingAs($admin)
            ->post(route('gestion.reunions.restore', $reunion->id))
            ->assertRedirect();

        $this->assertNull($reunion->fresh()->deleted_at);
        $this->assertNull(Document::onlyTrashed()->find($document->id));
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés (rapport + pièces jointes). */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);

        $admin = User::factory()->admin()->create();
        $reunion = Reunion::factory()->create();
        $caseRapport = ReunionFichier::factory()->create([
            'reunion_id' => $reunion->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
        ]);

        $this->actingAs($admin)->post(route('gestion.reunions.rapport.store', $reunion->id), [
            'fichier' => UploadedFile::fake()->image('rapport.png'),
        ])->assertRedirect();
        $documentId = $caseRapport->refresh()->document_id;

        $this->actingAs($admin)->delete(route('gestion.reunions.destroy', $reunion->id));

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.force-destroy', $reunion->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('reunions', ['id' => $reunion->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /**
     * "Vider la corbeille" — demande explicite du client (voir le docblock
     * de ReunionController::viderCorbeille()) : purge TOUTES les réunions
     * corbeillées en un clic, avec la même cascade documents que
     * forceDestroy()/forceDestroyGroupe(), et ne touche JAMAIS une réunion
     * qui n'est pas à la corbeille.
     */
    public function test_vider_la_corbeille_supprime_tout_sauf_les_reunions_actives(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_REUNION']);
        $admin = User::factory()->admin()->create();

        $reunionCorbeillee = Reunion::factory()->create();
        $document = Document::factory()->create();
        ReunionFichier::factory()->create([
            'reunion_id' => $reunionCorbeillee->id,
            'role' => ReunionFichier::ROLE_RAPPORT,
            'document_id' => $document->id,
        ]);
        $reunionCorbeillee->delete();

        $reunionActive = Reunion::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.reunions.corbeille.vider'))
            ->assertRedirect();

        $this->assertDatabaseMissing('reunions', ['id' => $reunionCorbeillee->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
        $this->assertDatabaseHas('reunions', ['id' => $reunionActive->id, 'deleted_at' => null]);
    }

    /**
     * Contrairement aux PV de délibération/Relevés de notes (où seule la
     * description reste modifiable, voir SessionReleveControllerTest), AUCUN
     * champ d'une réunion ne détermine un nombre de cases à figer : tous
     * restent modifiables après création (voir le docblock de
     * ReunionController).
     */
    public function test_tous_les_champs_restent_modifiables_apres_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_REUNION']);
        $ancienneAnnee = AnneeAcademique::factory()->create();
        $nouvelleAnnee = AnneeAcademique::factory()->create();
        $nouveauSousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id]);
        $reunion = Reunion::factory()->create(['annee_academique_id' => $ancienneAnnee->id]);

        $this->actingAs($admin)
            ->put(route('gestion.reunions.update', $reunion->id), [
                'annee_academique_id' => $nouvelleAnnee->id,
                'date_reunion' => '2026-11-01',
                'sous_type_document_id' => $nouveauSousType->id,
                'description' => 'Description corrigée',
            ])
            ->assertRedirect();

        $reunion->refresh();
        $this->assertSame($nouvelleAnnee->id, $reunion->annee_academique_id);
        $this->assertSame('2026-11-01', $reunion->date_reunion->toDateString());
        $this->assertSame($nouveauSousType->id, $reunion->sous_type_document_id);
        $this->assertSame('Description corrigée', $reunion->description);
    }

    /**
     * Actions groupées (sélection multiple, voir
     * resources/views/gestion/reunions/index.blade.php) — MÊME PRINCIPE que
     * tests/Feature/EtudiantControllerTest.php pour
     * mettreCorbeilleGroupee()/restaurerGroupe()/forceDestroyGroupe(), sur
     * demande explicite du client ("permettre de faire des sélections pour
     * des actions sur les tableaux différents").
     */
    public function test_mise_a_la_corbeille_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $reunionA = Reunion::factory()->create();
        $reunionB = Reunion::factory()->create();
        $reunionHorsSelection = Reunion::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.corbeille-groupee'), [
                'reunions' => [$reunionA->id, $reunionB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($reunionA->fresh()->deleted_at);
        $this->assertNotNull($reunionB->fresh()->deleted_at);
        $this->assertNull($reunionHorsSelection->fresh()->deleted_at);
    }

    public function test_restauration_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $reunionA = Reunion::factory()->create();
        $reunionB = Reunion::factory()->create();
        $reunionA->delete();
        $reunionB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.corbeille.restaurer-groupe'), [
                'reunions' => [$reunionA->id, $reunionB->id],
            ])
            ->assertRedirect();

        $this->assertNull($reunionA->fresh()->deleted_at);
        $this->assertNull($reunionB->fresh()->deleted_at);
    }

    /** Même raisonnement que forceDestroy() individuel : les documents encore attachés (rapport ou pièce jointe) sont mis à la corbeille avant de supprimer les réunions. */
    public function test_suppression_definitive_groupee_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $reunionA = Reunion::factory()->create();
        $reunionB = Reunion::factory()->create();
        $documentA = Document::factory()->create();
        $documentB = Document::factory()->create();
        ReunionFichier::factory()->create(['reunion_id' => $reunionA->id, 'document_id' => $documentA->id]);
        ReunionFichier::factory()->create(['reunion_id' => $reunionB->id, 'document_id' => $documentB->id]);
        $reunionA->delete();
        $reunionB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.corbeille.force-destroy-groupe'), [
                'reunions' => [$reunionA->id, $reunionB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('reunions', ['id' => $reunionA->id]);
        $this->assertDatabaseMissing('reunions', ['id' => $reunionB->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));
        $this->assertNotNull(Document::onlyTrashed()->find($documentB->id));
    }

    /** Une sélection vide (ou absente) doit être refusée côté serveur, pas seulement empêchée par le JS du bouton. */
    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.reunions.corbeille-groupee'), ['reunions' => []])
            ->assertRedirect()
            ->assertSessionHasErrors('reunions');
    }

    /** Sans le droit "delete" sur "reunions", les trois actions groupées restent interdites — même droit que les actions individuelles. */
    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'reunions_lecture_seule',
            'name' => 'Lecture seule réunions',
            'permissions' => ['reunions' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'reunions_lecture_seule']);
        $reunion = Reunion::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.reunions.corbeille-groupee'), ['reunions' => [$reunion->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.reunions.corbeille.restaurer-groupe'), ['reunions' => [$reunion->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.reunions.corbeille.force-destroy-groupe'), ['reunions' => [$reunion->id]])
            ->assertForbidden();
    }

    /**
     * Le droit "Supprimer définitivement" (delete_definitif) est désormais
     * distinct du droit "Supprimer" (delete) — demande explicite du client
     * ("permission séparée pour la suppression définitive"), voir
     * App\Support\ResourceAccess::ACTIONS. "delete" ne couvre plus que la
     * mise à la corbeille/restauration (réversible) ; "delete_definitif"
     * couvre la purge irréversible (force-destroy individuel/groupé, vider
     * la corbeille). Un rôle avec "delete" mais sans "delete_definitif" peut
     * donc toujours mettre à la corbeille/restaurer, mais reçoit un 403 sur
     * toute suppression irréversible.
     */
    public function test_le_droit_delete_definitif_est_distinct_du_droit_delete(): void
    {
        Role::query()->create([
            'slug' => 'reunions_sans_suppression_definitive',
            'name' => 'Réunions sans suppression définitive',
            'permissions' => ['reunions' => ['view' => true, 'delete' => true, 'delete_definitif' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'reunions_sans_suppression_definitive']);
        $reunion = Reunion::factory()->create();

        // Mise à la corbeille et restauration restent autorisées (droit "delete" toujours accordé).
        $this->actingAs($utilisateur)
            ->delete(route('gestion.reunions.destroy', $reunion->id))
            ->assertRedirect();
        $this->assertNotNull($reunion->fresh()->deleted_at);

        $this->actingAs($utilisateur)
            ->post(route('gestion.reunions.restore', $reunion->id))
            ->assertRedirect();
        $this->assertNull($reunion->fresh()->deleted_at);

        // Suppression définitive refusée (individuelle, groupée, vider la corbeille) — "delete_definitif" absent/false.
        $reunion->delete();

        $this->actingAs($utilisateur)
            ->delete(route('gestion.reunions.force-destroy', $reunion->id))
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.reunions.corbeille.force-destroy-groupe'), ['reunions' => [$reunion->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->delete(route('gestion.reunions.corbeille.vider'))
            ->assertForbidden();
    }
}
