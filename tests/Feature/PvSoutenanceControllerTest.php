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
use Tests\Concerns\CaptureExportExcel;
use Tests\Concerns\ConstruitFichierExcelUploade;
use Tests\TestCase;

/**
 * App\Http\Controllers\PvSoutenanceController — structure dédiée au PV de
 * soutenance (un enregistrement = un étudiant), demandée explicitement par
 * le client à partir d'un exemple de registre papier. Voir le docblock de la
 * migration 2026_10_04_000001_create_pv_soutenances_table.php.
 */
class PvSoutenanceControllerTest extends TestCase
{
    use CaptureExportExcel;
    use ConstruitFichierExcelUploade;
    use RefreshDatabase;

    /**
     * Ligne .xlsx valide pour import() — MÊME ORDRE DE COLONNES que
     * PvSoutenanceController::modeleImport()/import().
     *
     * @return array<int, mixed>
     */
    protected function ligneImportValide(array $surcharge = []): array
    {
        $ligne = [
            'licence', '15/06/2026', '42', 'Ouédraogo', 'Aïcha', '01/01/2000', 'Ouagadougou',
            'PV-2026-001', 'INFO', 'Étude comparative des systèmes de gestion des archives',
            'admis', '14.5', 'bien', 'Pr. Jean Kaboré', '', '', '',
        ];

        foreach ($surcharge as $position => $valeur) {
            $ligne[$position] = $valeur;
        }

        return $ligne;
    }

    protected function entetesImport(): array
    {
        return [
            'Niveau', 'Date de soutenance', "N° d'ordre au registre", 'Nom', 'Prénoms',
            'Date de naissance', 'Lieu de naissance', 'N° PV', 'Filière', 'Thème',
            'Décision du jury', 'Moyenne', 'Mention', 'Président du jury',
            'Directeur de mémoire', 'Rapporteur', 'Maître de stage',
        ];
    }

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

