<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\ChampSupplementaire;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\SessionReleveController — miroir de
 * tests/Feature/SessionDeliberationControllerTest.php (voir son docblock),
 * "même chose que pour le PV de délibération" sur demande explicite du
 * client. Changent : le type de document réutilisé (RELEVE_NOTES au lieu de
 * PV_DELIBERATION), les noms de routes/tables (sessions-releves /
 * session_releve_fichiers), et l'absence de cases "Note d'information"
 * (demande explicite du client — voir SessionReleveController::creerCasesVides()) :
 * un relevé de notes n'a que des cases numérotées (semestre ou année de thèse).
 */
class SessionReleveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.sessions-releves.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_releves',
            'name' => 'Sans droits',
            'permissions' => ['sessions_releves' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_releves']);

        $this->actingAs($utilisateur)->get(route('gestion.sessions-releves.index'))->assertForbidden();
    }

    /** Licence : 6 semestres (L1S1...L3S6, voir App\Support\Semestres) — pas de cases "Note d'information" (demande explicite du client). */
    public function test_la_creation_dune_session_licence_precree_les_bonnes_cases(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionReleve::SESSION_PREMIERE,
                'cycle' => SessionReleve::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
                'description' => 'Relevés L1 à L3',
            ])
            ->assertRedirect();

        $session = SessionReleve::query()->firstOrFail();

        $this->assertSame(6, $session->fichiers()->count());
        $this->assertSame(
            [1, 2, 3, 4, 5, 6],
            $session->fichiers()->where('role', SessionReleveFichier::ROLE_SEMESTRE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    /** Master : 4 semestres (M1S7...M2S10). */
    public function test_la_creation_dune_session_master_precree_les_bons_semestres(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionReleve::SESSION_DEUXIEME,
                'cycle' => SessionReleve::CYCLE_MASTER,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionReleve::query()->firstOrFail();

        $this->assertSame(
            [7, 8, 9, 10],
            $session->fichiers()->where('role', SessionReleveFichier::ROLE_SEMESTRE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    /** Doctorat : nombre d'années de thèse réglable depuis Administration > Paramètres — réglage PARTAGÉ avec le PV de délibération. */
    public function test_la_creation_dune_session_doctorat_respecte_le_reglage_du_nombre_dannees_de_these(): void
    {
        Parametre::actuel()->fill(['nombre_annees_these_doctorat' => 4])->save();

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionReleve::SESSION_PREMIERE,
                'cycle' => SessionReleve::CYCLE_DOCTORAT,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionReleve::query()->firstOrFail();

        $this->assertSame(
            [1, 2, 3, 4],
            $session->fichiers()->where('role', SessionReleveFichier::ROLE_ANNEE_THESE)->orderBy('numero')->pluck('numero')->all()
        );
    }

    public function test_impossible_de_creer_deux_fois_la_meme_combinaison(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();
        SessionReleve::factory()->create([
            'annee_academique_id' => $annee->id,
            'session' => SessionReleve::SESSION_PREMIERE,
            'cycle' => SessionReleve::CYCLE_LICENCE,
            'filiere_id' => $filiere->id,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionReleve::SESSION_PREMIERE,
                'cycle' => SessionReleve::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('filiere_id');

        $this->assertSame(1, SessionReleve::query()->count());
    }

    /** Le fichier déposé devient un vrai App\Models\Document, réutilisant le type/sous-type RELEVE_NOTES déjà existant. */
    public function test_deposer_un_fichier_sur_une_case_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);
        $sousType = SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Licence']);
        $niveau = Niveau::factory()->create(['code' => 'L1']);
        $session = SessionReleve::factory()->create(['cycle' => SessionReleve::CYCLE_LICENCE]);
        $caseSemestre1 = SessionReleveFichier::factory()->create([
            'session_releve_id' => $session->id,
            'role' => SessionReleveFichier::ROLE_SEMESTRE,
            'numero' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $session->id, 'fichier' => $caseSemestre1->id]), [
                'fichier' => UploadedFile::fake()->image('releve-s1.png'),
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
        // Le type RELEVE_NOTES doit exister (voir SousTypeDocumentSeeder en
        // production) — sans lui, uploaderFichier() refuse le dépôt (voir son
        // abort_if() dédié) plutôt que de planter avec une erreur SQL brute.
        TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        $admin = User::factory()->admin()->create();
        $session = SessionReleve::factory()->create(['cycle' => SessionReleve::CYCLE_LICENCE]);
        $case = SessionReleveFichier::factory()->create([
            'session_releve_id' => $session->id,
            'role' => SessionReleveFichier::ROLE_SEMESTRE,
            'numero' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $session->id, 'fichier' => $case->id]), [
                'fichier' => UploadedFile::fake()->image('v1.png'),
            ])
            ->assertRedirect();

        $case->refresh();
        $ancienDocumentId = $case->document_id;

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $session->id, 'fichier' => $case->id]), [
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
        TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        $admin = User::factory()->admin()->create();
        $session = SessionReleve::factory()->create();
        $case = SessionReleveFichier::factory()->create(['session_releve_id' => $session->id]);

        $this->actingAs($admin)->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $session->id, 'fichier' => $case->id]), [
            'fichier' => UploadedFile::fake()->image('note.png'),
        ])->assertRedirect();
        $case->refresh();
        $documentId = $case->document_id;

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-releves.fichiers.destroy', ['sessionReleve' => $session->id, 'fichier' => $case->id]))
            ->assertRedirect();

        $case->refresh();
        $this->assertNull($case->document_id);
        $this->assertDatabaseHas('session_releve_fichiers', ['id' => $case->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    /** Une case d'une AUTRE session ne doit jamais être modifiable via l'URL d'une session différente. */
    public function test_une_case_dune_autre_session_ne_peut_pas_etre_modifiee(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $sessionA = SessionReleve::factory()->create();
        $sessionB = SessionReleve::factory()->create();
        $caseDeA = SessionReleveFichier::factory()->create(['session_releve_id' => $sessionA->id]);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $sessionB->id, 'fichier' => $caseDeA->id]), [
                'fichier' => UploadedFile::fake()->image('intrus.png'),
            ])
            ->assertNotFound();
    }

    public function test_mise_a_la_corbeille_et_restauration_dune_session(): void
    {
        $admin = User::factory()->admin()->create();
        $session = SessionReleve::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-releves.destroy', $session->id))
            ->assertRedirect();

        $this->assertNotNull($session->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.restore', $session->id))
            ->assertRedirect();

        $this->assertNull($session->fresh()->deleted_at);
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés aux cases. */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        $admin = User::factory()->admin()->create();
        $session = SessionReleve::factory()->create();
        $case = SessionReleveFichier::factory()->create(['session_releve_id' => $session->id]);

        $this->actingAs($admin)->post(route('gestion.sessions-releves.fichiers.store', ['sessionReleve' => $session->id, 'fichier' => $case->id]), [
            'fichier' => UploadedFile::fake()->image('note.png'),
        ])->assertRedirect();
        $documentId = $case->refresh()->document_id;

        $this->actingAs($admin)->delete(route('gestion.sessions-releves.destroy', $session->id));

        $this->actingAs($admin)
            ->delete(route('gestion.sessions-releves.force-destroy', $session->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions_releves', ['id' => $session->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentId));
    }

    public function test_seule_la_description_est_modifiable_apres_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $session = SessionReleve::factory()->create();

        $this->actingAs($admin)
            ->put(route('gestion.sessions-releves.update', $session->id), [
                'description' => 'Nouvelle description',
            ])
            ->assertRedirect();

        $this->assertSame('Nouvelle description', $session->fresh()->description);
    }

    /**
     * Un champ supplémentaire actif et applicable (voir
     * App\Models\ChampSupplementaire, ajouté depuis Référentiels > Champs
     * supplémentaires) doit apparaître directement sur toute NOUVELLE session
     * créée après lui — la synchronisation RÉTROACTIVE sur les sessions déjà
     * existantes est testée séparément dans ChampSupplementaireControllerTest.
     */
    public function test_une_nouvelle_session_recoit_automatiquement_les_champs_supplementaires_applicables(): void
    {
        ChampSupplementaire::factory()->create([
            'pour_releve' => true,
            'pour_pv' => false,
            'cycles' => [SessionReleve::CYCLE_LICENCE],
        ]);
        // Champ pour un autre cycle : ne doit jamais apparaître ici.
        ChampSupplementaire::factory()->create([
            'pour_releve' => true,
            'cycles' => [SessionReleve::CYCLE_DOCTORAT],
        ]);
        // Champ inactif : ne doit jamais apparaître ici non plus.
        ChampSupplementaire::factory()->create([
            'pour_releve' => true,
            'actif' => false,
            'cycles' => [SessionReleve::CYCLE_LICENCE],
        ]);

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-releves.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionReleve::SESSION_PREMIERE,
                'cycle' => SessionReleve::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionReleve::query()->firstOrFail();

        $this->assertSame(1, $session->fichiers()->where('role', SessionReleveFichier::ROLE_SUPPLEMENTAIRE)->count());
    }
}
