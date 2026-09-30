<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\ChampSupplementaire;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\ChampSupplementaireController — référentiel qui
 * permet à l'administration d'ajouter un document propre au PV de
 * délibération et/ou au Relevé de notes sans toucher au code (demande
 * explicite du client). Le point central testé ici est la synchronisation
 * RÉTROACTIVE (synchroniserCasesRetroactives()) : ajouter/modifier un champ
 * doit aussi créer la case correspondante sur les sessions déjà existantes,
 * pas seulement sur les nouvelles — voir aussi
 * SessionDeliberationControllerTest/SessionReleveControllerTest pour le cas
 * des NOUVELLES sessions (creerCasesChampsSupplementaires()).
 */
class ChampSupplementaireControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.champs-supplementaires.index'))->assertOk();
    }

    /** Créer un champ "pour_pv" doit ajouter une case sur chaque session de PV déjà existante dont le cycle correspond. */
    public function test_la_creation_dun_champ_synchronise_les_sessions_pv_existantes(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLicence = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE]);
        $sessionMaster = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_MASTER]);

        $this->actingAs($admin)
            ->post(route('gestion.champs-supplementaires.store'), [
                'libelle' => 'Certificat de scolarité',
                'ordre' => 0,
                'actif' => '1',
                'pour_pv' => '1',
                'cycles' => [SessionDeliberation::CYCLE_LICENCE],
            ])
            ->assertRedirect();

        $champ = ChampSupplementaire::query()->firstOrFail();

        $this->assertDatabaseHas('session_deliberation_fichiers', [
            'session_deliberation_id' => $sessionLicence->id,
            'champ_supplementaire_id' => $champ->id,
            'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
        ]);
        // Le cycle Master n'est pas coché pour ce champ : pas de case créée.
        $this->assertDatabaseMissing('session_deliberation_fichiers', [
            'session_deliberation_id' => $sessionMaster->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** Un champ "pour_releve" ne touche pas aux sessions de PV, et inversement. */
    public function test_un_champ_pour_releve_uniquement_ne_cree_aucune_case_sur_les_pv(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionPv = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE]);
        $sessionReleve = SessionReleve::factory()->create(['cycle' => SessionReleve::CYCLE_LICENCE]);

        $this->actingAs($admin)
            ->post(route('gestion.champs-supplementaires.store'), [
                'libelle' => 'Pièce jointe relevé',
                'ordre' => 0,
                'actif' => '1',
                'pour_releve' => '1',
                'cycles' => [SessionReleve::CYCLE_LICENCE],
            ])
            ->assertRedirect();

        $champ = ChampSupplementaire::query()->firstOrFail();

        $this->assertDatabaseHas('session_releve_fichiers', [
            'session_releve_id' => $sessionReleve->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
        $this->assertDatabaseMissing('session_deliberation_fichiers', [
            'session_deliberation_id' => $sessionPv->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** Modifier un champ pour y ajouter le Relevé (initialement PV seul) synchronise aussi les relevés déjà existants. */
    public function test_la_modification_dun_champ_synchronise_aussi_les_sessions_existantes(): void
    {
        $admin = User::factory()->admin()->create();
        $champ = ChampSupplementaire::factory()->create([
            'pour_pv' => true,
            'pour_releve' => false,
            'cycles' => [SessionDeliberation::CYCLE_LICENCE],
        ]);
        $sessionReleve = SessionReleve::factory()->create(['cycle' => SessionReleve::CYCLE_LICENCE]);

        $this->assertDatabaseMissing('session_releve_fichiers', [
            'session_releve_id' => $sessionReleve->id,
            'champ_supplementaire_id' => $champ->id,
        ]);

        $this->actingAs($admin)
            ->put(route('gestion.champs-supplementaires.update', $champ->id), [
                'libelle' => $champ->libelle,
                'ordre' => $champ->ordre,
                'actif' => '1',
                'pour_pv' => '1',
                'pour_releve' => '1',
                'cycles' => [SessionDeliberation::CYCLE_LICENCE],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('session_releve_fichiers', [
            'session_releve_id' => $sessionReleve->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** Ni PV ni Relevé coché : le formulaire est refusé plutôt que de créer un champ invisible partout. */
    public function test_impossible_de_creer_un_champ_sans_aucun_document_concerne(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.champs-supplementaires.store'), [
                'libelle' => 'Champ fantôme',
                'ordre' => 0,
                'actif' => '1',
                'cycles' => [SessionDeliberation::CYCLE_LICENCE],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('pour_pv');

        $this->assertSame(0, ChampSupplementaire::query()->count());
    }

    /** Une nouvelle session créée APRÈS le champ reçoit directement la case, sans synchronisation manuelle. */
    public function test_une_nouvelle_session_pv_recoit_automatiquement_un_champ_actif_applicable(): void
    {
        ChampSupplementaire::factory()->create([
            'pour_pv' => true,
            'cycles' => [SessionDeliberation::CYCLE_LICENCE],
        ]);

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.sessions-deliberation.store'), [
                'annee_academique_id' => $annee->id,
                'session' => SessionDeliberation::SESSION_PREMIERE,
                'cycle' => SessionDeliberation::CYCLE_LICENCE,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $session = SessionDeliberation::query()->firstOrFail();

        $this->assertSame(1, $session->fichiers()->where('role', SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE)->count());
    }

    /** Mise à la corbeille bloquée tant qu'un document a été déposé sur au moins une case de ce champ. */
    public function test_suppression_bloquee_si_le_champ_est_encore_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $champ = ChampSupplementaire::factory()->create(['pour_pv' => true]);
        $session = SessionDeliberation::factory()->create();
        $document = Document::factory()->create();
        SessionDeliberationFichier::factory()->create([
            'session_deliberation_id' => $session->id,
            'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
            'champ_supplementaire_id' => $champ->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.champs-supplementaires.destroy', $champ->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($champ->fresh()->deleted_at);
    }

    public function test_mise_a_la_corbeille_et_restauration_dun_champ_non_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $champ = ChampSupplementaire::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.champs-supplementaires.destroy', $champ->id))
            ->assertRedirect();

        $this->assertNotNull($champ->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.champs-supplementaires.restore', $champ->id))
            ->assertRedirect();

        $this->assertNull($champ->fresh()->deleted_at);
    }

    /**
     * Un champ "pour_soutenance" synchronise les PV de soutenance déjà
     * existants dont le niveau correspond — y compris "bts", valeur propre
     * au PV de soutenance (voir ChampSupplementaire::cyclesDisponibles() et
     * son docblock : "cycles" est une colonne partagée entre les trois
     * usages, "bts" n'a de sens que pour ce dernier).
     */
    public function test_un_champ_pour_soutenance_synchronise_les_pv_de_soutenance_existants(): void
    {
        $admin = User::factory()->admin()->create();
        $pvBts = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_BTS]);
        $pvMaster = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_MASTER]);

        $this->actingAs($admin)
            ->post(route('gestion.champs-supplementaires.store'), [
                'libelle' => 'Attestation de stage',
                'ordre' => 0,
                'actif' => '1',
                'pour_soutenance' => '1',
                'cycles' => [PvSoutenance::NIVEAU_BTS],
            ])
            ->assertRedirect();

        $champ = ChampSupplementaire::query()->firstOrFail();

        $this->assertDatabaseHas('pv_soutenance_fichiers', [
            'pv_soutenance_id' => $pvBts->id,
            'champ_supplementaire_id' => $champ->id,
            'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
        ]);
        $this->assertDatabaseMissing('pv_soutenance_fichiers', [
            'pv_soutenance_id' => $pvMaster->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** Mise à la corbeille bloquée aussi quand seul un PV de soutenance utilise encore le champ. */
    public function test_suppression_bloquee_si_le_champ_est_encore_utilise_par_un_pv_de_soutenance(): void
    {
        $admin = User::factory()->admin()->create();
        $champ = ChampSupplementaire::factory()->create(['pour_pv' => false, 'pour_soutenance' => true]);
        $pvSoutenance = PvSoutenance::factory()->create();
        $document = Document::factory()->create();
        PvSoutenanceFichier::factory()->create([
            'pv_soutenance_id' => $pvSoutenance->id,
            'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
            'champ_supplementaire_id' => $champ->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.champs-supplementaires.destroy', $champ->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($champ->fresh()->deleted_at);
    }

    public function test_suppression_definitive_dun_champ_non_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $champ = ChampSupplementaire::factory()->create();
        $champ->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.champs-supplementaires.force-destroy', $champ->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('champs_supplementaires', ['id' => $champ->id]);
    }
}
