<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use App\Models\EtudiantInscription;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\TypePieceEtudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\TypePieceEtudiantController — référentiel qui permet
 * à l'administration de définir la liste des pièces attendues dans un
 * dossier étudiant sans toucher au code (demande explicite du client), MÊME
 * PRINCIPE que ChampSupplementaireControllerTest : le point central testé
 * ici est la synchronisation RÉTROACTIVE (synchroniserCasesRetroactives()) —
 * ajouter/réactiver un type doit aussi créer la case correspondante sur les
 * dossiers déjà existants, pas seulement sur les nouveaux (voir
 * EtudiantControllerTest pour le cas des NOUVEAUX dossiers/inscriptions).
 */
class TypePieceEtudiantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.types-pieces-etudiants.index'))->assertOk();
    }

    /** Un type "par dossier" (non récurrent) synchronise une case vide sur chaque étudiant déjà existant. */
    public function test_la_creation_dun_type_non_recurrent_synchronise_les_etudiants_existants(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.store'), [
                'code' => 'CNIB',
                'libelle' => 'CNIB',
                'ordre' => 1,
                'actif' => '1',
                'recurrente' => '0',
            ])
            ->assertRedirect();

        $type = TypePieceEtudiant::query()->firstOrFail();

        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $type->id,
            'etudiant_inscription_id' => null,
            'document_id' => null,
        ]);
    }

    /** Un type "par année académique" (récurrent) synchronise une case vide sur chaque INSCRIPTION déjà existante, pas sur l'étudiant directement. */
    public function test_la_creation_dun_type_recurrent_synchronise_les_inscriptions_existantes(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $inscription = EtudiantInscription::factory()->create(['etudiant_id' => $etudiant->id]);

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.store'), [
                'code' => 'RELEVES_NOTES',
                'libelle' => 'Relevés de notes',
                'ordre' => 1,
                'actif' => '1',
                'recurrente' => '1',
            ])
            ->assertRedirect();

        $type = TypePieceEtudiant::query()->firstOrFail();

        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $type->id,
            'etudiant_inscription_id' => $inscription->id,
            'document_id' => null,
        ]);
    }

    /**
     * Une pièce déposée AVANT cette refonte (ancien libellé libre, voir le
     * docblock d'App\Models\EtudiantFichier) dont le libellé correspond
     * exactement (insensible à la casse) au nouveau type est RATTACHÉE à ce
     * type plutôt que de créer une case vide en double à côté — pour ne
     * jamais faire disparaître une pièce déjà fournie.
     */
    public function test_une_piece_deposee_avant_la_refonte_est_rattachee_au_type_correspondant(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $document = Document::factory()->create();
        $ancienneCase = EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'libelle' => 'cnib',
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.store'), [
                'code' => 'CNIB',
                'libelle' => 'CNIB',
                'ordre' => 1,
                'actif' => '1',
                'recurrente' => '0',
            ])
            ->assertRedirect();

        $type = TypePieceEtudiant::query()->firstOrFail();

        $ancienneCase->refresh();
        $this->assertSame($type->id, $ancienneCase->type_piece_etudiant_id);
        // Aucune case vide en double n'a été créée pour cet étudiant/ce type.
        $this->assertSame(1, EtudiantFichier::query()->where('etudiant_id', $etudiant->id)->where('type_piece_etudiant_id', $type->id)->count());
    }

    /** Un type inactif à la création ne synchronise rien. */
    public function test_un_type_inactif_a_la_creation_ne_synchronise_rien(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.store'), [
                'code' => 'PHOTO_IDENTITE',
                'libelle' => "Photo d'identité",
                'ordre' => 1,
                'actif' => '0',
                'recurrente' => '0',
            ])
            ->assertRedirect();

        $type = TypePieceEtudiant::query()->firstOrFail();

        $this->assertDatabaseMissing('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $type->id,
        ]);
    }

    /** Réactiver un type (restore()) resynchronise, comme à la création. */
    public function test_restaurer_un_type_resynchronise_les_etudiants_existants(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypePieceEtudiant::factory()->create(['recurrente' => false]);
        $type->delete();
        $etudiant = Etudiant::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.restore', $type->id))
            ->assertRedirect();

        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $type->id,
        ]);
    }

    /** Un nouvel étudiant créé APRÈS le type reçoit directement la case, sans synchronisation manuelle (voir EtudiantController::creerCasesIdentite()). */
    public function test_un_nouvel_etudiant_recoit_automatiquement_les_cases_des_types_actifs(): void
    {
        TypePieceEtudiant::factory()->create(['recurrente' => false, 'actif' => true]);

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.store'), [
                'matricule' => 'ING-2026-099',
                'nom' => 'Kaboré',
                'prenoms' => 'Fatimata',
                'annee_academique_id' => $annee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $etudiant = Etudiant::query()->where('matricule', 'ING-2026-099')->firstOrFail();

        $this->assertSame(1, $etudiant->fichiers()->count());
    }

    /** Mise à la corbeille bloquée tant qu'un document a été déposé sur au moins une case de ce type. */
    public function test_suppression_bloquee_si_le_type_est_encore_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypePieceEtudiant::factory()->create();
        $document = Document::factory()->create();
        EtudiantFichier::factory()->create([
            'type_piece_etudiant_id' => $type->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.types-pieces-etudiants.destroy', $type->id))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($type->fresh()->deleted_at);
    }

    public function test_mise_a_la_corbeille_et_restauration_dun_type_non_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypePieceEtudiant::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.types-pieces-etudiants.destroy', $type->id))
            ->assertRedirect();

        $this->assertNotNull($type->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.types-pieces-etudiants.restore', $type->id))
            ->assertRedirect();

        $this->assertNull($type->fresh()->deleted_at);
    }

    public function test_suppression_definitive_dun_type_non_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypePieceEtudiant::factory()->create();
        $type->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.types-pieces-etudiants.force-destroy', $type->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('types_pieces_etudiants', ['id' => $type->id]);
    }

    /** Le code et la portée (recurrente) ne se modifient plus après création. */
    public function test_le_code_et_la_portee_restent_figes_a_la_modification(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypePieceEtudiant::factory()->create(['code' => 'CNIB', 'recurrente' => false, 'libelle' => 'CNIB']);

        $this->actingAs($admin)
            ->put(route('gestion.types-pieces-etudiants.update', $type->id), [
                'code' => 'AUTRE_CODE',
                'libelle' => 'CNIB (mis à jour)',
                'ordre' => 5,
                'actif' => '1',
                'recurrente' => '1',
            ])
            ->assertRedirect();

        $type->refresh();
        $this->assertSame('CNIB', $type->code);
        $this->assertFalse($type->recurrente);
        $this->assertSame('CNIB (mis à jour)', $type->libelle);
        $this->assertSame(5, $type->ordre);
    }
}
