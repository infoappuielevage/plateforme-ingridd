<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CaptureExportExcel;
use Tests\Concerns\ConstruitFichierExcelUploade;
use Tests\TestCase;

/**
 * App\Http\Controllers\ReferentielController — remplace FiliereResource/
 * NiveauResource/AnneeAcademiqueResource sur demande du client (plus de
 * Filament). Un seul contrôleur générique pour les trois, voir son docblock —
 * ces tests couvrent donc les trois référentiels via un fournisseur de
 * données plutôt que trois classes de test quasi identiques.
 */
class ReferentielControllerTest extends TestCase
{
    use CaptureExportExcel;
    use ConstruitFichierExcelUploade;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: class-string}> */
    public static function referentiels(): array
    {
        return [
            'filieres' => ['filieres', Filiere::class],
            'niveaux' => ['niveaux', Niveau::class],
            'annees-academiques' => ['annees-academiques', AnneeAcademique::class],
        ];
    }

    #[DataProvider('referentiels')]
    public function test_la_liste_est_accessible_a_un_admin(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();
        $modele::factory()->create(['libelle' => 'Élément visible']);

        $this->actingAs($admin)
            ->get(route('gestion.referentiels.index', ['referentiel' => $referentiel]))
            ->assertOk()
            ->assertSee('Élément visible');
    }

    #[DataProvider('referentiels')]
    public function test_un_role_sans_droit_de_vue_ne_peut_pas_acceder_a_la_liste(string $referentiel): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_'.str_replace('-', '_', $referentiel),
            'name' => 'Sans droits',
            'permissions' => [$referentiel === 'annees-academiques' ? 'annees_academiques' : $referentiel => ['view' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_'.str_replace('-', '_', $referentiel)]);

        $this->actingAs($utilisateur)
            ->get(route('gestion.referentiels.index', ['referentiel' => $referentiel]))
            ->assertForbidden();
    }

    public function test_un_referentiel_inconnu_renvoie_une_erreur_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.referentiels.index', ['referentiel' => 'inexistant']))
            ->assertNotFound();
    }

    #[DataProvider('referentiels')]
    public function test_la_creation_fonctionne(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();

        $donnees = $referentiel === 'annees-academiques'
            ? ['libelle' => '2030-2031', 'actif' => '1']
            : ['code' => 'NOUV', 'libelle' => 'Nouveau', 'ordre' => 1, 'actif' => '1'];

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.store', ['referentiel' => $referentiel]), $donnees)
            ->assertRedirect(route('gestion.referentiels.index', ['referentiel' => $referentiel]));

        $this->assertDatabaseHas((new $modele)->getTable(), ['libelle' => $donnees['libelle']]);
    }

    /**
     * Le champ Code est désactivé (disabled) sur le formulaire de
     * modification, donc jamais envoyé par le navigateur — la validation ne
     * doit pas l'exiger à ce moment-là (voir ReferentielController::valider()).
     */
    public function test_la_modification_fonctionne_sans_envoyer_le_code(): void
    {
        $admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create(['code' => 'INFO', 'libelle' => 'Ancien libellé']);

        $this->actingAs($admin)
            ->put(route('gestion.referentiels.update', ['referentiel' => 'filieres', 'id' => $filiere->id]), [
                'libelle' => 'Nouveau libellé',
                'ordre' => 2,
                'actif' => '1',
            ])
            ->assertRedirect(route('gestion.referentiels.index', ['referentiel' => 'filieres']));

        $this->assertSame('Nouveau libellé', $filiere->fresh()->libelle);
        $this->assertSame('INFO', $filiere->fresh()->code);
    }

    /**
     * Garde-fou repris à l'identique de HasReferentielBulkActions côté
     * Filament : jamais de corbeille pour un référentiel encore utilisé par
     * au moins un document, même corbeillé.
     */
    public function test_impossible_de_mettre_a_la_corbeille_un_referentiel_encore_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create();
        Document::factory()->create(['filiere_id' => $filiere->id]);

        $this->actingAs($admin)
            ->delete(route('gestion.referentiels.destroy', ['referentiel' => 'filieres', 'id' => $filiere->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('suppression');

        $this->assertNull($filiere->fresh()->deleted_at);
    }

    public function test_corbeille_puis_restauration_dun_referentiel_non_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create();

        $this->actingAs($admin)
            ->delete(route('gestion.referentiels.destroy', ['referentiel' => 'niveaux', 'id' => $niveau->id]))
            ->assertRedirect();
        $this->assertNotNull($niveau->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.restore', ['referentiel' => 'niveaux', 'id' => $niveau->id]))
            ->assertRedirect();
        $this->assertNull($niveau->fresh()->deleted_at);
    }

    /**
     * Actions groupées (sélection multiple) — demandées par le client, même
     * modèle que les 5 autres écrans qui les ont déjà (Étudiants, PV de
     * soutenance, sessions, réunions). Voir ReferentielController::
     * mettreCorbeilleGroupee() et consorts.
     */
    #[DataProvider('referentiels')]
    public function test_mise_a_la_corbeille_groupee(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();
        $un = $modele::factory()->create();
        $deux = $modele::factory()->create();
        $nonSelectionne = $modele::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.corbeille-groupee', ['referentiel' => $referentiel]), [
                'ids' => [$un->id, $deux->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($un->fresh()->deleted_at);
        $this->assertNotNull($deux->fresh()->deleted_at);
        $this->assertNull($nonSelectionne->fresh()->deleted_at);
    }

    /**
     * Même garde-fou que test_impossible_de_mettre_a_la_corbeille_un_referentiel_encore_utilise()
     * mais en sélection groupée : la ligne encore utilisée est ignorée en
     * silence (pas d'erreur qui bloquerait tout le reste de la sélection).
     */
    public function test_la_mise_a_la_corbeille_groupee_ignore_un_element_encore_utilise(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisee = Filiere::factory()->create();
        Document::factory()->create(['filiere_id' => $utilisee->id]);
        $libre = Filiere::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.corbeille-groupee', ['referentiel' => 'filieres']), [
                'ids' => [$utilisee->id, $libre->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertNull($utilisee->fresh()->deleted_at);
        $this->assertNotNull($libre->fresh()->deleted_at);
    }

    #[DataProvider('referentiels')]
    public function test_restauration_groupee(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();
        $un = $modele::factory()->create();
        $deux = $modele::factory()->create();
        $un->delete();
        $deux->delete();

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.corbeille.restaurer-groupe', ['referentiel' => $referentiel]), [
                'ids' => [$un->id, $deux->id],
            ])
            ->assertRedirect();

        $this->assertNull($un->fresh()->deleted_at);
        $this->assertNull($deux->fresh()->deleted_at);
    }

    #[DataProvider('referentiels')]
    public function test_suppression_definitive_groupee(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();
        $un = $modele::factory()->create();
        $un->delete();

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.corbeille.force-destroy-groupe', ['referentiel' => $referentiel]), [
                'ids' => [$un->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing((new $modele)->getTable(), ['id' => $un->id]);
    }

    public function test_une_selection_vide_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.corbeille-groupee', ['referentiel' => 'filieres']), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_les_actions_groupees_exigent_le_droit_de_suppression(): void
    {
        Role::query()->create([
            'slug' => 'sans_droit_suppression_filieres',
            'name' => 'Sans droit de suppression',
            'permissions' => ['filieres' => ['view' => true, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droit_suppression_filieres']);
        $filiere = Filiere::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.referentiels.corbeille-groupee', ['referentiel' => 'filieres']), [
                'ids' => [$filiere->id],
            ])
            ->assertForbidden();

        $this->assertNull($filiere->fresh()->deleted_at);
    }

    /**
     * export() respecte EXACTEMENT les mêmes filtres que index() (même
     * requeteListe() partagée, voir le docblock d'export()) et les colonnes
     * dépendent de `a_code` (voir la constante DEFINITIONS) — vérifié ici
     * sur les trois référentiels via le même fournisseur de données.
     */
    #[DataProvider('referentiels')]
    public function test_lexport_respecte_les_filtres_et_colonnes(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();
        $visible = $referentiel === 'annees-academiques'
            ? $modele::factory()->create(['libelle' => '2030-2031', 'actif' => true])
            : $modele::factory()->create(['code' => 'VISIBLE', 'libelle' => 'Élément visible', 'ordre' => 1, 'actif' => true]);
        $referentiel === 'annees-academiques'
            ? $modele::factory()->create(['libelle' => '2031-2032'])
            : $modele::factory()->create(['code' => 'AUTRE', 'libelle' => 'Autre élément']);

        $terme = $referentiel === 'annees-academiques' ? '2030' : 'VISIBLE';

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.referentiels.export', ['referentiel' => $referentiel, 'recherche' => $terme]))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));
        $this->assertCount(1, $lignes);

        if ($referentiel === 'annees-academiques') {
            $this->assertSame(['2030-2031', 'oui'], $lignes[0]);
        } else {
            // assertEquals (pas assertSame) pour la colonne "Ordre" : un aller-retour
            // par un fichier .xlsx peut renvoyer un nombre entier en float (1.0),
            // ce que import() gère déjà lui-même via un cast (int) explicite.
            $this->assertSame('VISIBLE', $lignes[0][0]);
            $this->assertSame('Élément visible', $lignes[0][1]);
            $this->assertEquals(1, $lignes[0][2]);
            $this->assertSame('oui', $lignes[0][3]);
        }
    }

    #[DataProvider('referentiels')]
    public function test_le_formulaire_dimport_est_accessible(string $referentiel): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.referentiels.import.formulaire', ['referentiel' => $referentiel]))
            ->assertOk();
    }

    /** Le modèle d'import a EXACTEMENT les mêmes colonnes, dans le même ordre, que ce que import() attend (voir son docblock). */
    #[DataProvider('referentiels')]
    public function test_le_modele_dimport_a_les_bonnes_colonnes(string $referentiel): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)
            ->get(route('gestion.referentiels.import.modele', ['referentiel' => $referentiel]))
            ->assertOk();

        $lignes = array_values($this->lignesExportees($reponse->baseResponse));
        $this->assertCount(1, $lignes);

        if ($referentiel === 'annees-academiques') {
            $this->assertSame(['2030-2031', 'oui'], $lignes[0]);
        } else {
            $this->assertSame('EXEMPLE', $lignes[0][0]);
            $this->assertSame('Exemple', $lignes[0][1]);
            $this->assertEquals(1, $lignes[0][2]);
            $this->assertSame('oui', $lignes[0][3]);
        }
    }

    /** Correspondance par CODE pour filières/niveaux (voir le docblock d'import()) : un code inconnu crée un nouvel élément. */
    #[DataProvider('referentiels')]
    public function test_import_cree_un_nouvel_element(string $referentiel, string $modele): void
    {
        $admin = User::factory()->admin()->create();

        $fichier = $referentiel === 'annees-academiques'
            ? $this->fichierExcelUploade(['Libellé', 'Actif'], [['2030-2031', 'oui']])
            : $this->fichierExcelUploade(['Code', 'Libellé', 'Ordre', 'Actif'], [['NOUV', 'Nouveau', 1, 'oui']]);

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.import', ['referentiel' => $referentiel]), ['fichier' => $fichier])
            ->assertRedirect(route('gestion.referentiels.index', ['referentiel' => $referentiel]))
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 1 && $resultat['mis_a_jour'] === 0 && $resultat['erreurs'] === []);

        $cle = $referentiel === 'annees-academiques' ? 'libelle' : 'code';
        $valeur = $referentiel === 'annees-academiques' ? '2030-2031' : 'NOUV';
        $this->assertDatabaseHas((new $modele)->getTable(), [$cle => $valeur]);
    }

    /** Une ligne déjà connue MET À JOUR plutôt que de dupliquer — le code lui-même ne change jamais après création (même règle que valider()). */
    public function test_import_met_a_jour_sans_changer_le_code(): void
    {
        $admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create(['code' => 'INFO', 'libelle' => 'Ancien libellé', 'ordre' => 1, 'actif' => true]);

        $fichier = $this->fichierExcelUploade(
            ['Code', 'Libellé', 'Ordre', 'Actif'],
            [['INFO', 'Nouveau libellé', 5, 'non']]
        );

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.import', ['referentiel' => 'filieres']), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && $resultat['mis_a_jour'] === 1);

        $filiere->refresh();
        $this->assertSame('INFO', $filiere->code);
        $this->assertSame('Nouveau libellé', $filiere->libelle);
        $this->assertSame(5, $filiere->ordre);
        $this->assertFalse($filiere->actif);
        $this->assertSame(1, Filiere::query()->count());
    }

    /** Les années académiques n'ont pas de code (voir DEFINITIONS) : la correspondance se fait par LIBELLÉ, voir le docblock d'import(). */
    public function test_import_met_a_jour_une_annee_academique_par_libelle(): void
    {
        $admin = User::factory()->admin()->create();
        $annee = AnneeAcademique::factory()->create(['libelle' => '2025-2026', 'actif' => false]);

        $fichier = $this->fichierExcelUploade(['Libellé', 'Actif'], [['2025-2026', 'oui']]);

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.import', ['referentiel' => 'annees-academiques']), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', fn ($resultat) => $resultat['crees'] === 0 && $resultat['mis_a_jour'] === 1);

        $this->assertTrue($annee->fresh()->actif);
        $this->assertSame(1, AnneeAcademique::query()->count());
    }

    /** Une ligne invalide (ici : libellé manquant) est signalée et sautée, elle ne bloque jamais le reste du fichier. */
    public function test_import_signale_une_ligne_invalide_sans_bloquer_les_autres(): void
    {
        $admin = User::factory()->admin()->create();

        $fichier = $this->fichierExcelUploade(
            ['Code', 'Libellé', 'Ordre', 'Actif'],
            [
                ['SANSLIBELLE', '', 1, 'oui'],
                ['VALIDE', 'Élément valide', 2, 'oui'],
            ]
        );

        $this->actingAs($admin)
            ->post(route('gestion.referentiels.import', ['referentiel' => 'filieres']), ['fichier' => $fichier])
            ->assertSessionHas('resultatImport', function ($resultat) {
                return $resultat['crees'] === 1
                    && count($resultat['erreurs']) === 1
                    && str_starts_with($resultat['erreurs'][0], 'Ligne 2 :');
            });

        $this->assertDatabaseMissing('filieres', ['code' => 'SANSLIBELLE']);
        $this->assertDatabaseHas('filieres', ['code' => 'VALIDE']);
    }

    /** Sans le droit "create" sur le référentiel, les écrans/traitements d'import restent interdits — même droit que la création individuelle. */
    public function test_limport_exige_le_droit_de_creation(): void
    {
        Role::query()->create([
            'slug' => 'filieres_lecture_seule',
            'name' => 'Lecture seule filières',
            'permissions' => ['filieres' => ['view' => true, 'create' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'filieres_lecture_seule']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.referentiels.import.formulaire', ['referentiel' => 'filieres']))
            ->assertForbidden();

        $fichier = $this->fichierExcelUploade(['Code', 'Libellé', 'Ordre', 'Actif'], [['NOUV', 'Nouveau', 1, 'oui']]);

        $this->actingAs($utilisateur)
            ->post(route('gestion.referentiels.import', ['referentiel' => 'filieres']), ['fichier' => $fichier])
            ->assertForbidden();
    }
}
