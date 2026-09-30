<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\ResourceAccess;
use Illuminate\Database\Seeder;

/**
 * Rôles — les trois premiers (admin/gestionnaire/consultation) viennent du
 * projet d'origine et gardent une matrice de permissions complète.
 *
 * Les cinq suivants (Direction, Secrétariat, Comptabilité, Patrimoine,
 * Chargées de cours) correspondent à l'organisation réelle d'INGRIDD —
 * demande explicite du client de les prévoir dès maintenant dans le
 * référentiel, PLUTÔT que d'attendre d'avoir fini de détailler qui a accès
 * à quoi. Créés avec `permissions = []` (aucun droit tant que rien n'est
 * coché) : App\Support\ResourceAccess::can() traite une ressource absente
 * comme "false", donc un tableau vide revient exactement à tout décocher —
 * rien de différent d'un rôle fraîchement créé depuis l'écran Rôles &
 * permissions. Le client affinera ce que chacun peut voir/gérer depuis cet
 * écran (Administration > Rôles & permissions), sans repasser par le code :
 * ce seeder ne fait qu'exister pour que ces cinq rôles soient là dès
 * l'installation plutôt qu'à créer un par un manuellement.
 */
class RoleSeeder extends Seeder
{
    /** Nom affiché par slug — voir le docblock de la classe. */
    protected const ROLES_ORGANISATION = [
        'direction' => 'Direction',
        'secretariat' => 'Secrétariat',
        'comptabilite' => 'Comptabilité',
        'patrimoine' => 'Patrimoine',
        'chargees_cours' => 'Chargées de cours',
    ];

    public function run(): void
    {
        $toutesLesActions = array_fill_keys(array_keys(ResourceAccess::ACTIONS), true);
        $permissionsCompletes = array_fill_keys(array_keys(ResourceAccess::RESSOURCES), $toutesLesActions);

        $lectureSeule = array_map(
            fn () => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            array_fill_keys(array_keys(ResourceAccess::RESSOURCES), null)
        );

        Role::query()->updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrateur', 'permissions' => $permissionsCompletes]
        );

        Role::query()->updateOrCreate(
            ['slug' => 'gestionnaire'],
            [
                'name' => 'Gestionnaire',
                'permissions' => [
                    'documents' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'types_documents' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'filieres' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'niveaux' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'annees_academiques' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'audit_logs' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                ],
            ]
        );

        Role::query()->updateOrCreate(
            ['slug' => 'consultation'],
            ['name' => 'Consultation', 'permissions' => $lectureSeule]
        );

        // `updateOrCreate` avec seulement `permissions` en cas de ligne déjà
        // existante n'écraserait PAS un `name` déjà personnalisé par le
        // client depuis l'écran Rôles & permissions — mais ici `name` est
        // réinclus volontairement (comme pour les trois rôles ci-dessus) :
        // rejouer ce seeder resynchronise aussi le libellé si jamais il a
        // été modifié par erreur, sans jamais toucher aux permissions déjà
        // configurées par le client (voir la condition ci-dessous).
        foreach (self::ROLES_ORGANISATION as $slug => $nom) {
            $role = Role::query()->where('slug', $slug)->first();

            if ($role) {
                // Le rôle existe déjà (créé par ce seeder précédemment, ou
                // par le client depuis l'écran Rôles & permissions) : on ne
                // touche plus à `permissions` une fois que le client a
                // commencé à le configurer, seulement au `name` — rejouer ce
                // seeder ne doit jamais effacer des droits déjà accordés.
                $role->update(['name' => $nom]);

                continue;
            }

            Role::query()->create(['slug' => $slug, 'name' => $nom, 'permissions' => []]);
        }
    }
}
