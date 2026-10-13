<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CaptureExportExcel;
use Tests\TestCase;

/**
 * App\Http\Controllers\SessionDeliberationController — nouvelle structure
 * dédiée aux PV de délibération (année académique / session / cycle /
 * filière + notes d'information + fichiers par semestre ou par année de
 * thèse), demandée explicitement par le client en remplacement du simple
 * "un document = un fichier" utilisé jusqu'ici pour ce type. Voir le
 * docblock de la migration 2026_09_30_000023_create_sessions_deliberation_table.php.
 */
class SessionDeliberationControllerTest extends TestCase
{
    use CaptureExportExcel;
    use RefreshDatabase;

    /** export() respecte les mêmes filtres que index() (même requeteListe() partagée, voir le docblock d'export()). */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE, 'description' => 'Session licence']);
        SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_MASTER, 'description' => 'Session master']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.sessions-deliberation.export', ['cycle' => SessionDeliberation::CYCLE_MASTER]))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame('Session master', $lignes[0][4]);
    }

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.sessions-deliberation.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_pv',
            'name' => 'Sans droits',
            'permissions' => ['sessions_deliberation' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_pv']);

        $this->actingAs($utilisateur)->get(route('gestion.sessions-deliberation.index'))->assertForbidden();
    }

    /** Licence : notes d'information + 6 semestres (L1S1...L3S6, voir App\Support\Semestres). */
    public function test_la_creation_dune_session_licence_precree_les_bonnes_cases(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionDeliberation::SESSION_PREMIERE,
                'cycle' => SessionDeliberation::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
                'description' => 'Délibération L1 à L3',
            ])
            ->assertRedirect();

        $session = SessionDeliberation::query()->firstOrFail();

        $this->assertSame(8, $session->fichiers()->count()); // 2 notes + 6 semestres
        $this->assertSame(
            [1, 2, 3, 4, 5, 6],
            $session->fichiers()->where('role', SessionDeliberationFichier::ROLE_SEMESTRE)->orderBy('numero')->pluck('numero')->all()
        );
        $this->assertSame(2, $session->fichiers()->whereIn('role', array_keys(SessionDeliberationFichier::ROLES_NOTES))->count());
    }

    /** Master : notes d'information + 4 semestres (M1S7...M2S10). */
    public function test_la_creation_dune_session_master_precree_les_bons_semestres(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionDeliberation::SESSION_DEUXIEME,
                'cycle' => SessionDeliberation::CYCLE_MASTER,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionDeliberation::query()->firstOrFail();

        $this->assertSame(
            [7, 8, 9, 10],
            $session->fichiers()->where('role', SessionDeliberationFichier::ROLE_SEMESTRE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    /** Doctorat : nombre d'années de thèse réglable depuis Administration > Paramètres (demande explicite du client). */
    public function test_la_creation_dune_session_doctorat_respecte_le_reglage_du_nombre_dannees_de_these(): void
    {
        Parametre::actuel()->fill(['nombre_annees_these_doctorat' => 4])->save();

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionDeliberation::SESSION_PREMIERE,
                'cycle' => SessionDeliberation::CYCLE_DOCTORAT,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionDeliberation::query()->firstOrFail();

        $this->assertSame(
            [1, 2, 3, 4],
            $session->fichiers()->where('role', SessionDeliberationFichier::ROLE_ANNEE_THESE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    public function test_impossible_de_creer_deux_fois_la_meme_combinaison(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();
        SessionDeliberation::factory()->create([
            'annee_academique_id' => $annee->id,
            'session' => SessionDeliberation::SESSION_PREMIERE,
            'cycle' => SessionDeliberation::CYCLE_LICENCE,
            'filiere_id' => $filiere->id,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionDeliberation::SESSION_PREMIERE,
                'cycle' => SessionDeliberation::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('filiere_id');

        $this->assertSame(1, SessionDeliberation::query()->count());
    }

    /** Le fichier déposé devient un vrai App\Models\Document, réutilisant le type/sous-type PV_DELIBERATION déjà existant. */
    public function test_deposer_un_fichier_sur_une_case_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_DELIBERATION']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Licence']);
        $niveau = Niveau::factory()->create(['code' => 'L1']);
        $session = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE]);
        $caseSemestre1 = SessionDeliberationFichier::factory()->create([
            'session_deliberation_id' => $session->id,
            'role' => SessionDeliberationFichier::ROLE_SEMESTRE,
            'numero' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $caseSemestre1->id]), [
                'fichier' => UploadedFile::fake()->image('pv-s1.png'),
            ])
            ->assertRedirect();

        $caseSemestre1->refresh();
        $this->assertNotNull($caseSemestre1->document_id);

        $document = Document::query()->findOrFail($caseSemestre1->document_id);
        $this->assertSame($type->id, $document->type_document_id);
        $this->assertSame($sousType->id, $document->sous_type_document_id);
        $this->assertSame($niveau->id, $document->niveau_id);
        $this->assertSame(1, $document->semestre);
        $this->assertSame($session->filiere_id, $document->filiere_id);
        $this->assertSame($admin->id, $document->uploaded_by);
    }

    /** Déposer un nouveau fichier sur une case déjà remplie met l'ancien document à la corbeille (jamais supprimé tout de suite). */
    public function test_remplacer_le_fichier_dune_case_met_lancien_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        // Le type PV_DELIBERATION doit exister (voir TypeDocumentSeeder en
        // production) — sans lui, uploaderFichier() refuse le dépôt (voir son
        // abort_if() dédié) plutôt que de planter avec une erreur SQL brute.
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION']);

        $admin = User::factory()->admin()->create();
        $session = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE]);
        $case = SessionDeliberationFichier::factory()->create([
            'session_deliberation_id' => $session->id,
            'role' => SessionDeliberationFichier::ROLE_NOTE_ETUDIANTS,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $case->id]), [
                'fichier' => UploadedFile::fake()->image('v1.png'),
            ])
            ->assertRedirect();

        $case->refresh();
        $ancienDocumentId = $case->document_id;

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $case->id]), [
                'fichier' => UploadedFile::fake()->image('v2.png'),
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertNotSame($ancienDocumentId, $case->document_id);
        $this->assertNotNull(Document::onlyTrashed()->find($ancienDocumentId));
    }

    /** Retirer le fichier d'une case la vide sans la supprimer — elle reste disponible pour un nouveau dépôt (upload progressif demandé par le client). */
    public function test_supprimer_le_fichier_dune_case_la_vide_sans_la_supprimer(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION']);

        $admin = User::factory()->admin()->create();
        $session = SessionDeliberation::factory()->create();
        $case = SessionDeliberationFichier::factory()->create(['session_deliberation_id' => $session->id]);

        $this->actingAs($admin)->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $case->id]), [
            'fichier' => UploadedFile::fake()->image('note.png'),
        ])->assertRedirect();
        $case->refresh();
        $documentId = $case->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-deliberation.fichiers.destroy', ['sessionDeliberation' => $session->id, 'fichier' => $case->id]))
            ->assertRedirect();

        $case->refresh();
        $this->assertNull($case->document_id);
        $this->assertDatabaseHas('session_deliberation_fichiers', ['id' => $case->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Une case d'une AUTRE session ne doit jamais être modifiable via l'URL d'une session différente. */
    public function test_une_case_dune_autre_session_ne_peut_pas_etre_modifiee(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $sessionA = SessionDeliberation::factory()->create();
        $sessionB = SessionDeliberation::factory()->create();
        $caseDeA = SessionDeliberationFichier::factory()->create(['session_deliberation_id' => $sessionA->id]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $sessionB->id, 'fichier' => $caseDeA->id]), [
                'fichier' => UploadedFile::fake()->image('intrus.png'),
            ])
            ->assertNotFound();
    }

    public function test_mise_a_la_corbeille_et_restauration_dune_session(): void
    {
        $admin = User::factory()->admin()->create();
        $session = SessionDeliberation::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-deliberation.destroy', $session->id))
            ->assertRedirect();

        $this->assertNotNull($session->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.restore', $session->id))
            ->assertRedirect();

        $this->assertNull($session->fresh()->deleted_at);
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés aux cases. */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION']);

        $admin = User::factory()->admin()->create();
        $session = SessionDeliberation::factory()->create();
        $case = SessionDeliberationFichier::factory()->create(['session_deliberation_id' => $session->id]);

        $this->actingAs($admin)->post(route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $case->id]), [
            'fichier' => UploadedFile::fake()->image('note.png'),
        ])->assertRedirect();
        $documentId = $case->refresh()->document_id;

        $this->actingAs($admin)->delete(route('gestion.sessions-deliberation.destroy', $session->id));

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-deliberation.force-destroy', $session->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions_deliberation', ['id' => $session->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    public function test_seule_la_description_est_modifiable_apres_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $session = SessionDeliberation::factory()->create();

        $this->actingAs($admin)
            ->put(route('gestion.sessions-deliberation.update', $session->id), [
                'description' => 'Nouvelle description',
            ])
            ->assertRedirect();

        $this->assertSame('Nouvelle description', $session->fresh()->description);
    }

    /**
     * Actions groupées (sélection multiple, voir
     * resources/views/gestion/sessions-deliberation/index.blade.php) — MÊME
     * PRINCIPE que tests/Feature/EtudiantControllerTest.php pour
     * mettreCorbeilleGroupee()/restaurerGroupe()/forceDestroyGroupe(), sur
     * demande explicite du client ("permettre de faire des sélections pour
     * des actions sur les tableaux différents").
     */
    public function test_mise_a_la_corbeille_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionA = SessionDeliberation::factory()->create();
        $sessionB = SessionDeliberation::factory()->create();
        $sessionHorsSelection = SessionDeliberation::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.corbeille-groupee'), [
                'sessions' => [$sessionA->id, $sessionB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($sessionA->fresh()->deleted_at);
        $this->assertNotNull($sessionB->fresh()->deleted_at);
        $this->assertNull($sessionHorsSelection->fresh()->deleted_at);
    }

    public function test_restauration_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionA = SessionDeliberation::factory()->create();
        $sessionB = SessionDeliberation::factory()->create();
        $sessionA->delete();
        $sessionB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.corbeille.restaurer-groupe'), [
                'sessions' => [$sessionA->id, $sessionB->id],
            ])
            ->assertRedirect();

        $this->assertNull($sessionA->fresh()->deleted_at);
        $this->assertNull($sessionB->fresh()->deleted_at);
    }

    /** Même raisonnement que forceDestroy() individuel : les documents encore attachés sont mis à la corbeille avant de supprimer les sessions. */
    public function test_suppression_definitive_groupee_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionA = SessionDeliberation::factory()->create();
        $sessionB = SessionDeliberation::factory()->create();
        $documentA = Document::factory()->create();
        $documentB = Document::factory()->create();
        SessionDeliberationFichier::factory()->create(['session_deliberation_id' => $sessionA->id, 'document_id' => $documentA->id]);
        SessionDeliberationFichier::factory()->create(['session_deliberation_id' => $sessionB->id, 'document_id' => $documentB->id]);
        $sessionA->delete();
        $sessionB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.corbeille.force-destroy-groupe'), [
                'sessions' => [$sessionA->id, $sessionB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions_deliberation', ['id' => $sessionA->id]);
        $this->assertDatabaseMissing('sessions_deliberation', ['id' => $sessionB->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));
        $this->assertNotNull(Document::onlyTrashed()->find($documentB->id));
    }

    /** Une sélection vide (ou absente) doit être refusée côté serveur, pas seulement empêchée par le JS du bouton. */
    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.corbeille-groupee'), ['sessions' => []])
            ->assertRedirect()
            ->assertSessionHasErrors('sessions');
    }

    /** Sans le droit "delete" sur "sessions_deliberation", les trois actions groupées restent interdites — même droit que les actions individuelles. */
    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'sessions_deliberation_lecture_seule',
            'name' => 'Lecture seule PV délibération',
            'permissions' => ['sessions_deliberation' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sessions_deliberation_lecture_seule']);
        $session = SessionDeliberation::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.sessions-deliberation.corbeille-groupee'), ['sessions' => [$session->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.sessions-deliberation.corbeille.restaurer-groupe'), ['sessions' => [$session->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.sessions-deliberation.corbeille.force-destroy-groupe'), ['sessions' => [$session->id]])
            ->assertForbidden();
    }
}