    /** export() respecte les mêmes filtres que index() (même requeteListe() partagée, voir le docblock d'export()). */
    public function test_lexport_respecte_les_filtres_de_la_liste(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_LICENCE, 'numero_pv' => 'PV-LICENCE']);
        PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_MASTER, 'numero_pv' => 'PV-MASTER']);

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.pv-soutenances.export', ['niveau' => PvSoutenance::NIVEAU_LICENCE]))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));

        $this->assertCount(1, $lignes);
        $this->assertSame('PV-LICENCE', $lignes[0][0]);
        $this->assertSame('Licence', $lignes[0][3]);
    }

    public function test_le_formulaire_dimport_est_accessible(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.pv-soutenances.import.formulaire'))
            ->assertOk();
    }

    /** Le modèle d'import a EXACTEMENT les mêmes colonnes, dans le même ordre, que ce que import() attend (voir son docblock). */
    public function test_le_modele_dimport_est_telechargeable(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.pv-soutenances.import.modele'))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));
        $this->assertCount(1, $lignes);
        // assertEquals (pas assertSame) : un aller-retour par un fichier .xlsx
        // renvoie une cellule à l'apparence purement numérique ("42", "14.5")
        // en entier/flottant PHP plutôt qu'en chaîne — même remarque que pour
        // l'export étudiants (voir PvSoutenanceController::import(), qui
        // normalise justement cette valeur avant validation).
        $this->assertEquals($this->ligneImportValide(), $lignes[0]);
    }

    /** N° PV inconnu → nouveau PV créé, avec ses deux cases fixes précréées exactement comme store(). */
    public function test_import_cree_un_nouveau_pv_et_precree_les_cases(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [$this->ligneImportValide()]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertRedirect(route('gestion.pv-soutenances.index'))
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 1 && $resultat['mis_a_jour'] === 0 && $resultat['erreurs'] === []);

        $pv = PvSoutenance::query()->where('numero_pv', 'PV-2026-001')->firstOrFail();
        $this->assertSame('Ouédraogo', $pv->nom);
        $this->assertSame('licence', $pv->niveau);
        $this->assertEquals(14.5, $pv->moyenne);
        $this->assertSame(
            [PvSoutenanceFichier::ROLE_DIPLOME, PvSoutenanceFichier::ROLE_IMAGE_REGISTRE],
            $pv->fichiers()->pluck('role')->all()
        );
    }

    /** N° PV déjà connu → PV existant mis à jour, ses cases/documents déjà déposés ne sont jamais recréés. */
    public function test_import_met_a_jour_un_pv_existant_par_numero_pv(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);
        $pvExistant = PvSoutenance::factory()->create([
            'numero_pv' => 'PV-2026-001',
            'numero_ordre_registre' => '99',
            'niveau' => PvSoutenance::NIVEAU_LICENCE,
            'nom' => 'Ancien nom',
        ]);
        $fichierDocument = $pvExistant->fichiers()->create([
            'role' => PvSoutenanceFichier::ROLE_DIPLOME,
            'document_id' => null,
        ]);

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [$this->ligneImportValide()]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && $resultat['mis_a_jour'] === 1);

        $pvExistant->refresh();
        $this->assertSame('Ouédraogo', $pvExistant->nom);
        $this->assertSame('42', $pvExistant->numero_ordre_registre);
        // La case déjà présente avant l'import n'a pas été dupliquée/recréée.
        $this->assertSame(1, $pvExistant->fichiers()->count());
        $this->assertTrue($pvExistant->fichiers()->whereKey($fichierDocument->id)->exists());
    }

    /** Une ligne invalide (ici : code niveau inconnu) est signalée et sautée, elle ne bloque jamais le reste du fichier. */
    public function test_import_signale_une_ligne_invalide_sans_bloquer_les_autres(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [
            $this->ligneImportValide([0 => 'inexistant', 7 => 'PV-INVALIDE']),
            $this->ligneImportValide([7 => 'PV-VALIDE', 2 => '43']),
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', function ($resultat) {
                return $resultat['crees'] === 1
                    && count($resultat['erreurs']) === 1
                    && str_starts_with($resultat['erreurs'][0], 'Ligne 2 :');
            });

        $this->assertDatabaseMissing('pv_soutenances', ['numero_pv' => 'PV-INVALIDE']);
        $this->assertDatabaseHas('pv_soutenances', ['numero_pv' => 'PV-VALIDE']);
    }

    /** Un N° PV qui correspond à un PV dans la corbeille n'est JAMAIS restauré silencieusement par un import — voir le docblock d'import(). */
    public function test_import_refuse_un_numero_pv_dans_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);
        $pv = PvSoutenance::factory()->create(['numero_pv' => 'PV-2026-001']);
        $pv->delete();

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [$this->ligneImportValide()]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && count($resultat['erreurs']) === 1);

        $this->assertNotNull($pv->fresh()->deleted_at);
    }

    /** Même contrainte qu'à la saisie manuelle (voir valider()) : le N° d'ordre au registre reste unique PAR NIVEAU, y compris entre deux lignes du même fichier. */
    public function test_import_respecte_lunicite_du_numero_dordre_registre_par_niveau(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [
            $this->ligneImportValide([7 => 'PV-UN']),
            $this->ligneImportValide([7 => 'PV-DEUX']),
        ]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 1 && count($resultat['erreurs']) === 1);

        $this->assertDatabaseHas('pv_soutenances', ['numero_pv' => 'PV-UN']);
        $this->assertDatabaseMissing('pv_soutenances', ['numero_pv' => 'PV-DEUX']);
    }

    /** Un classeur Excel réglé en français utilise la virgule comme séparateur décimal (ex. "14,5") — voir le docblock d'import(). */
    public function test_import_accepte_la_virgule_comme_separateur_decimal_pour_la_moyenne(): void
    {
        $admin = User::factory()->admin()->create();
        Filiere::factory()->create(['code' => 'INFO']);

        $fichier = $this->fichierExcelUploade($this->entetesImport(), [$this->ligneImportValide([11 => '14,75'])]);

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.import'), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 1 && $resultat['erreurs'] === []);

        $this->assertEquals(14.75, PvSoutenance::query()->where('numero_pv', 'PV-2026-001')->firstOrFail()->moyenne);
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

    /**
     * Photo de l'étudiant (demande explicite du client, "une image de la
     * personne") — stockée sur le disque `public` comme App\Models\
     * User::$avatar, jamais comme une case de document (voir le docblock de
     * App\Models\PvSoutenance).
     */
    public function test_un_admin_peut_ajouter_une_photo_a_la_creation_dun_pv(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides([
                'photo' => UploadedFile::fake()->image('etudiant.jpg'),
            ]))
            ->assertRedirect();

        $pv = PvSoutenance::query()->firstOrFail();
        $this->assertNotNull($pv->photo);
        Storage::disk('public')->assertExists($pv->photo);
    }

    public function test_un_pv_de_soutenance_peut_etre_cree_sans_photo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides())
            ->assertRedirect();

        $this->assertNull(PvSoutenance::query()->firstOrFail()->photo);
    }

    /** Un SVG est un format texte pouvant contenir du JavaScript — même risque de XSS stockée que pour la photo de profil (voir ProfilControllerTest). */
    public function test_un_fichier_svg_est_refuse_comme_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.store'), $this->donneesValides([
                'photo' => UploadedFile::fake()->create('etudiant.svg', 10, 'image/svg+xml'),
            ]))
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, PvSoutenance::query()->count());
    }

    /**
     * Remplacer la photo doit supprimer l'ancienne (voir
     * PvSoutenanceController::update()) — sinon storage/app/public/
     * pv-soutenances-photos accumule des fichiers orphelins, même raisonnement
     * que ProfilController::update() pour `avatar`.
     */
    public function test_remplacer_la_photo_supprime_lancienne(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $pv->forceFill(['photo' => 'pv-soutenances-photos/ancienne.jpg'])->save();
        Storage::disk('public')->put('pv-soutenances-photos/ancienne.jpg', 'contenu');

        $this->actingAs($admin)
            ->put(route('gestion.pv-soutenances.update', $pv->id), $this->donneesValides([
                'filiere_id' => $pv->filiere_id,
                'numero_ordre_registre' => $pv->numero_ordre_registre,
                'numero_pv' => $pv->numero_pv,
                'niveau' => $pv->niveau,
                'photo' => UploadedFile::fake()->image('nouvelle.jpg'),
            ]))
            ->assertRedirect();

        $pv->refresh();
        $this->assertNotSame('pv-soutenances-photos/ancienne.jpg', $pv->photo);
        Storage::disk('public')->assertMissing('pv-soutenances-photos/ancienne.jpg');
        Storage::disk('public')->assertExists($pv->photo);
    }

    /** Laisser le champ "Photo" vide en modification garde la photo déjà enregistrée — pas d'écrasement silencieux. */
    public function test_laisser_le_champ_photo_vide_en_modification_garde_la_photo_actuelle(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create();
        $pv->forceFill(['photo' => 'pv-soutenances-photos/existante.jpg'])->save();
        Storage::disk('public')->put('pv-soutenances-photos/existante.jpg', 'contenu');

        $this->actingAs($admin)
            ->put(route('gestion.pv-soutenances.update', $pv->id), $this->donneesValides([
                'filiere_id' => $pv->filiere_id,
                'numero_ordre_registre' => $pv->numero_ordre_registre,
                'numero_pv' => $pv->numero_pv,
                'niveau' => $pv->niveau,
            ]))
            ->assertRedirect();

        $this->assertSame('pv-soutenances-photos/existante.jpg', $pv->fresh()->photo);
        Storage::disk('public')->assertExists('pv-soutenances-photos/existante.jpg');
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

    /**
     * La liste est désormais organisée en onglets par niveau (BTS, DTS,
     * Licence, Master, Doctorat) plutôt qu'un filtre "Niveau" à liste
     * déroulante — demande explicite du client. L'onglet "Tous" (par
     * défaut) ne doit jamais rien cacher.
     */
    public function test_la_liste_peut_etre_filtree_par_onglet_de_niveau(): void
    {
        $admin = User::factory()->admin()->create();
        $pvBts = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_BTS, 'numero_pv' => 'PV-BTS']);
        $pvDts = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_DTS, 'numero_pv' => 'PV-DTS']);
        $pvMaster = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_MASTER, 'numero_pv' => 'PV-MASTER']);

        // Onglet BTS : uniquement le PV de ce niveau.
        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['niveau' => PvSoutenance::NIVEAU_BTS]));
        $reponse->assertOk();
        $reponse->assertSee('PV-BTS');
        $reponse->assertDontSee('PV-DTS');
        $reponse->assertDontSee('PV-MASTER');

        // Pas d'onglet (défaut) = "Tous" : les trois apparaissent.
        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index'));
        $reponse->assertOk();
        $reponse->assertSee('PV-BTS');
        $reponse->assertSee('PV-DTS');
        $reponse->assertSee('PV-MASTER');
    }

    /** Une valeur de niveau inconnue dans l'URL (lien externe/favori obsolète) retombe sur "Tous" plutôt qu'une erreur. */
    public function test_un_niveau_inconnu_dans_lurl_retombe_sur_tous(): void
    {
        $admin = User::factory()->admin()->create();
        $pv = PvSoutenance::factory()->create(['numero_pv' => 'PV-VALIDE']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['niveau' => 'inconnu']));
        $reponse->assertOk();
        $reponse->assertSee('PV-VALIDE');
    }

    /** "N° PV" est désormais un filtre dédié, distinct de "Recherche" (nom/prénoms) — demande explicite du client. */
    public function test_la_liste_peut_etre_filtree_par_numero_pv(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['numero_pv' => 'PV-2026-099']);
        PvSoutenance::factory()->create(['numero_pv' => 'PV-2026-001']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['numero_pv' => '099']));
        $reponse->assertOk();
        $reponse->assertSee('PV-2026-099');
        $reponse->assertDontSee('PV-2026-001');

        // "Recherche" (nom/prénoms) ne doit plus chercher dans numero_pv.
        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['recherche' => '099']));
        $reponse->assertOk();
        $reponse->assertDontSee('PV-2026-099');
    }

    /** Tri cliquable sur les en-têtes de colonne — demande explicite du client ("ajoute la fonctionnalité de trier sur les tableaux"), sur une colonne directe. */
    public function test_la_liste_peut_etre_triee_sur_une_colonne_directe(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['numero_pv' => 'PV-B']);
        PvSoutenance::factory()->create(['numero_pv' => 'PV-A']);
        PvSoutenance::factory()->create(['numero_pv' => 'PV-C']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['tri' => 'numero_pv', 'direction' => 'asc']));
        $reponse->assertOk();
        $reponse->assertSeeInOrder(['PV-A', 'PV-B', 'PV-C']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['tri' => 'numero_pv', 'direction' => 'desc']));
        $reponse->assertOk();
        $reponse->assertSeeInOrder(['PV-C', 'PV-B', 'PV-A']);
    }

    /** "Filière" n'est pas une colonne de pv_soutenances mais une relation : le tri passe par une sous-requête corrélée (voir PvSoutenanceController::colonnesTriables()), doit fonctionner comme un tri classique. */
    public function test_la_liste_peut_etre_triee_par_filiere(): void
    {
        $admin = User::factory()->admin()->create();
        $filiereA = Filiere::factory()->create(['libelle' => 'Agronomie']);
        $filiereZ = Filiere::factory()->create(['libelle' => 'Zootechnie']);
        PvSoutenance::factory()->create(['filiere_id' => $filiereZ->id, 'numero_pv' => 'PV-Z']);
        PvSoutenance::factory()->create(['filiere_id' => $filiereA->id, 'numero_pv' => 'PV-A']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['tri' => 'filiere', 'direction' => 'asc']));
        $reponse->assertOk();
        $reponse->assertSeeInOrder(['PV-A', 'PV-Z']);
    }

    /** "Documents déposés" est un compteur calculé (withCount conditionnel), pas une colonne réelle. */
    public function test_la_liste_peut_etre_triee_par_documents_deposes(): void
    {
        $admin = User::factory()->admin()->create();
        $pvComplet = PvSoutenance::factory()->create(['numero_pv' => 'PV-COMPLET']);
        $pvVide = PvSoutenance::factory()->create(['numero_pv' => 'PV-VIDE']);
        $document = Document::factory()->create();
        PvSoutenanceFichier::factory()->create([
            'pv_soutenance_id' => $pvComplet->id,
            'role' => PvSoutenanceFichier::ROLE_DIPLOME,
            'document_id' => $document->id,
        ]);
        PvSoutenanceFichier::factory()->create([
            'pv_soutenance_id' => $pvVide->id,
            'role' => PvSoutenanceFichier::ROLE_DIPLOME,
            'document_id' => null,
        ]);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['tri' => 'documents_deposes', 'direction' => 'desc']));
        $reponse->assertOk();
        $reponse->assertSeeInOrder(['PV-COMPLET', 'PV-VIDE']);
    }

    /** Une clé de tri inconnue dans l'URL (lien externe/favori obsolète) retombe sur le tri par défaut plutôt qu'une erreur SQL — même principe que l'onglet "niveau". */
    public function test_une_cle_de_tri_inconnue_retombe_sur_le_tri_par_defaut(): void
    {
        $admin = User::factory()->admin()->create();
        PvSoutenance::factory()->create(['numero_pv' => 'PV-VALIDE']);

        $reponse = $this->actingAs($admin)->get(route('gestion.pv-soutenances.index', ['tri' => 'colonne_inconnue_ou_sensible']));
        $reponse->assertOk();
        $reponse->assertSee('PV-VALIDE');
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

    /**
     * Actions groupées (sélection multiple, voir
     * resources/views/gestion/pv-soutenances/index.blade.php) — MÊME
     * PRINCIPE que tests/Feature/EtudiantControllerTest.php pour
     * mettreCorbeilleGroupee()/restaurerGroupe()/forceDestroyGroupe(), sur
     * demande explicite du client ("permettre de faire des sélections pour
     * des actions sur les tableaux différents").
     */
    public function test_mise_a_la_corbeille_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $pvA = PvSoutenance::factory()->create();
        $pvB = PvSoutenance::factory()->create();
        $pvHorsSelection = PvSoutenance::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille-groupee'), [
                'pvSoutenances' => [$pvA->id, $pvB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($pvA->fresh()->deleted_at);
        $this->assertNotNull($pvB->fresh()->deleted_at);
        $this->assertNull($pvHorsSelection->fresh()->deleted_at);
    }

    /**
     * Cascade MISE À LA CORBEILLE, variante GROUPÉE — demande explicite du
     * client ("cascade incohérente") : voir le docblock de
     * PvSoutenanceController::mettreFichiersALaCorbeille(). Couvre le
     * chemin de code propre à la sélection multiple (mettreCorbeilleGroupee()),
     * distinct de celui déjà testé pour un seul PV (voir
     * ReunionControllerTest::test_mise_a_la_corbeille_dune_reunion_met_aussi_son_document_a_la_corbeille()
     * pour le cas unitaire).
     */
    public function test_mise_a_la_corbeille_groupee_met_aussi_les_documents_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();

        $pvA = PvSoutenance::factory()->create();
        $documentA = Document::factory()->create();
        PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pvA->id, 'document_id' => $documentA->id]);

        $pvB = PvSoutenance::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille-groupee'), [
                'pvSoutenances' => [$pvA->id, $pvB->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($pvA->fresh()->deleted_at);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille.restaurer-groupe'), [
                'pvSoutenances' => [$pvA->id, $pvB->id],
            ])
            ->assertRedirect();

        $this->assertNull($pvA->fresh()->deleted_at);
        $this->assertNull(Document::onlyTrashed()->find($documentA->id));
    }

    public function test_restauration_groupee(): void
    {
        $admin = User::factory()->admin()->create();
        $pvA = PvSoutenance::factory()->create();
        $pvB = PvSoutenance::factory()->create();
        $pvA->delete();
        $pvB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille.restaurer-groupe'), [
                'pvSoutenances' => [$pvA->id, $pvB->id],
            ])
            ->assertRedirect();

        $this->assertNull($pvA->fresh()->deleted_at);
        $this->assertNull($pvB->fresh()->deleted_at);
    }

    /** Même raisonnement que forceDestroy() individuel : les documents encore attachés sont mis à la corbeille avant de supprimer les PV. */
    public function test_suppression_definitive_groupee_met_les_documents_encore_attaches_a_la_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $pvA = PvSoutenance::factory()->create();
        $pvB = PvSoutenance::factory()->create();
        $documentA = Document::factory()->create();
        $documentB = Document::factory()->create();
        PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pvA->id, 'document_id' => $documentA->id]);
        PvSoutenanceFichier::factory()->create(['pv_soutenance_id' => $pvB->id, 'document_id' => $documentB->id]);
        $pvA->delete();
        $pvB->delete();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille.force-destroy-groupe'), [
                'pvSoutenances' => [$pvA->id, $pvB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('pv_soutenances', ['id' => $pvA->id]);
        $this->assertDatabaseMissing('pv_soutenances', ['id' => $pvB->id]);
        $this->assertNotNull(Document::onlyTrashed()->find($documentA->id));
        $this->assertNotNull(Document::onlyTrashed()->find($documentB->id));
    }

    /** Une sélection vide (ou absente) doit être refusée côté serveur, pas seulement empêchée par le JS du bouton. */
    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.pv-soutenances.corbeille-groupee'), ['pvSoutenances' => []])
            ->assertRedirect()
            ->assertSessionHasErrors('pvSoutenances');
    }

    /** Sans le droit "delete" sur "pv_soutenances", les trois actions groupées restent interdites — même droit que les actions individuelles. */
    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'pv_soutenances_lecture_seule',
            'name' => 'Lecture seule PV soutenance',
            'permissions' => ['pv_soutenances' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'pv_soutenances_lecture_seule']);
        $pv = PvSoutenance::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.pv-soutenances.corbeille-groupee'), ['pvSoutenances' => [$pv->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.pv-soutenances.corbeille.restaurer-groupe'), ['pvSoutenances' => [$pv->id]])
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->post(route('gestion.pv-soutenances.corbeille.force-destroy-groupe'), ['pvSoutenances' => [$pv->id]])
            ->assertForbidden();
    }
}
