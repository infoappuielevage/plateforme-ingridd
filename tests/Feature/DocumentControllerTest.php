<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\DocumentController — remplace App\Filament\Resources\
 * DocumentResource, CorbeilleResource et la page ListDocuments (onglets)
 * (plus de Filament, sur demande du client). Porte à l'identique, en vraies
 * requêtes HTTP plutôt qu'en Livewire::test(), les garanties qu'avaient :
 * tests/Feature/DocumentResourceTest.php (création/modification, champs
 * conditionnels par type, retrait, filtres, corbeille individuelle), tests/
 * Feature/CorbeilleTest.php (accès direct à la corbeille, "Vider la
 * corbeille") et tests/Feature/DocumentListTabsTest.php (onglets par
 * famille — voir App\Support\FamillesDocument, qui n'existait pas côté
 * Filament : ces onglets étaient codés en dur dans ListDocuments::getTabs()).
 *
 * Non repris ici (connu, accepté) : la sélection multiple + suppression
 * groupée qu'avait DocumentResource::table() — même simplification déjà
 * faite pour les référentiels (voir le docblock de
 * App\Http\Controllers\ReferentielController) : chaque document reste
 * modifiable/supprimable individuellement. Facile à ajouter plus tard si le
 * besoin s'en fait sentir.
 */
