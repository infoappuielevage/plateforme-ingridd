<?php

namespace Tests\Feature;

use App\Filament\Resources\AnneeAcademiqueResource\Pages\CreateAnneeAcademique;
use App\Filament\Resources\AnneeAcademiqueResource\Pages\ListAnneesAcademiques;
use App\Filament\Resources\FiliereResource\Pages\CreateFiliere;
use App\Filament\Resources\FiliereResource\Pages\ListFilieres;
use App\Filament\Resources\NiveauResource\Pages\CreateNiveau;
use App\Filament\Resources\NiveauResource\Pages\ListNiveaux;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\TypeDocumentResource\Pages\CreateTypeDocument;
use App\Filament\Resources\TypeDocumentResource\Pages\ListTypeDocuments;
use App\Models\Document;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Complète AccesRessourcesTest : couvre la création effective d'un
 * référentiel, et le formulaire "Rôles & permissions" (champs en notation
 * pointée `permissions.{ressource}.{action}` — voir RoleResource::form()).
 *
 * "Type de document" sert de référentiel représentatif pour les tests
 * partagés (corbeille, actions groupées, garde-fou "encore utilisé") : les
 * référentiels simples (TypeDocument, Filiere, Niveau, AnneeAcademique)
 * partagent exactement le même mécanisme, voir HasReferentielBulkActions et
 * le docblock de chaque modèle. (Le référentiel "Catégorie" a été retiré du
 * projet, sur demande du client — voir les migrations
 * 2026_09_21_000019/000020.)
 */
class ReferentielResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_creer_un_type_de_document(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateTypeDocument::class)
            ->fillForm(['code' => 'TEST', 'libelle' => 'Type test', 'ordre' => 1, 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('type_documents', ['code' => 'TEST']);
    }

    public function test_un_admin_peut_creer_une_filiere(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateFiliere::class)
            ->fillForm(['code' => 'INFO', 'libelle' => 'Informatique', 'ordre' => 1, 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('filieres', ['code' => 'INFO']);
    }

    public function test_un_admin_peut_creer_un_niveau(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateNiveau::class)
            ->fillForm(['code' => 'L3TEST', 'libelle' => 'Licence 3 test', 'ordre' => 1, 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('niveaux', ['code' => 'L3TEST']);
    }

    public function test_un_admin_peut_creer_une_annee_academique(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateAnneeAcademique::class)
            ->fillForm(['libelle' => '2027-2028', 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('annees_academiques', ['libelle' => '2027-2028']);
    }

    public function test_une_annee_academique_mal_formatee_est_rejetee(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateAnneeAcademique::class)
            ->fillForm(['libelle' => '2027/2028', 'actif' => true])
            ->call('create')
            ->assertHasFormErrors(['libelle']);
    }

    public function test_un_admin_peut_creer_un_role_avec_des_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->fillForm([
                'slug' => 'archiviste',
                'name' => 'Archiviste',
                'permissions.documents.view' => true,
                'permissions.documents.create' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('slug', 'archiviste')->firstOrFail();

        $this->assertTrue((bool) ($role->permissions['documents']['view'] ?? false));
        $this->assertTrue((bool) ($role->permissions['documents']['create'] ?? false));
    }

    public function test_les_permissions_dun_role_se_rechargent_correctement_en_edition(): void
    {
        $admin = User::factory()->admin()->create();

        $role = Role::query()->create([
            'slug' => 'lecteur_test',
            'name' => 'Lecteur test',
            'permissions' => [
                'documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet([
                'permissions.documents.view' => true,
                'permissions.documents.create' => false,
            ]);
    }

    public function test_les_actions_groupees_activent_et_desactivent_des_types_de_document(): void
    {
        $admin = User::factory()->admin()->create();
        $type1 = TypeDocument::factory()->create(['actif' => true]);
        $type2 = TypeDocument::factory()->create(['actif' => true]);

        Livewire::actingAs($admin)
            ->test(ListTypeDocuments::class)
            ->callTableBulkAction('desactiver', [$type1, $type2]);

        $this->assertDatabaseHas('type_documents', ['id' => $type1->id, 'actif' => false]);
        $this->assertDatabaseHas('type_documents', ['id' => $type2->id, 'actif' => false]);

        Livewire::actingAs($admin)
            ->test(ListTypeDocuments::class)
            ->callTableBulkAction('activer', [$type1, $type2]);

        $this->assertDatabaseHas('type_documents', ['id' => $type1->id, 'actif' => true]);
        $this->assertDatabaseHas('type_documents', ['id' => $type2->id, 'actif' => true]);
    }

    /**
     * Un type encore utilisé par un document (même mis à la corbeille — voir
     * Document::class, SoftDeletes) n'est jamais mis à la corbeille, y
     * compris via une action groupée : il doit rester actif, contrairement à
     * un type inutilisé sélectionné en même temps. "Supprimer" ne supprime
     * plus la ligne : elle reste en base, seulement marquée `deleted_at`.
     */
    public function test_la_mise_a_la_corbeille_groupee_ignore_les_types_de_document_encore_utilises(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $typeUtilise = TypeDocument::factory()->create();
        $typeInutilise = TypeDocument::factory()->create();
        Document::factory()->create(['type_document_id' => $typeUtilise->id]);

        Livewire::actingAs($admin)
            ->test(ListTypeDocuments::class)
            ->callTableBulkAction('supprimer', [$typeUtilise, $typeInutilise]);

        $this->assertDatabaseHas('type_documents', ['id' => $typeUtilise->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('type_documents', ['id' => $typeInutilise->id]);
    }

    /**
     * Corbeille des référentiels (TypeDocument représentatif des quatre —
     * TypeDocument, Filiere, Niveau, AnneeAcademique partagent exactement le
     * même mécanisme, voir HasReferentielBulkActions et le docblock de
     * chaque modèle) : mise à la corbeille, restauration, puis suppression
     * définitive — même niveau de test que sur les documents (voir
     * DocumentResourceTest), directement sur le modèle plutôt que via
     * l'action Livewire pour restaurer/supprimer définitivement (le filtre
     * "Corbeille" par défaut exclut les lignes corbeillées de la requête de
     * la table, donc l'action ne les retrouverait pas).
     */
    public function test_un_type_de_document_inutilise_peut_etre_mis_a_la_corbeille_puis_restaure(): void
    {
        $type = TypeDocument::factory()->create();

        $type->delete();
        $this->assertSoftDeleted('type_documents', ['id' => $type->id]);

        $type->restore();
        $this->assertDatabaseHas('type_documents', ['id' => $type->id, 'deleted_at' => null]);
    }

    /**
     * Le bouton "Mettre à la corbeille" (DeleteAction) n'apparaît même pas
     * pour un type encore utilisé par un document — même garde-fou que pour
     * l'action groupée ci-dessus, appliqué ici à l'action individuelle.
     */
    public function test_le_bouton_mettre_a_la_corbeille_est_masque_pour_un_type_de_document_encore_utilise(): void
    {
        Storage::fake('documents');

        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        Document::factory()->create(['type_document_id' => $type->id]);

        Livewire::actingAs($admin)
            ->test(ListTypeDocuments::class)
            ->assertTableActionHidden('delete', $type);
    }

    public function test_un_type_de_document_corbeille_peut_etre_supprime_definitivement(): void
    {
        $type = TypeDocument::factory()->create();
        $type->delete();

        $type->forceDelete();

        $this->assertDatabaseMissing('type_documents', ['id' => $type->id]);
    }

    /**
     * Écran de liste avec le filtre "Corbeille" : doit s'afficher sans
     * erreur, y compris quand des lignes sont effectivement corbeillées
     * (colonne "Mis à la corbeille le", ->counts('documents') sur une
     * relation qui doit continuer à fonctionner malgré SoftDeletes).
     */
    public function test_lecran_de_liste_des_types_de_document_saffiche_sans_erreur_avec_un_type_corbeille(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TypeDocument::factory()->create();
        $type->delete();

        Livewire::actingAs($admin)->test(ListTypeDocuments::class)->assertSuccessful();
    }

    /**
     * Les écrans de liste utilisent ->counts('...') dans leurs colonnes
     * (nombre d'utilisateurs par rôle, de documents par type...) : ça exige
     * que la relation Eloquent correspondante existe vraiment sur le
     * modèle. Un test de création seul ne suffit pas à détecter une relation
     * manquante — seul le rendu réel de la table le révèle (c'est exactement
     * ce qui avait été oublié sur App\Models\Role::users()).
     */
    public function test_les_ecrans_de_liste_du_referentiel_saffichent_sans_erreur(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(ListRoles::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListTypeDocuments::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListFilieres::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListNiveaux::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListAnneesAcademiques::class)->assertSuccessful();
    }
}
