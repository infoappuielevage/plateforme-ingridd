<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RoleSeeder — les trois rôles d'origine (admin/gestionnaire/consultation)
 * gardent leur matrice de permissions complète (déjà couverts ailleurs par
 * l'usage courant de User::factory()->admin()/gestionnaire()/consultation()
 * dans le reste de la suite) ; ces tests couvrent spécifiquement les cinq
 * rôles "organisation" (Direction, Secrétariat, Comptabilité, Patrimoine,
 * Chargées de cours) demandés par le client pour correspondre à
 * l'organisation réelle d'INGRIDD.
 */
class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_cinq_roles_organisation_sont_crees_en_lecture_seule_partout(): void
    {
        (new RoleSeeder)->run();

        foreach ([
            'direction' => 'Direction',
            'secretariat' => 'Secrétariat',
            'comptabilite' => 'Comptabilité',
            'patrimoine' => 'Patrimoine',
            'chargees_cours' => 'Chargées de cours',
        ] as $slug => $nom) {
            $role = Role::query()->where('slug', $slug)->first();

            $this->assertNotNull($role, "Le rôle \"{$slug}\" devrait exister.");
            $this->assertSame($nom, $role->name);

            // Droit "Voir" partout, rien d'autre, par défaut — demande
            // explicite du client : un rôle sans aucun droit faisait
            // apparaître un tableau de bord qui semblait vide/pas à jour
            // (voir le docblock du seeder). Le client resserre ou élargit
            // ensuite depuis Rôles & permissions.
            foreach (\App\Support\ResourceAccess::RESSOURCES as $ressource => $libelle) {
                $this->assertSame(
                    ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                    $role->permissions[$ressource] ?? null,
                    "Le rôle \"{$slug}\" devrait avoir le droit \"Voir\" uniquement sur \"{$ressource}\"."
                );
            }
        }
    }

    /**
     * Installations déjà en place AVANT ce changement : ces cinq rôles y
     * existent encore avec `permissions = []` (ancien comportement). Rejouer
     * le seeder (ex. lors d'une mise à jour) doit les faire passer en
     * lecture seule, exactement comme une installation toute neuve —
     * personne ne doit rester bloqué avec un tableau de bord vide juste
     * parce que son rôle a été créé avant ce correctif.
     */
    public function test_un_role_organisation_jamais_configure_recoit_la_lecture_seule_au_prochain_passage(): void
    {
        Role::query()->create(['slug' => 'secretariat', 'name' => 'Secrétariat', 'permissions' => []]);

        (new RoleSeeder)->run();

        $secretariat = Role::query()->where('slug', 'secretariat')->firstOrFail();
        $this->assertSame(
            ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            $secretariat->permissions['documents']
        );
    }

    public function test_rejouer_le_seeder_ne_duplique_rien(): void
    {
        (new RoleSeeder)->run();
        $premierTotal = Role::query()->count();

        (new RoleSeeder)->run();
        $secondTotal = Role::query()->count();

        $this->assertSame($premierTotal, $secondTotal);
    }

    /**
     * Une fois que le client a coché des droits pour "Secrétariat" (ou
     * n'importe lequel des cinq) depuis l'écran Rôles & permissions, rejouer
     * ce seeder (ex. lors d'un déploiement) ne doit surtout pas les effacer
     * silencieusement — seul le libellé est resynchronisé.
     */
    public function test_rejouer_le_seeder_ne_reinitialise_pas_les_droits_deja_configures(): void
    {
        (new RoleSeeder)->run();

        $secretariat = Role::query()->where('slug', 'secretariat')->firstOrFail();
        $secretariat->update([
            'permissions' => ['documents' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false]],
        ]);

        (new RoleSeeder)->run();

        $secretariat->refresh();
        $this->assertSame(
            ['documents' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false]],
            $secretariat->permissions
        );
    }
}