class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_accede_a_la_liste_des_documents(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('gestion.documents.index'))->assertOk();
    }

    public function test_un_role_sans_permission_ne_peut_pas_voir_les_documents(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_documents',
            'name' => 'Sans droits',
            'permissions' => ['documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_documents']);

        $this->actingAs($utilisateur)->get(route('gestion.documents.index'))->assertForbidden();
    }

    public function test_un_admin_peut_ajouter_un_document(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $type->id,
                'description' => 'Contrat de prestation 2026',
                // UploadedFile::fake()->image() génère un vrai contenu PNG (via GD) :
                // la validation `mimetypes:` inspecte le contenu réel du fichier, pas
                // seulement son nom/extension — un fake()->create() avec un contenu
                // arbitraire échouerait ce contrôle.
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

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

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $type->id,
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'annee_academique_id' => $anneeAcademique->id,
                'fichier' => UploadedFile::fake()->image('releve.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

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
     * S1/S2, L2 = S3/S4... numérotation continue sur les 5 années) — ici,
     * Licence 2 propose Semestre 3/4.
     *
     * Type quelconque (PAS `RELEVE_NOTES` : ce code a désormais son propre
     * écran dédié et n'est plus créable depuis ce formulaire général, voir
     * App\Support\TypesEcranDedie) — le champ "Semestre" est accepté pour
     * n'importe quel type, ce comportement n'est pas spécifique au code.
     */
    public function test_le_semestre_est_enregistre_selon_le_niveau_choisi(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $niveauL2 = Niveau::factory()->create(['code' => 'L2', 'libelle' => 'Licence 2']);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $type->id,
                'niveau_id' => $niveauL2->id,
                'semestre' => 3,
                'fichier' => UploadedFile::fake()->image('releve.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $type->id,
            'niveau_id' => $niveauL2->id,
            'semestre' => 3,
        ]);
    }

    public function test_le_sous_type_est_enregistre_quand_le_type_en_a(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeBordereau = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousTypeCnss = SousTypeDocument::factory()->create(['type_document_id' => $typeBordereau->id, 'libelle' => 'CNSS']);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typeBordereau->id,
                'sous_type_document_id' => $sousTypeCnss->id,
                'fichier' => UploadedFile::fake()->image('bordereau.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeBordereau->id,
            'sous_type_document_id' => $sousTypeCnss->id,
        ]);
    }

    /**
     * Forcé malgré tout dans la requête (un sous-type d'un AUTRE type) : le
     * formulaire (JS) ne propose déjà que les sous-types du type choisi,
     * mais le contrôleur ne fait jamais confiance à ce que le JavaScript a
     * montré/caché — voir la règle Rule::exists(...)->where(...) sur
     * sous_type_document_id dans DocumentController::valider().
     */
    public function test_le_sous_type_dun_autre_type_est_rejete(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $autreType = TypeDocument::factory()->create(['code' => 'BORDEREAU']);
        $sousTypeSansRapport = SousTypeDocument::factory()->create(['type_document_id' => $autreType->id]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typeContrat->id,
                'sous_type_document_id' => $sousTypeSansRapport->id,
                'fichier' => UploadedFile::fake()->image('contrat.png'),
            ])
            ->assertSessionHasErrors('sous_type_document_id');

        $this->assertDatabaseMissing('documents', ['type_document_id' => $typeContrat->id]);
    }

    /**
     * Les champs "Titulaire", "N° diplôme", "Date de délivrance", "Mention"
     * et "Statut de retrait" ne sont proposés dans le formulaire que pour
     * les diplômes/attestations, mais le contrôleur les accepte toujours
     * s'ils sont envoyés (voir le docblock de DocumentController).
     */
    public function test_les_champs_complementaires_sont_enregistres_pour_un_diplome(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typeDiplome->id,
                'nom_titulaire' => 'OUEDRAOGO Awa',
                'numero_diplome' => 'DIP-2026-00123',
                'date_delivrance' => '2026-07-15',
                'mention' => 'Bien',
                'fichier' => UploadedFile::fake()->image('diplome.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typeDiplome->id,
            'nom_titulaire' => 'OUEDRAOGO Awa',
            'numero_diplome' => 'DIP-2026-00123',
            'mention' => 'Bien',
        ]);

        $document = Document::query()->where('numero_diplome', 'DIP-2026-00123')->firstOrFail();
        $this->assertSame('2026-07-15', $document->date_delivrance->toDateString());
    }

    /**
     * `nom_titulaire` n'était encore que "nullable" dans DocumentController::
     * valider() — un oubli par rapport à l'ancien formulaire Filament (requis
     * conditionnellement selon le type) découvert en portant ce test : corrigé
     * dans le même changement (voir le commentaire sur $titulaireExige).
     */
    public function test_le_nom_du_titulaire_est_obligatoire_pour_un_diplome(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeAttestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typeAttestation->id,
                'nom_titulaire' => '',
                'fichier' => UploadedFile::fake()->image('attestation.png'),
            ])
            ->assertSessionHasErrors('nom_titulaire');
    }

    /**
     * `champs_soutenance_actifs` est un drapeau configurable sur N'IMPORTE
     * QUEL type (voir App\Support\TypesPvSoutenance) — testé ici sur un
     * type quelconque, PAS `PV_SOUTENANCE` : ce code a désormais son propre
     * écran dédié et n'est plus créable depuis ce formulaire général (voir
     * App\Support\TypesEcranDedie).
     */
    public function test_le_nom_du_titulaire_est_obligatoire_quand_les_champs_soutenance_sont_actifs(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typePvSoutenance = TypeDocument::factory()->create(['champs_soutenance_actifs' => true]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typePvSoutenance->id,
                'nom_titulaire' => '',
                'fichier' => UploadedFile::fake()->image('pv-soutenance.png'),
            ])
            ->assertSessionHasErrors('nom_titulaire');
    }

    public function test_les_champs_complementaires_sont_enregistres_quand_les_champs_soutenance_sont_actifs(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typePvSoutenance = TypeDocument::factory()->create(['champs_soutenance_actifs' => true]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $typePvSoutenance->id,
                'nom_titulaire' => 'KABORE Issa',
                'theme_memoire' => 'Digitalisation des processus administratifs',
                'date_soutenance' => '2026-06-10',
                'membres_jury' => "Pr. OUEDRAOGO Jean (Président)\nDr. SAWADOGO Awa (Rapporteur)",
                'mention' => 'Très bien',
                'fichier' => UploadedFile::fake()->image('pv-soutenance.png'),
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'type_document_id' => $typePvSoutenance->id,
            'nom_titulaire' => 'KABORE Issa',
            'theme_memoire' => 'Digitalisation des processus administratifs',
            'mention' => 'Très bien',
        ]);

        $document = Document::query()->where('theme_memoire', 'Digitalisation des processus administratifs')->firstOrFail();
        $this->assertSame('2026-06-10', $document->date_soutenance->toDateString());
        $this->assertStringContainsString('OUEDRAOGO Jean', $document->membres_jury);
    }

    // --- Types à écran dédié (voir App\Support\TypesEcranDedie) : PV de
    // soutenance, PV de délibération, Relevés de notes ont chacun leur
    // propre écran (PvSoutenanceController/SessionDeliberationController/
    // SessionReleveController) — ce formulaire général ne doit plus pouvoir
    // en créer, pour éviter un document orphelin sans étudiant/session
    // associé (nettoyage demandé par le client, "plus de doublons").

    /** @return iterable<string, array{0: string}> */
    public static function codesAvecEcranDedie(): iterable
    {
        yield 'PV de soutenance' => ['PV_SOUTENANCE'];
        yield 'PV de délibération' => ['PV_DELIBERATION'];
        yield 'Relevés de notes' => ['RELEVE_NOTES'];
    }

    /**
     * Ce type continue légitimement d'apparaître AILLEURS sur la page (menu
     * "Documents > Types de documents" de la sidebar, qui liste tous les
     * types actifs quel que soit l'écran concerné — voir
     * MenuDocumentsTest/MenuTypesDocumentsTest, qui verrouillent ce
     * comportement, et le docblock d'App\Support\MenuDocuments : "deux
     * usages différents, pas une duplication"). Seule l'OFFRE DE CRÉATION
     * doit l'exclure (voir DocumentController::donneesFormulaire()) — la
     * vérification porte donc précisément sur le <select> du formulaire,
     * pas sur la page entière.
     */
    /** @dataProvider codesAvecEcranDedie */
    public function test_le_type_dun_ecran_dedie_nest_plus_propose_a_la_creation(string $code): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => $code, 'libelle' => 'Libellé '.$code]);

        $reponse = $this->actingAs($admin)->get(route('gestion.documents.create'))->assertOk();

        preg_match('/<select name="type_document_id".*?<\/select>/s', $reponse->getContent(), $correspondances);

        $this->assertNotEmpty($correspondances, 'Le <select> "type_document_id" est introuvable dans la page.');
        $this->assertStringNotContainsString('Libellé '.$code, $correspondances[0]);
    }

    /** @dataProvider codesAvecEcranDedie */
    public function test_creer_un_document_avec_un_type_a_ecran_dedie_est_rejete(string $code): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => $code]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.store'), [
                'type_document_id' => $type->id,
                'fichier' => UploadedFile::fake()->image('document.png'),
            ])
            ->assertSessionHasErrors('type_document_id');

        $this->assertDatabaseMissing('documents', ['type_document_id' => $type->id]);
    }

    /**
     * Un document déjà classé sous un de ces types (déposé avant ce
     * changement, ou via l'ancien raccourci Secrétariat à l'époque) doit
     * rester modifiable normalement — seule la CRÉATION est bloquée.
     */
    public function test_modifier_un_document_existant_dun_type_a_ecran_dedie_reste_possible(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);
        $document = Document::factory()->create(['type_document_id' => $type->id, 'description' => 'Avant modification']);

        $this->actingAs($admin)
            ->put(route('gestion.documents.update', $document->id), [
                'type_document_id' => $type->id,
                'description' => 'Après modification',
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'description' => 'Après modification',
        ]);
    }

    public function test_le_bouton_marquer_comme_retire_met_a_jour_le_statut(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));

        $admin = User::factory()->admin()->create();
        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME', 'champs_diplome_actifs' => true]);
        $document = Document::factory()->create([
            'type_document_id' => $typeDiplome->id,
            'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.documents.basculer-retrait', $document))
            ->assertRedirect();

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

        $this->actingAs($admin)
            ->post(route('gestion.documents.basculer-retrait', $document))
            ->assertRedirect();

        $document->refresh();

        $this->assertFalse($document->estRetire());
        $this->assertNull($document->date_retrait);
        $this->assertNull($document->retrait_enregistre_par_id);
    }

    /**
     * Repasser un diplôme/une attestation à "Non retiré" depuis le
     * formulaire de modification (plutôt que le bouton rapide) doit nettoyer
     * la date et l'auteur du retrait — sinon ces deux colonnes resteraient à
     * leur ancienne valeur, incohérentes avec le nouveau statut.
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

        $this->actingAs($admin)
            ->put(route('gestion.documents.update', $document), [
                'type_document_id' => $typeDiplome->id,
                'nom_titulaire' => 'KABORE Issa',
                'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $document->refresh();

        $this->assertFalse($document->estRetire());
        $this->assertNull($document->date_retrait);
        $this->assertNull($document->retrait_enregistre_par_id);
    }

    public function test_le_modificateur_est_enregistre_a_la_modification(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $document = Document::factory()->create(['type_document_id' => $type->id]);

        $this->actingAs($admin)
            ->put(route('gestion.documents.update', $document), [
                'type_document_id' => $type->id,
                'description' => 'Description modifiée',
            ])
            ->assertRedirect(route('gestion.documents.index'));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'description' => 'Description modifiée',
            'modifie_par' => $admin->id,
        ]);
    }

    public function test_la_liste_des_documents_peut_etre_filtree_par_annee_academique(): void
    {
        $admin = User::factory()->admin()->create();

        $ancienne = AnneeAcademique::factory()->create(['libelle' => '2024-2025']);
        $recente = AnneeAcademique::factory()->create(['libelle' => '2025-2026']);

        Document::factory()->create(['nom_original' => 'ancien.pdf', 'annee_academique_id' => $ancienne->id]);
        Document::factory()->create(['nom_original' => 'recent.pdf', 'annee_academique_id' => $recente->id]);

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['annee_academique_id' => $recente->id]))
            ->assertOk()
            ->assertSee('recent.pdf')
            ->assertDontSee('ancien.pdf');
    }

    public function test_un_document_supprime_va_a_la_corbeille_sans_perdre_son_fichier(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);

        $this->actingAs($admin)
            ->delete(route('gestion.documents.destroy', $document))
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertExists('uploads/test.pdf');
    }

    public function test_un_document_de_la_corbeille_peut_etre_restaure(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create();
        $document->delete();

        $this->actingAs($admin)
            ->post(route('gestion.documents.restore', $document->id))
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_la_suppression_definitive_efface_le_fichier_physique(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);
        $document->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.documents.force-destroy', $document->id))
            ->assertRedirect();

        Storage::disk('documents')->assertMissing('uploads/test.pdf');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_le_telechargement_est_refuse_sans_droit_de_vue(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);

        Role::query()->create([
            'slug' => 'sans_droits_telechargement',
            'name' => 'Sans droits',
            'permissions' => ['documents' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_telechargement']);

        $this->actingAs($utilisateur)
            ->get(route('documents.telecharger', $document))
            ->assertForbidden();
    }

    /**
     * "Voir" (route documents.voir) — pendant de "Télécharger", mais en
     * Content-Disposition: inline plutôt que "attachment" (voir routes/
     * web.php), pour ouvrir un aperçu dans un nouvel onglet au lieu de
     * forcer un téléchargement. Demande explicite du client.
     */
    public function test_apercu_dun_document_renvoie_le_contenu_en_ligne(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf', 'nom_original' => 'test.pdf']);

        $reponse = $this->actingAs($admin)->get(route('documents.voir', $document));

        $reponse->assertOk();
        $this->assertStringContainsString('inline', $reponse->headers->get('Content-Disposition'));
    }

    public function test_lapercu_est_refuse_sans_droit_de_vue(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);

        Role::query()->create([
            'slug' => 'sans_droits_apercu',
            'name' => 'Sans droits',
            'permissions' => ['documents' => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_apercu']);

        $this->actingAs($utilisateur)
            ->get(route('documents.voir', $document))
            ->assertForbidden();
    }

    /** Comme le téléchargement, l'aperçu doit fonctionner depuis la corbeille (withTrashed(), voir routes/web.php). */
    public function test_lapercu_fonctionne_pour_un_document_de_la_corbeille(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/test.pdf', 'contenu');

        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['chemin' => 'uploads/test.pdf']);
        $document->delete();

        $this->actingAs($admin)
            ->get(route('documents.voir', $document->id))
            ->assertOk();
    }

    // --- Corbeille : accès direct depuis le menu (voir ?corbeille=1) + "Vider la corbeille" ---
    // Remplace tests/Feature/CorbeilleTest.php (App\Filament\Resources\CorbeilleResource, retiré).

    public function test_la_corbeille_naffiche_que_les_documents_supprimes(): void
    {
        $admin = User::factory()->admin()->create();

        Document::factory()->create(['nom_original' => 'actif.pdf']);
        $corbeille = Document::factory()->create(['nom_original' => 'corbeille.pdf']);
        $corbeille->delete();

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['corbeille' => 1]))
            ->assertOk()
            ->assertSee('corbeille.pdf')
            ->assertDontSee('actif.pdf');
    }

    public function test_le_bouton_vider_la_corbeille_supprime_tout_definitivement(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/a.pdf', 'a');
        Storage::disk('documents')->put('uploads/b.pdf', 'b');

        $admin = User::factory()->admin()->create();

        $document1 = Document::factory()->create(['chemin' => 'uploads/a.pdf']);
        $document2 = Document::factory()->create(['chemin' => 'uploads/b.pdf']);
        $documentActif = Document::factory()->create();

        $document1->delete();
        $document2->delete();

        $this->actingAs($admin)
            ->delete(route('gestion.documents.corbeille.vider'))
            ->assertRedirect(route('gestion.documents.index', ['corbeille' => 1]));

        $this->assertDatabaseMissing('documents', ['id' => $document1->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document2->id]);
        Storage::disk('documents')->assertMissing('uploads/a.pdf');
        Storage::disk('documents')->assertMissing('uploads/b.pdf');

        // Un document actif (pas corbeillé) n'est jamais concerné.
        $this->assertDatabaseHas('documents', ['id' => $documentActif->id]);
    }

    public function test_le_bouton_vider_la_corbeille_est_masque_quand_la_corbeille_est_vide(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->create(); // actif, pas corbeillé

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['corbeille' => 1]))
            ->assertOk()
            ->assertDontSee('Vider la corbeille');
    }

    public function test_vider_la_corbeille_est_reserve_a_qui_peut_supprimer_des_documents(): void
    {
        Storage::fake('documents');

        Role::query()->create([
            'slug' => 'lecteur_sans_suppression_corbeille',
            'name' => 'Lecteur sans suppression',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_suppression_corbeille']);

        $document = Document::factory()->create();
        $document->delete();

        $this->actingAs($utilisateur)
            ->delete(route('gestion.documents.corbeille.vider'))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    // --- Sélection multiple / actions groupées ---
    // Remplace, côté Blade, les DeleteBulkAction/RestoreBulkAction/
    // ForceDeleteBulkAction de App\Filament\Resources\DocumentResource
    // (Filament, retiré) : sélectionner plusieurs documents à la fois
    // (cases à cocher, voir index.blade.php) plutôt qu'un par un.

    public function test_la_mise_a_la_corbeille_groupee_affecte_uniquement_les_documents_selectionnes(): void
    {
        $admin = User::factory()->admin()->create();

        $document1 = Document::factory()->create();
        $document2 = Document::factory()->create();
        $documentNonSelectionne = Document::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille-groupee'), [
                'documents' => [$document1->id, $document2->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $document1->id]);
        $this->assertSoftDeleted('documents', ['id' => $document2->id]);
        $this->assertDatabaseHas('documents', ['id' => $documentNonSelectionne->id, 'deleted_at' => null]);
    }

    public function test_la_mise_a_la_corbeille_groupee_ignore_un_id_deja_corbeille(): void
    {
        $admin = User::factory()->admin()->create();

        $document = Document::factory()->create();
        $document->delete();

        // Un id déjà corbeillé glissé dans la sélection ne doit provoquer
        // aucune erreur : le scope global SoftDeletes l'exclut de lui-même.
        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille-groupee'), [
                'documents' => [$document->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_la_mise_a_la_corbeille_groupee_exige_au_moins_un_document(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille-groupee'), ['documents' => []])
            ->assertSessionHasErrors('documents');
    }

    public function test_la_mise_a_la_corbeille_groupee_est_reservee_a_qui_peut_supprimer_des_documents(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_sans_suppression_groupee',
            'name' => 'Lecteur sans suppression',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_suppression_groupee']);

        $document = Document::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.documents.corbeille-groupee'), ['documents' => [$document->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_la_restauration_groupee_naffecte_que_les_documents_selectionnes(): void
    {
        $admin = User::factory()->admin()->create();

        $document1 = Document::factory()->create();
        $document2 = Document::factory()->create();
        $documentNonSelectionne = Document::factory()->create();
        $document1->delete();
        $document2->delete();
        $documentNonSelectionne->delete();

        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille.restaurer-groupe'), [
                'documents' => [$document1->id, $document2->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['id' => $document1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('documents', ['id' => $document2->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('documents', ['id' => $documentNonSelectionne->id]);
    }

    public function test_la_restauration_groupee_est_reservee_a_qui_peut_supprimer_des_documents(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_sans_restauration_groupee',
            'name' => 'Lecteur sans restauration',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_restauration_groupee']);

        $document = Document::factory()->create();
        $document->delete();

        $this->actingAs($utilisateur)
            ->post(route('gestion.documents.corbeille.restaurer-groupe'), ['documents' => [$document->id]])
            ->assertForbidden();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_la_suppression_definitive_groupee_naffecte_que_les_documents_selectionnes(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('uploads/a.pdf', 'a');
        Storage::disk('documents')->put('uploads/b.pdf', 'b');

        $admin = User::factory()->admin()->create();

        $document1 = Document::factory()->create(['chemin' => 'uploads/a.pdf']);
        $document2 = Document::factory()->create(['chemin' => 'uploads/b.pdf']);
        $documentNonSelectionne = Document::factory()->create();
        $document1->delete();
        $document2->delete();
        $documentNonSelectionne->delete();

        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille.force-destroy-groupe'), [
                'documents' => [$document1->id, $document2->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $document1->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document2->id]);
        Storage::disk('documents')->assertMissing('uploads/a.pdf');
        Storage::disk('documents')->assertMissing('uploads/b.pdf');

        // Toujours corbeillé, pas concerné par la sélection : ni supprimé de
        // la base, ni restauré.
        $this->assertSoftDeleted('documents', ['id' => $documentNonSelectionne->id]);
    }

    public function test_la_suppression_definitive_groupee_ignore_un_id_actif_non_corbeille(): void
    {
        $admin = User::factory()->admin()->create();

        // Un id actif (jamais corbeillé) glissé dans la sélection — par
        // exemple une requête forgée — ne doit jamais être supprimé
        // définitivement : seuls les documents réellement dans la corbeille
        // sont concernés (Document::onlyTrashed()).
        $document = Document::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.documents.corbeille.force-destroy-groupe'), [
                'documents' => [$document->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_la_suppression_definitive_groupee_est_reservee_a_qui_peut_supprimer_des_documents(): void
    {
        Storage::fake('documents');

        Role::query()->create([
            'slug' => 'lecteur_sans_suppression_definitive_groupee',
            'name' => 'Lecteur sans suppression définitive',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_suppression_definitive_groupee']);

        $document = Document::factory()->create();
        $document->delete();

        $this->actingAs($utilisateur)
            ->post(route('gestion.documents.corbeille.force-destroy-groupe'), ['documents' => [$document->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    /** Les cases à cocher (et donc la barre d'actions groupées) ne doivent apparaître qu'à qui a le droit de supprimer — sans ce droit, aucune des 3 actions groupées ne lui est accessible. */
    public function test_les_cases_a_cocher_de_selection_sont_masquees_sans_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'lecteur_sans_suppression_cases',
            'name' => 'Lecteur sans suppression',
            'permissions' => ['documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'lecteur_sans_suppression_cases']);

        Document::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.documents.index'))
            ->assertOk()
            ->assertDontSee('case-selection-document', escape: false);
    }

    // --- Onglets par famille (voir App\Support\FamillesDocument) ---
    // Remplace tests/Feature/DocumentListTabsTest.php (App\Filament\Resources\
    // DocumentResource\Pages\ListDocuments::getTabs(), retiré).

    public function test_longlet_academique_ne_montre_que_les_types_academiques(): void
    {
        $admin = User::factory()->admin()->create();

        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);
        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        Document::factory()->create(['type_document_id' => $typeReleve->id, 'nom_original' => 'releve.pdf']);
        Document::factory()->create(['type_document_id' => $typeContrat->id, 'nom_original' => 'contrat.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['onglet' => 'academique']))
            ->assertOk()
            ->assertSee('releve.pdf')
            ->assertDontSee('contrat.pdf');
    }

    public function test_longlet_diplomes_attestations_ne_montre_que_ces_types(): void
    {
        $admin = User::factory()->admin()->create();

        $typeDiplome = TypeDocument::factory()->create(['code' => 'DIPLOME']);
        $typePvSoutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE']);

        Document::factory()->create(['type_document_id' => $typeDiplome->id, 'nom_original' => 'diplome.pdf']);
        Document::factory()->create(['type_document_id' => $typePvSoutenance->id, 'nom_original' => 'pv.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['onglet' => 'diplomes_attestations']))
            ->assertOk()
            ->assertSee('diplome.pdf')
            ->assertDontSee('pv.pdf');
    }

    public function test_longlet_tous_montre_tous_les_types(): void
    {
        $admin = User::factory()->admin()->create();

        $typeContrat = TypeDocument::factory()->create(['code' => 'CONTRAT']);
        $typeReleve = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES']);

        Document::factory()->create(['type_document_id' => $typeContrat->id, 'nom_original' => 'contrat.pdf']);
        Document::factory()->create(['type_document_id' => $typeReleve->id, 'nom_original' => 'releve.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['onglet' => 'tous']))
            ->assertOk()
            ->assertSee('contrat.pdf')
            ->assertSee('releve.pdf');
    }

    /** Un onglet inconnu dans l'URL (lien externe, favori obsolète...) retombe silencieusement sur "tous" plutôt qu'une erreur 404/500. */
    public function test_un_onglet_inconnu_retombe_sur_tous(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->create(['nom_original' => 'visible.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.documents.index', ['onglet' => 'onglet_qui_nexiste_pas']))
            ->assertOk()
            ->assertSee('visible.pdf');
    }
}
