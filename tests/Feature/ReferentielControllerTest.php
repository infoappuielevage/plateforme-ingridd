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
}
