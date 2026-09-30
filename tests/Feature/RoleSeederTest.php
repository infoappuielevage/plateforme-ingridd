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

    public function test_les_cinq_roles_organisation_sont_crees_sans_aucun_droit(): void
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
            // Aucun droit accordé par défaut — le client les configurera lui-même
            // depuis Rôles & permissions (voir le docblock du seeder).
            $this->assertSame([], $role->permissions);
        }
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
