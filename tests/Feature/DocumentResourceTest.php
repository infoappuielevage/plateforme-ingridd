<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentResource\Pages\CreateDocument;
use App\Filament\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_ajouter_un_document(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $type->id,
                'description' => 'Contrat de prestation 2026',
                // UploadedFile::fake()->image() génère un vrai contenu PNG (via GD) : la
                // validation de type de fichier de DocumentResource (acceptedFileTypes)
                // inspecte le contenu réel du fichier, pas seulement son nom/extension —
                // un fake()->create() avec un contenu arbitraire échouerait ce contrôle.
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'description' => 'Contrat de prestation 2026',
            'type_document_id' => $type->id,
            'uploaded_by' => $admin->id,
        ]);
    }

    public function test_un_admin_peut_ajouter_un_document_avec_filiere_niveau_et_annee_academique(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $filiere = Filiere::factory()->create();
        $niveau = Niveau::factory()->create();
        $anneeAcademique = AnneeAcademique::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $type->id,
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'annee_academique_id' => $anneeAcademique->id,
                'fichier' => UploadedFile::fake()->image('releve.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $type->id,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
        ]);
    }

    /**
     * Les options du champ "Semestre" dépendent du niveau choisi (voir
     * App\Support\Semestres, précisé explicitement par le client : L1 =
     * S1/S2, L2 = S3/S4, L3 = S5/S6, M1 = S7/S8, M2 = S9/S10, numérotation
     * continue sur les 5 années) — ici, Licence 2 propose Semestre 3/4.
     */
    public function test_le_semestre_est_enregistre_selon_le_niveau_pour_un_releve_de_notes(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        // Le champ "Semestre" n'apparaît que pour ce type précis — voir
        // DocumentResource::form() ($typeReleveNotesId).
        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);
        $niveauL2 = Niveau::factory()->create(['code' => 'L2', 'libelle' => 'Licence 2']);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeReleve->id,
                'niveau_id' => $niveauL2->id,
                'semestre' => 3,
                'fichier' => UploadedFile::fake()->image('releve.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeReleve->id,
            'niveau_id' => $niveauL2->id,
            'semestre' => 3,
        ]);
    }

    /**
     * Le champ "Sous-type" (voir App\Models\SousTypeDocument) ne s'affiche
     * que si le type sélectionné a au moins un sous-type actif configuré
     * depuis l'administration — sinon il reste absent (dehydrated(false)),
     * même si un id traîne dans l'état du formulaire.
     */
    public function test_le_sous_type_est_enregistre_quand_le_type_en_a(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeBordereau = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousTypeCnss = SousTypeDocument::factory()->create(['type_document_id' => $typeBordereau->id, 'libelle' => 'CNSS']);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeBordereau->id,
                'sous_type_document_id' => $sousTypeCnss->id,
                'fichier' => UploadedFile::fake()->image('bordereau.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeBordereau->id,
            'sous_type_document_id' => $sousTypeCnss->id,
        ]);
    }

    public function test_le_sous_type_est_ignore_pour_un_type_qui_nen_a_pas(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        // Sous-type sans rapport (appartient à un autre type) : forcé dans
        // le formulaire malgré tout, doit être ignoré.
        $autreType = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousTypeSansRapport = SousTypeDocument::factory()->create(['type_document_id' => $autreType->id]);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeContrat->id,
                'sous_type_document_id' => $sousTypeSansRapport->id,
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeContrat->id,
            'sous_type_document_id' => null,
        ]);
    }

    /**
     * Les champs "Titulaire", "N° diplôme", "Date de délivrance", "Mention"
     * et "Statut de retrait" ne sont proposés que pour les diplômes et
     * attestations (voir App\Support\TypesDiplome et DocumentResource) —
     * demande explicite du client pour mieux gérer/suivre ces documents.
     */
    public function test_les_champs_complementaires_sont_enregistres_pour_un_diplome(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeDiplome->id,
                'nom_titulaire' => 'OUEDRAOGO Awa',
                'numero_diplome' => 'DIP-2026-00123',
                'date_delivrance' => '2026-07-15',
                'mention' => 'Bien',
                'fichier' => UploadedFile::fake()->image('diplome.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeDiplome->id,
            'nom_titulaire' => 'OUEDRAOGO Awa',
            'numero_diplome' => 'DIP-2026-00123',
            'mention' => 'Bien',
            'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
        ]);

        $document = Document::query()->where('numero_diplome', 'DIP-2026-00123')->firstOrFail();
        $this->assertSame('2026-07-15', $document->date_delivrance->toDateString());
    }

    public function test_le_nom_du_titulaire_est_obligatoire_pour_un_diplome(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeAttestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeAttestation->id,
                'nom_titulaire' => '',
                'fichier' => UploadedFile::fake()->image('attestation.png'),
            ])
            ->call('create')
            ->assertHasFormErrors(['nom_titulaire' => 'required']);
    }

    /**
     * Le PV de soutenance réutilise `nom_titulaire`/`mention` (partagés avec
     * les diplômes/attestations) et a en plus trois champs qui lui sont
     * propres : thème du mémoire, date de soutenance, membres du jury. Pas
     * de statut de retrait pour ce type (contrairement aux diplômes/
     * attestations) : `numero_diplome`/`statut_retrait` doivent rester
     * absents même si on force une valeur dans le formulaire.
     */
    public function test_les_champs_complementaires_sont_enregistres_pour_un_pv_de_soutenance(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typePvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'champs_soutenance_actifs' => true]);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typePvSoutenance->id,
                'nom_titulaire' => 'KABORE Issa',
                'theme_memoire' => 'Digitalisation des processus administratifs',
                'date_soutenance' => '2026-06-10',
                'membres_jury' => "Pr. OUEDRAOGO Jean (Président)\nDr. SAWADOGO Awa (Rapporteur)",
                'mention' => 'Très bien',
                // Forcé malgré tout : doit être ignoré (dehydrated(false) pour ce type).
                'statut_retrait' => Document::STATUT_RETRAIT_RETIRE,
                'fichier' => UploadedFile::fake()->image('pv-soutenance.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typePvSoutenance->id,
            'nom_titulaire' => 'KABORE Issa',
            'theme_memoire' => 'Digitalisation des processus administratifs',
            'mention' => 'Très bien',
            'numero_diplome' => null,
            'statut_retrait' => null,
        ]);

        $document = Document::query()->where('theme_memoire', 'Digitalisation des processus administratifs')->firstOrFail();
        $this->assertSame('2026-06-10', $document->date_soutenance->toDateString());
        $this->assertStringContainsString('OUEDRAOGO Jean', $document->membres_jury);
    }

    public function test_le_nom_du_titulaire_est_obligatoire_pour_un_pv_de_soutenance(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typePvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'champs_soutenance_actifs' => true]);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typePvSoutenance->id,
                'nom_titulaire' => '',
                'fichier' => UploadedFile::fake()->image('pv-soutenance.png'),
            ])
            ->call('create')
            ->assertHasFormErrors(['nom_titulaire' => 'required']);
    }

    /**
     * Pour un type de document sans rapport (ex. un contrat), ces champs
     * doivent rester absents (dehydrated(false) côté formulaire) : ni
     * obligatoires, ni enregistrés même si une valeur traîne dans l'état du
     * formulaire.
     */
    public function test_les_champs_diplome_sont_ignores_pour_un_autre_type_de_document(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        Livewire::actingAs($admin)
            ->test(CreateDocument::class)
            ->fillForm([
                'type_document_id' => $typeContrat->id,
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeContrat->id,
            'nom_titulaire' => null,
            'statut_retrait' => null,
            'theme_memoire' => null,
        ]);
    }

    /**
     * Le bouton "Marquer comme retiré" (DocumentResource::table()) permet de
     * suivre en un clic qu'un étudiant est venu récupérer son diplôme/son
     * attestation, sans passer par le formulaire de modification.
     */
    public function test_le_bouton_marquer_comme_retire_met_a_jour_le_statut(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $document = Document::factory()->create([
            'type_document_id' => $typeDiplome->id,
            'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
        ]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->callTableAction('marquer_retire', $document);

        $document->refresh();

        $this->assertTrue($document->estRetire());
        $this->assertSame('2026-09-20', $document->date_retrait->toDateString());
        $this->assertSame($admin->id, $document->retrait_enregistre_par_id);
    }

    public function test_le_bouton_annuler_le_retrait_remet_le_statut_a_non_retire(): void
    {
        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $document = Document::factory()->create([
            'type_document_id' => $typeDiplome->id,
            'statut_retrait' => Document::STATUT_RETRAIT_RETIRE,
            'date_retrait' => now(),
            'retrait_enregistre_par_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->callTableAction('annuler_retrait', $document);

        $document->refresh();

        $this->assertFalse($document->estRetire());
        $this->assertNull($document->date_retrait);
        $this->assertNull($document->retrait_enregistre_par_id);
    }

    /**
     * Repasser un diplôme/une attestation à "Non retiré" depuis le
     * formulaire de modification (plutôt que le bouton rapide "Annuler le
     * retrait") doit nettoyer la date et l'auteur du retrait — sinon ces
     * deux colonnes resteraient à leur ancienne valeur, incohérentes avec
     * le nouveau statut (voir EditDocument::mutateFormDataBeforeSave()).
     */
    public function test_repasser_a_non_retire_depuis_le_formulaire_nettoie_la_date_et_lauteur(): void
    {
        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $document = Document::factory()->create([
            'type_document_id' => $typeDiplome->id,
            'nom_titulaire' => 'KABORE Issa',
            'statut_retrait' => Document::STATUT_RETRAIT_RETIRE,
            'date_retrait' => now(),
            'retrait_enregistre_par_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->fillForm(['statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE])
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();

        $this->assertFalse($document->estRetire());
        $this->assertNull($document->date_retrait);
        $this->assertNull($document->retrait_enregistre_par_id);
    }

    public function test_la_liste_des_documents_peut_etre_filtree_par_annee_academique(): void
    {
        $admin = User::factory()->admin()->create();

        $ancienne = AnneeAcademique::factory()->create(['libelle' => '2024-2025']);
        $recente = AnneeAcademique::factory()->create(['libelle' => '2025-2026']);

        $ancien = Document::factory()->create(['description' => 'Ancien', 'annee_academique_id' => $ancienne->id]);
        $recent = Document::factory()->create(['description' => 'Récent', 'annee_academique_id' => $recente->id]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->filterTable('annee_academique_id', $recente->id)
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$ancien]);
    }

    public function test_le_modificateur_est_enregistre_a_la_modification(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $document = Document::factory()->create([
            'type_document_id' => $type->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->fillForm(['description' => 'Description modifiée'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'description' => 'Description modifiée',
            'modifie_par' => $admin->id,
        ]);
    }

    public function test_la_liste_peut_etre_filtree_par_date_d_ajout(): void
    {
        $admin = User::factory()->admin()->create();

        $ancien = Document::factory()->create(['description' => 'Ancien', 'created_at' => now()->subDays(30)]);
        $recent = Document::factory()->create(['description' => 'Récent', 'created_at' => now()]);

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->filterTable('ajoute_entre', ['du' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$ancien]);
    }

    public function test_un_document_supprime_va_a_la_corbeille_sans_perdre_son_fichier(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);

        $document->delete();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertExists('uploads/test.pdf');
    }

    public function test_un_document_de_la_corbeille_peut_etre_restaure(): void
    {
        $document = Document::factory()->create();
        $document->delete();

        $document->restore();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_la_suppression_definitive_efface_le_fichier_physique(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);
        $document->delete();
        Storage::disk('documents')->assertExists('uploads/test.pdf');

        $document->forceDelete();

        Storage::disk('documents')->assertMissing('uploads/test.pdf');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_la_mise_a_la_corbeille_groupee_fonctionne(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $document1 = Document::factory()->create();
        $document2 = Document::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListDocuments::class)
            ->callTableBulkAction('delete', [$document1, $document2]);

        $this->assertSoftDeleted('documents', ['id' => $document1->id]);
        $this->assertSoftDeleted('documents', ['id' => $document2->id]);
    }

    public function test_le_telechargement_est_refuse_sans_droit_de_vue(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);

        \App\Models\Role::query()->create([
            'slug' => 'sans_droits',
            'name' => 'Sans droits',
            'permissions' => ['documents' => ['view' => false]],
        ]);

        $utilisateur = User::factory()->create(['role' => 'sans_droits']);

        $this->actingAs($utilisateur)
            ->get(route('documents.telecharger', $document))
            ->assertForbidden();
    }
}
