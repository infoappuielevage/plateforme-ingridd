<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecretariatController — remplace App\Filament\Pages\DocumentsParType sur
 * demande du client (plus de Filament). Une page dédiée par type de document
 * "épinglé" (voir App\Models\TypeDocument::$epingle_secretariat), qui
 * réutilise le formulaire/les actions de DocumentController — voir le
 * docblock du contrôleur.
 *
 * Contrairement à tests/Feature/DocumentsParTypeTest.php (Livewire), ces
 * tests passent par de VRAIES requêtes HTTP (`$this->get(route(...))`) :
 * SecretariatController est un contrôleur Laravel classique, pas un
 * composant Livewire.
 */
class SecretariatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_dun_type_ne_montre_que_les_documents_de_ce_type(): void
    {
        $admin = User::factory()->admin()->create();
        $contrat = TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats']);
        $courrier = TypeDocument::factory()->create(['code' => 'COURRIER', 'libelle' => 'Courrier']);

        $documentContrat = Document::factory()->create(['type_document_id' => $contrat->id, 'nom_original' => 'contrat-visible.pdf']);
        $documentCourrier = Document::factory()->create(['type_document_id' => $courrier->id, 'nom_original' => 'courrier-absent.pdf']);

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'CONTRAT']))
            ->assertOk()
            ->assertSee('contrat-visible.pdf')
            ->assertDontSee('courrier-absent.pdf');
    }

    /**
     * Un type avec des sous-types actifs ET `champs_diplome_actifs` (comme
     * "Attestation") explose en une page par sous-type — même logique que
     * l'ancien menu Filament, désormais pilotée par ces colonnes plutôt que
     * par le code "ATTESTATION" en dur.
     */
    public function test_la_page_dun_sous_type_ne_montre_que_ce_sous_type(): void
    {
        $admin = User::factory()->admin()->create();
        $attestation = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);
        $succes = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Succès', 'actif' => true]);
        $vacation = SousTypeDocument::factory()->create(['type_document_id' => $attestation->id, 'libelle' => 'Vacation', 'actif' => true]);

        $documentSucces = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $succes->id,
            'nom_original' => 'succes-visible.pdf',
        ]);
        $documentVacation = Document::factory()->create([
            'type_document_id' => $attestation->id,
            'sous_type_document_id' => $vacation->id,
            'nom_original' => 'vacation-absent.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'ATTESTATION', 'sousType' => $succes->id]))
            ->assertOk()
            ->assertSee('succes-visible.pdf')
            ->assertDontSee('vacation-absent.pdf');
    }

    public function test_un_code_de_type_inconnu_renvoie_une_erreur_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'CODE_INEXISTANT']))
            ->assertNotFound();
    }

    /**
     * Faille de sécurité corrigée sur demande explicite du client ("revoir
     * la sécurité pour éviter l'accessibilité à des pages confidentielles
     * sans autorisation") : cette page ne vérifiait que le droit générique
     * "documents" -> "Voir", sans jamais tenir compte de
     * App\Support\TypesEcranDedie — un rôle n'ayant QUE ce droit générique
     * (ex. "Gestionnaire", voir database/seeders/RoleSeeder.php, qui n'a PAS
     * le droit "etudiants") pouvait consulter ici une liste complète de
     * pièces de dossier étudiant ou de PV de soutenance en tapant
     * directement l'URL, même si le menu ne pointe jamais vers ces codes
     * (`epingle_secretariat` leur a été explicitement retiré, voir
     * database/seeders/TypeDocumentSeeder.php) — 404, même pour un
     * administrateur : ces types n'ont plus leur place ici du tout, pas
     * seulement une question de droit.
     */
    /** @dataProvider codesAvecEcranDedie */
    public function test_les_types_a_ecran_dedie_sont_desormais_inaccessibles_au_secretariat(string $code): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => $code]);

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => $code]))
            ->assertNotFound();
    }

    /** @return iterable<string, array{0: string}> */
    public static function codesAvecEcranDedie(): iterable
    {
        yield 'PV de soutenance' => ['PV_SOUTENANCE'];
        yield 'PV de délibération' => ['PV_DELIBERATION'];
        yield 'Relevés de notes' => ['RELEVE_NOTES'];
        yield 'PV de réunion' => ['PV_REUNION'];
        yield 'Dossier étudiant' => ['DOSSIER_ETUDIANT'];
    }

    public function test_un_role_sans_droit_de_vue_sur_les_documents_ne_peut_pas_acceder_a_la_page(): void
    {
        Role::query()->create([
            'slug' => 'sans_droits_secretariat',
            'name' => 'Sans droits secrétariat',
            'permissions' => ['documents' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false]],
        ]);
        $utilisateur = User::factory()->create(['role' => 'sans_droits_secretariat']);
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT']);

        $this->actingAs($utilisateur)
            ->get(route('gestion.secretariat', ['code' => $type->code]))
            ->assertForbidden();
    }

    /**
     * ATTESTATION, pas PV_SOUTENANCE (code à écran dédié depuis, voir
     * App\Support\TypesEcranDedie et test_les_types_a_ecran_dedie_sont_desormais_inaccessibles_au_secretariat
     * ci-dessous) : ce test porte sur le filtre "corbeille" lui-même, pas
     * sur cette exclusion.
     */
    public function test_le_mode_corbeille_ne_montre_que_les_documents_supprimes(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'champs_diplome_actifs' => true]);

        $actif = Document::factory()->create(['type_document_id' => $type->id, 'nom_original' => 'actif.pdf']);
        $corbeille = Document::factory()->create(['type_document_id' => $type->id, 'nom_original' => 'corbeille.pdf']);
        $corbeille->delete();

        $this->actingAs($admin)
            ->get(route('gestion.secretariat', ['code' => 'ATTESTATION', 'corbeille' => 1]))
            ->assertOk()
            ->assertSee('corbeille.pdf')
            ->assertDontSee('actif.pdf');
    }

    /**
     * Le bouton "Ajouter un document" d'une page Secrétariat renvoie vers le
     * formulaire habituel de DocumentController avec le type déjà
     * présélectionné — voir DocumentController::create()/donneesFormulaire().
     */
    public function test_le_formulaire_dajout_presectionne_le_type_venant_du_secretariat(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'actif' => true]);

        $this->actingAs($admin)
            ->get(route('gestion.documents.create', ['type_document_id' => $type->id]))
            ->assertOk()
            ->assertViewHas('typePreselectionneId', $type->id);
    }

    /**
     * Le menu "Secrétariat" (voir resources/views/layouts/gestion.blade.php)
     * ne liste que les types épinglés — un type "générique" non épinglé ne
     * doit pas y apparaître, même s'il existe et a des documents.
     */
    public function test_le_menu_secretariat_ne_liste_que_les_types_epingles(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'epingle_secretariat' => true]);
        TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats Génériques', 'epingle_secretariat' => false]);

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        // On vérifie la présence du LIEN Secrétariat lui-même (pas juste le
        // libellé) : un type non épinglé peut légitimement apparaître
        // ailleurs sur le tableau de bord (voir App\Support\MenuDocuments,
        // le sous-menu "Types de documents" liste TOUS les types actifs,
        // épinglés ou non — deux usages différents, voir son docblock) sans
        // pour autant avoir sa propre entrée Secrétariat.
        $reponse->assertOk()
            ->assertSee(route('gestion.secretariat', ['code' => 'PV_DELIBERATION']), false)
            ->assertDontSee(route('gestion.secretariat', ['code' => 'CONTRAT']), false);
    }

    /**
     * Nettoyage demandé par le client ("plus de doublons ailleurs") : PV de
     * soutenance, PV de délibération et Relevés de notes ont chacun leur
     * propre écran dédié (liens directs dans la sidebar, voir
     * PvSoutenanceController/SessionDeliberationController/
     * SessionReleveController) — le raccourci Secrétariat générique, qui
     * menait à la même chose par un autre chemin (une simple liste de
     * fichiers, sans la structure par étudiant/session), a donc été retiré
     * pour ces trois-là (voir TypeDocumentSeeder::CONFIGURATION).
     * ATTESTATION n'a pas d'écran dédié équivalent : il garde son raccourci.
     */
    /**
     * Sur une installation fraîche (état actuel du référentiel, voir
     * TypeDocumentSeeder) : PV de soutenance, PV de délibération et Relevés
     * de notes n'ont plus de raccourci Secrétariat (chacun a son propre
     * écran dédié désormais) ; ATTESTATION, qui n'a pas d'écran dédié
     * équivalent, a en plus été temporairement désactivé dans le cadre du
     * nettoyage du menu ("trop d'éléments dont on a plus besoin pour le
     * moment") — son raccourci disparaît donc aussi, pour la même raison
     * que n'importe quel autre type désactivé (voir
     * test_un_type_epingle_mais_desactive_napparait_pas_au_secretariat
     * ci-dessous pour la mécanique générique). Rien n'est épinglé au
     * Secrétariat juste après une installation fraîche : c'est le client
     * qui réactivera ce dont il a besoin, au fur et à mesure, depuis
     * Administration > Référentiels > Types de documents.
     */
    public function test_les_types_avec_ecran_dedie_ne_sont_plus_epingles_au_secretariat(): void
    {
        (new TypeDocumentSeeder)->run();
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        $reponse->assertOk()
            ->assertDontSee(route('gestion.secretariat', ['code' => 'PV_SOUTENANCE']), false)
            ->assertDontSee(route('gestion.secretariat', ['code' => 'PV_DELIBERATION']), false)
            ->assertDontSee(route('gestion.secretariat', ['code' => 'RELEVE_NOTES']), false)
            ->assertDontSee(route('gestion.secretariat', ['code' => 'ATTESTATION']), false);
    }

    /**
     * Mécanique générique (indépendante des données actuelles du
     * référentiel) : désactiver un type depuis Administration > Référentiels
     * > Types de documents doit aussi masquer son raccourci Secrétariat,
     * pas seulement l'empêcher d'apparaître pour un nouveau document — sinon
     * le raccourci resterait affiché pour un type que le client a
     * justement dit ne plus vouloir voir "pour le moment" (voir
     * resources/views/layouts/gestion.blade.php, $elementsSecretariat).
     */
    public function test_un_type_epingle_mais_desactive_napparait_pas_au_secretariat(): void
    {
        $admin = User::factory()->admin()->create();
        TypeDocument::factory()->create([
            'code' => 'X_EPINGLE_INACTIF',
            'libelle' => 'Type épinglé désactivé',
            'epingle_secretariat' => true,
            'actif' => false,
        ]);

        $reponse = $this->actingAs($admin)->get(route('gestion.dashboard'));

        $reponse->assertOk()->assertDontSee(route('gestion.secretariat', ['code' => 'X_EPINGLE_INACTIF']), false);
    }
}
