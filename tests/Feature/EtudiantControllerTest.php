<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use App\Models\EtudiantInscription;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\TypePieceEtudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CaptureExportExcel;
use Tests\Concerns\ConstruitFichierExcelUploade;
use Tests\TestCase;

/**
 * App\Http\Controllers\EtudiantController — même principe général que
 * tests/Feature/SessionDeliberationControllerTest.php (voir son docblock)
 * pour les pièces du dossier, DEPUIS LA REFONTE "liste fixe" (voir
 * TypePieceEtudiantControllerTest pour la synchronisation rétroactive) : une
 * case pré-créée se remplit/se vide, elle ne se crée/supprime plus à la
 * volée. En plus de ça, l'historique d'inscriptions propre à cet écran (voir
 * le docblock de la classe testée) : l'identité de l'étudiant (matricule,
 * nom, prénoms) est stable, son niveau/sa filière changent chaque année
 * académique.
 */
class EtudiantControllerTest extends TestCase
{
    use CaptureExportExcel;
    use ConstruitFichierExcelUploade;
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.etudiants.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_la_liste(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_etudiants',
            'name' => 'Sans droits',
            'permissions' => ['etudiants' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_etudiants']);

        $this->actingAs($utilisateur)->get(route('gestion.etudiants.index'))->assertForbidden();
    }

    /** La création d'un dossier crée l'étudiant ET sa première inscription en une seule fois — voir EtudiantController::store(). */
    public function test_la_creation_dun_dossier_cree_letudiant_et_sa_premiere_inscription(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create(['code' => 'L1']);
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.store'), [
                'matricule' => 'ING-2026-001',
                'nom' => 'Ouedraogo',
                'prenoms' => 'Aïcha',
                'annee_academique_id' => $annee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $etudiant = Etudiant::query()->where('matricule', 'ING-2026-001')->firstOrFail();

        $this->assertSame(1, $etudiant->inscriptions()->count());
        $inscription = $etudiant->inscriptions()->firstOrFail();
        $this->assertSame($annee->id, $inscription->annee_academique_id);
        $this->assertSame($niveau->id, $inscription->niveau_id);
        $this->assertSame($filiere->id, $inscription->filiere_id);
    }

    /**
     * La création pré-crée aussi, VIDES, les cases des types actifs — une
     * case "identité" (rattachée à l'étudiant) pour un type non récurrent,
     * une case "par année" (rattachée à la première inscription) pour un
     * type récurrent — voir EtudiantController::creerCasesIdentite()/
     * creerCasesInscription(). Un type inactif ne reçoit aucune case.
     */
    public function test_la_creation_precree_les_cases_des_types_actifs(): void
    {
        $typeIdentite = TypePieceEtudiant::factory()->create(['recurrente' => false, 'actif' => true]);
        $typeAnnee = TypePieceEtudiant::factory()->create(['recurrente' => true, 'actif' => true]);
        $typeInactif = TypePieceEtudiant::factory()->create(['recurrente' => false, 'actif' => false]);

        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.store'), [
                'matricule' => 'ING-2026-002',
                'nom' => 'Sawadogo',
                'prenoms' => 'Ibrahim',
                'annee_academique_id' => $annee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $etudiant = Etudiant::query()->where('matricule', 'ING-2026-002')->firstOrFail();
        $inscription = $etudiant->inscriptions()->firstOrFail();

        $this->assertSame(2, $etudiant->fichiers()->count());
        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typeIdentite->id,
            'etudiant_inscription_id' => null,
            'document_id' => null,
        ]);
        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typeAnnee->id,
            'etudiant_inscription_id' => $inscription->id,
            'document_id' => null,
        ]);
        $this->assertDatabaseMissing('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typeInactif->id,
        ]);
    }

    public function test_la_validation_exige_les_champs_obligatoires(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['matricule', 'nom', 'prenoms', 'annee_academique_id', 'niveau_id', 'filiere_id']);

        $this->assertSame(0, Etudiant::query()->count());
    }

    public function test_le_matricule_doit_etre_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Etudiant::factory()->create(['matricule' => 'ING-2026-001']);
        $annee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.store'), [
                'matricule' => 'ING-2026-001',
                'nom' => 'Sawadogo',
                'prenoms' => 'Ibrahim',
                'annee_academique_id' => $annee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('matricule');

        $this->assertSame(1, Etudiant::query()->count());
    }

    /** Un étudiant n'a qu'une seule inscription par année académique — voir le docblock de la migration etudiant_inscriptions. */
    public function test_impossible_dajouter_deux_inscriptions_pour_la_meme_annee(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create();
        $etudiant = Etudiant::factory()->create();
        EtudiantInscription::factory()->create(['etudiant_id' => $etudiant->id, 'annee_academique_id' => $annee->id]);
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.inscriptions.store', $etudiant->id), [
                'annee_academique_id' => $annee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('annee_academique_id');

        $this->assertSame(1, $etudiant->inscriptions()->count());
    }

    /** Ajouter l'inscription d'une NOUVELLE année illustre la progression d'un étudiant (demande explicite du client). */
    public function test_ajouter_une_inscription_pour_une_nouvelle_annee(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $nouvelleAnnee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create(['code' => 'L2']);
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.inscriptions.store', $etudiant->id), [
                'annee_academique_id' => $nouvelleAnnee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $etudiant->inscriptions()->where('annee_academique_id', $nouvelleAnnee->id)->count());
    }

    /** Ajouter une inscription pré-crée aussi, VIDES, les cases des types récurrents actifs — pour CETTE année précise. */
    public function test_ajouter_une_inscription_precree_les_cases_des_types_recurrents(): void
    {
        $typeAnnee = TypePieceEtudiant::factory()->create(['recurrente' => true, 'actif' => true]);
        $typeIdentite = TypePieceEtudiant::factory()->create(['recurrente' => false, 'actif' => true]);

        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $nouvelleAnnee = AnneeAcademique::factory()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.inscriptions.store', $etudiant->id), [
                'annee_academique_id' => $nouvelleAnnee->id,
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertRedirect();

        $inscription = $etudiant->inscriptions()->where('annee_academique_id', $nouvelleAnnee->id)->firstOrFail();

        $this->assertDatabaseHas('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typeAnnee->id,
            'etudiant_inscription_id' => $inscription->id,
        ]);
        // Le type "identité" n'est jamais rattaché à une inscription.
        $this->assertDatabaseMissing('etudiant_fichiers', [
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typeIdentite->id,
            'etudiant_inscription_id' => $inscription->id,
        ]);
    }

    /** Corrige le niveau/la filière d'une inscription déjà existante — l'année académique ne se change pas ici. */
    public function test_modifier_une_inscription_existante(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $inscription = EtudiantInscription::factory()->create(['etudiant_id' => $etudiant->id]);
        $nouveauNiveau = Niveau::factory()->create();
        $nouvelleFiliere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->put(route('gestion.etudiants.inscriptions.update', ['etudiant' => $etudiant->id, 'inscription' => $inscription->id]), [
                'niveau_id' => $nouveauNiveau->id,
                'filiere_id' => $nouvelleFiliere->id,
            ])
            ->assertRedirect();

        $inscription->refresh();
        $this->assertSame($nouveauNiveau->id, $inscription->niveau_id);
        $this->assertSame($nouvelleFiliere->id, $inscription->filiere_id);
    }

    /** Retirer une inscription la supprime ENTIÈREMENT, avec ses cases récurrentes (cascade en base) — et met les documents encore attachés à la corbeille. */
    public function test_retirer_une_inscription(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $inscription = EtudiantInscription::factory()->create(['etudiant_id' => $etudiant->id]);
        $document = Document::factory()->create();
        $fichier = EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'etudiant_inscription_id' => $inscription->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.inscriptions.destroy', ['etudiant' => $etudiant->id, 'inscription' => $inscription->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('etudiant_inscriptions', ['id' => $inscription->id]);
        // La case récurrente part avec l'inscription (cascadeOnDelete en base).
        $this->assertDatabaseMissing('etudiant_fichiers', ['id' => $fichier->id]);
        // Le document qui y était attaché est mis à la corbeille, pas perdu.
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
    }

    /** Une inscription d'un AUTRE étudiant ne doit jamais être modifiable/retirable via l'URL d'un étudiant différent. */
    public function test_une_inscription_dun_autre_etudiant_ne_peut_pas_etre_modifiee_ou_retiree(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiantA = Etudiant::factory()->create();
        $etudiantB = Etudiant::factory()->create();
        $inscriptionDeA = EtudiantInscription::factory()->create(['etudiant_id' => $etudiantA->id]);
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create();

        $this->actingAs($admin)
            ->put(route('gestion.etudiants.inscriptions.update', ['etudiant' => $etudiantB->id, 'inscription' => $inscriptionDeA->id]), [
                'niveau_id' => $niveau->id,
                'filiere_id' => $filiere->id,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.inscriptions.destroy', ['etudiant' => $etudiantB->id, 'inscription' => $inscriptionDeA->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('etudiant_inscriptions', ['id' => $inscriptionDeA->id]);
    }

    /** Déposer un fichier sur une case VIDE la remplit — la case elle-même existe déjà (voir creerCasesIdentite()), elle n'est jamais créée ici. */
    public function test_deposer_un_fichier_sur_une_case_cree_un_document_et_remplit_la_case(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'DOSSIER_ETUDIANT']);
        $typePiece = TypePieceEtudiant::factory()->create(['libelle' => 'CNIB']);
        $etudiant = Etudiant::factory()->create();
        $fichier = EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'type_piece_etudiant_id' => $typePiece->id,
            'document_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.fichiers.store', ['etudiant' => $etudiant->id, 'fichier' => $fichier->id]), [
                'fichier' => UploadedFile::fake()->image('cnib.png'),
            ])
            ->assertRedirect();

        $fichier->refresh();
        $this->assertNotNull($fichier->document_id);

        $document = Document::query()->findOrFail($fichier->document_id);
        $this->assertSame($type->id, $document->type_document_id);
        $this->assertSame($admin->id, $document->uploaded_by);
        $this->assertStringContainsString('CNIB', $document->description);
    }

    /** Déposer un fichier sur une case déjà remplie REMPLACE le document et met l'ancien à la corbeille — la case reste la même ligne. */
    public function test_remplacer_le_fichier_dune_case_met_lancien_document_a_la_corbeille(): void
    {
        Storage::fake('documents');
        TypeDocument::factory()->create(['code' => 'DOSSIER_ETUDIANT']);

        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $ancienDocument = Document::factory()->create();
        $fichier = EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'document_id' => $ancienDocument->id,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.fichiers.store', ['etudiant' => $etudiant->id, 'fichier' => $fichier->id]), [
                'fichier' => UploadedFile::fake()->image('nouveau.png'),
            ])
            ->assertRedirect();

        $fichier->refresh();
        $this->assertNotSame($ancienDocument->id, $fichier->document_id);
        $this->assertNotNull(Document::onlyTrashed()->find($ancienDocument->id));
    }

    /** Retirer le fichier d'une case la VIDE (document à la corbeille) sans supprimer la case elle-même — elle reste disponible pour un nouveau dépôt. */
    public function test_retirer_le_fichier_dune_case_la_vide_sans_la_supprimer(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $document = Document::factory()->create();
        $fichier = EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.fichiers.destroy', ['etudiant' => $etudiant->id, 'fichier' => $fichier->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('etudiant_fichiers', ['id' => $fichier->id, 'document_id' => null]);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
    }

    /** Une case d'un AUTRE étudiant ne doit jamais être remplie/vidée via l'URL d'un étudiant différent. */
    public function test_une_case_dun_autre_etudiant_ne_peut_pas_etre_remplie_ou_retiree(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $etudiantA = Etudiant::factory()->create();
        $etudiantB = Etudiant::factory()->create();
        $caseDeA = EtudiantFichier::factory()->create(['etudiant_id' => $etudiantA->id]);

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.fichiers.store', ['etudiant' => $etudiantB->id, 'fichier' => $caseDeA->id]), [
                'fichier' => UploadedFile::fake()->image('piece.png'),
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.fichiers.destroy', ['etudiant' => $etudiantB->id, 'fichier' => $caseDeA->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('etudiant_fichiers', ['id' => $caseDeA->id]);
    }

    public function test_mise_a_la_corbeille_et_restauration_dun_dossier(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.destroy', $etudiant->id))
            ->assertRedirect();

        $this->assertNotNull($etudiant->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.restore', $etudiant->id))
            ->assertRedirect();

        $this->assertNull($etudiant->fresh()->deleted_at);
    }

    /** La suppression définitive emporte aussi (à la corbeille) les documents encore attachés aux cases du dossier. */
    public function test_suppression_definitive_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $document = Document::factory()->create();
        EtudiantFichier::factory()->create([
            'etudiant_id' => $etudiant->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin)->delete(route('gestion.etudiants.destroy', $etudiant->id));

        $this->actingAs($admin)
            ->delete(route('gestion.etudiants.force-destroy', $etudiant->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('etudiants', ['id' => $etudiant->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($document->id));
    }

    /** update() ne touche QUE l'identité (matricule/nom/prénoms) — les inscriptions se gèrent depuis show(), pas ce formulaire. */
    public function test_seule_lidentite_est_modifiable_via_update(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create();
        $inscription = EtudiantInscription::factory()->create(['etudiant_id' => $etudiant->id]);

        $this->actingAs($admin)
            ->put(route('gestion.etudiants.update', $etudiant->id), [
                'matricule' => 'ING-CORRIGE-01',
                'nom' => 'Nouveau nom',
                'prenoms' => 'Nouveaux prénoms',
            ])
            ->assertRedirect();

        $etudiant->refresh();
        $this->assertSame('ING-CORRIGE-01', $etudiant->matricule);
        $this->assertSame('Nouveau nom', $etudiant->nom);
        // L'inscription existante n'a pas bougé.
        $this->assertDatabaseHas('etudiant_inscriptions', ['id' => $inscription->id, 'niveau_id' => $inscription->niveau_id]);
    }

    /** L'unicité du matricule ignore l'étudiant lui-même lors d'une modification (sinon il ne pourrait jamais rien enregistrer d'autre). */
    public function test_le_matricule_reste_unique_meme_lors_dune_modification(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = Etudiant::factory()->create(['matricule' => 'ING-0001']);
        $autreEtudiant = Etudiant::factory()->create(['matricule' => 'ING-0002']);

        // Garder le même matricule (ne se change pas réellement) doit rester valide.
        $this->actingAs($admin)
            ->put(route('gestion.etudiants.update', $etudiant->id), [
                'matricule' => 'ING-0001',
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Reprendre le matricule d'un AUTRE étudiant doit être refusé.
        $this->actingAs($admin)
            ->put(route('gestion.etudiants.update', $etudiant->id), [
                'matricule' => 'ING-0002',
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('matricule');
    }

    /**
     * Actions groupées (sélection multiple, voir
     * resources/views/gestion/etudiants/index.blade.php) — MÊME PRINCIPE que
     * tests/Feature/DocumentControllerTest.php pour
     * mettreCorbeilleGroupee()/restaurerGroupe()/forceDestroyGroupe(), sur
     * demande explicite du client ("permettre de faire des sélections pour
     * des actions sur les tableaux différents").
     */
    public function test_mise_a_la_corbeille_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiantA = Etudiant::factory()->create();
        $etudiantB = Etudiant::factory()->create();
        $etudiantHorsSelection = Etudiant::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.corbeille-groupee'), [
                'etudiants' => [$etudiantA->id, $etudiantB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($etudiantA->fresh()->deleted_at);
        $this->assertNotNull($etudiantB->fresh()->deleted_at);
        $this->assertNull($etudiantHorsSelection->fresh()->deleted_at);
    }

    public function test_restauration_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiantA = Etudiant::factory()->create();
        $etudiantB = Etudiant::factory()->create();
        $etudiantA->delete();
        $etudiantB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.corbeille.restaurer-groupe'), [
                'etudiants' => [$etudiantA->id, $etudiantB->id],
            ])
            ->assertRedirect();

        $this->assertNull($etudiantA->fresh()->deleted_at);
        $this->assertNull($etudiantB->fresh()->deleted_at);
    }

    /** Même raisonnement que forceDestroy() individuel : les documents encore attachés sont mis à la corbeille avant de supprimer les dossiers. */
    public function test_suppression_definitive_groupee_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiantA = Etudiant::factory()->create();
        $etudiantB = Etudiant::factory()->create();
        $documentA = Document::factory()->create();
        $documentB = Document::factory()->create();
        EtudiantFichier::factory()->create(['etudiant_id' => $etudiantA->id, 'document_id' => $documentA->id]);
        EtudiantFichier::factory()->create(['etudiant_id' => $etudiantB->id, 'document_id' => $documentB->id]);
        $etudiantA->delete();
        $etudiantB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.corbeille.force-destroy-groupe'), [
                'etudiants' => [$etudiantA->id, $etudiantB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('etudiants', ['id' => $etudiantA->id]);
        $this->assertDatabaseMissing('etudiants', ['id' => $etudiantB->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));
        $this->assertNotNull(Document::onlyTrashed()->find($documentB->id));
    }

    /** Une sélection vide (ou absente) doit être refusée côté serveur, pas seulement empêchée par le JS du bouton (voir le docblock de la classe). */
    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.corbeille-groupee'), ['etudiants' => []])
            ->assertRedirect()
            ->assertSessionHasErrors('etudiants');
    }

    /** Sans le droit "delete" sur "etudiants", les trois actions groupées restent interdites — même droit que les actions individuelles. */
    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'etudiants_lecture_seule',
            'name' => 'Lecture seule étudiants',
            'permissions' => ['etudiants' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'etudiants_lecture_seule']);
        $etudiant = Etudiant::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.etudiants.corbeille-groupee'), ['etudiants' => [$etudiant->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.etudiants.corbeille.restaurer-groupe'), ['etudiants' => [$etudiant->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.etudiants.corbeille.force-destroy-groupe'), ['etudiants' => [$etudiant->id]])
            ->assertForbidden();
    }

    /**
     * export() respecte EXACTEMENT les mêmes filtres que index() (même
     * requeteListe() partagée, voir le docblock d'export()) — vérifié ici
     * avec le filtre "recherche" (matricule).
     */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create(['libelle' => '2025-2026']);
        $niveau = Niveau::factory()->create(['libelle' => 'Licence 1']);
        $filiere = Filiere::factory()->create(['libelle' => 'Informatique']);

        $etudiantVisible = Etudiant::factory()->create(['matricule' => 'ING-VISIBLE']);
        EtudiantInscription::factory()->create([
            'etudiant_id' => $etudiantVisible->id,
            'annee_academique_id' => $annee->id,
            'niveau_id' => $niveau->id,
            'filiere_id' => $filiere->id,
        ]);
        Etudiant::factory()->create(['matricule' => 'ING-AUTRE']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.etudiants.export', ['recherche' => 'VISIBLE']))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame(['ING-VISIBLE', $etudiantVisible->nom, $etudiantVisible->prenoms, '2025-2026', 'Licence 1', 'Informatique'], array_slice($lignes[0], 0, 6));
        // assertEquals (pas assertSame) : un aller-retour par un fichier .xlsx peut renvoyer un entier en float (0.0).
        $this->assertEquals(0, $lignes[0][6]);
    }

    public function test_le_formulaire_dimport_est_accessible(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.etudiants.import.formulaire'))
            ->assertOk();
    }

    /** Le modèle d'import a EXACTEMENT les mêmes colonnes, dans le même ordre, que ce que import() attend (voir son docblock). */
    public function test_le_modele_dimport_est_telechargeable(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.etudiants.import.modele'))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));
        $this->assertCount(1, $lignes);
        $this->assertSame(['ETU-0001', 'Exemple', 'Prénom Exemple', '2025-2026', 'L1', 'INFO'], $lignes[0]);
    }

    /** Matricule inconnu → dossier ET première inscription créés, exactement comme store() — voir le docblock d'import(). */
    public function test_import_cree_un_nouvel_etudiant(): void
    {
        $admin = User::factory()->admin()->create();
        AnneeAcademique::factory()->create(['libelle' => '2025-2026']);
        Niveau::factory()->create(['code' => 'L1']);
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade(
            ['Matricule', 'Nom', 'Prénoms', 'Année académique', 'Niveau', 'Filière'],
            [['ING-NOUVEAU', 'Sawadogo', 'Ibrahim', '2025-2026', 'L1', 'INFO']]
        );

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.import'), ['fichier' => $fichier])
            ->assertRedirect(route('gestion.etudiants.index'))
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 1 && $resultat['mis_a_jour'] === 0 && $resultat['erreurs'] === []);

        $etudiant = Etudiant::query()->where('matricule', 'ING-NOUVEAU')->firstOrFail();
        $this->assertSame('Sawadogo', $etudiant->nom);
        $this->assertSame(1, $etudiant->inscriptions()->count());
    }

    /** Matricule connu → identité mise à jour, inscription de l'année ajoutée SI elle n'existe pas déjà. */
    public function test_import_met_a_jour_un_etudiant_existant(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create(['libelle' => '2025-2026']);
        $niveau = Niveau::factory()->create(['code' => 'L2']);
        $filiere = Filiere::factory()->create(['code' => 'INFO']);
        $etudiant = Etudiant::factory()->create(['matricule' => 'ING-EXISTANT', 'nom' => 'Ancien nom']);
        EtudiantInscription::factory()->create([
            'etudiant_id' => $etudiant->id,
            'annee_academique_id' => $annee->id,
            'niveau_id' => Niveau::factory()->create(['code' => 'L1'])->id,
            'filiere_id' => $filiere->id,
        ]);

        $fichier = $this->fichierExcelUploade(
            ['Matricule', 'Nom', 'Prénoms', 'Année académique', 'Niveau', 'Filière'],
            [['ING-EXISTANT', 'Nouveau nom', 'Ibrahim', '2025-2026', 'L2', 'INFO']]
        );

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && $resultat['mis_a_jour'] === 1);

        $etudiant->refresh();
        $this->assertSame('Nouveau nom', $etudiant->nom);
        // Toujours une seule inscription pour cette année académique (mise à jour, pas dupliquée).
        $this->assertSame(1, $etudiant->inscriptions()->where('annee_academique_id', $annee->id)->count());
        $this->assertSame($niveau->id, $etudiant->inscriptions()->where('annee_academique_id', $annee->id)->first()->niveau_id);
    }

    /** Une ligne invalide (ici : code niveau inconnu) est signalée et sautée, elle ne bloque jamais le reste du fichier. */
    public function test_import_signale_une_ligne_invalide_sans_bloquer_les_autres(): void
    {
        $admin = User::factory()->admin()->create();
        AnneeAcademique::factory()->create(['libelle' => '2025-2026']);
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade(
            ['Matricule', 'Nom', 'Prénoms', 'Année académique', 'Niveau', 'Filière'],
            [
                ['ING-INVALIDE', 'Sawadogo', 'Ibrahim', '2025-2026', 'INEXISTANT', 'INFO'],
                ['ING-VALIDE', 'Ouédraogo', 'Aïcha', '2025-2026', 'L1', 'INFO'],
            ]
        );
        Niveau::factory()->create(['code' => 'L1']);

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', function ($resultat) {
                return $resultat['crees'] === 1
                    && count($resultat['erreurs']) === 1
                    && str_starts_with($resultat['erreurs'][0], 'Ligne 2 :');
            });

        $this->assertDatabaseMissing('etudiants', ['matricule' => 'ING-INVALIDE']);
        $this->assertDatabaseHas('etudiants', ['matricule' => 'ING-VALIDE']);
    }

    /** Un matricule qui correspond à un dossier dans la corbeille n'est JAMAIS restauré silencieusement par un import — voir le docblock d'import(). */
    public function test_import_refuse_un_matricule_dans_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        AnneeAcademique::factory()->create(['libelle' => '2025-2026']);
        Niveau::factory()->create(['code' => 'L1']);
        Filiere::factory()->create(['code' => 'INFO']);
        $etudiant = Etudiant::factory()->create(['matricule' => 'ING-CORBEILLE']);
        $etudiant->delete();

        $fichier = $this->fichierExcelUploade(
            ['Matricule', 'Nom', 'Prénoms', 'Année académique', 'Niveau', 'Filière'],
            [['ING-CORBEILLE', 'Sawadogo', 'Ibrahim', '2025-2026', 'L1', 'INFO']]
        );

        $this->actingAs($admin)
            ->post(route('gestion.etudiants.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && count($resultat['erreurs']) === 1);

        $this->assertNotNull($etudiant->fresh()->deleted_at);
    }
}
