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
 * à quoi.
 *
 * Créés avec un accès en LECTURE SEULE sur tout (même matrice que le rôle
 * "Consultation") plutôt qu'avec `permissions = []` comme auparavant —
 * demande explicite du client : avec aucun droit du tout, un compte utilisant
 * l'un de ces rôles arrivait sur un tableau de bord qui semblait "pas à
 * jour"/vide (carte "Documents" absente, "Documents récemment ajoutés"
 * toujours vide, graphiques masqués — voir DashboardController::index(),
 * qui masque correctement tout ça sans le droit "documents" -> "Voir"). La
 * lecture seule est un point de départ sûr (aucune création/modification/
 * suppression nulle part) que le client resserre ou élargit ensuite écran
 * par écran depuis Administration > Rôles & permissions, sans repasser par
 * le code.
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

        $lectureSeule = $this->permissionsLectureSeule();

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
                $donnees = ['name' => $nom];

                // `permissions === []` : exactement l'état laissé par
                // l'ancienne version de ce seeder, jamais encore ouvert
                // depuis Rôles & permissions — on applique alors la lecture
                // seule par défaut (voir le docblock de la classe). Dès que
                // le client a enregistré quoi que ce soit depuis cet écran
                // (même "tout décoché"), RoleController::valider()
                // reconstruit toujours la matrice complète avec des `false`
                // explicites, donc `permissions` n'est alors plus `[]` — ce
                // test ne touche jamais à un rôle déjà configuré par le
                // client, rejouer ce seeder restant sans danger.
                if ($role->permissions === []) {
                    $donnees['permissions'] = $lectureSeule;
                }

                $role->update($donnees);

                continue;
            }

            Role::query()->create(['slug' => $slug, 'name' => $nom, 'permissions' => $lectureSeule]);
        }
    }

    /**
     * Droit "Voir" sur toutes les ressources, rien d'autre — même matrice
     * que le rôle "Consultation" ci-dessus, réutilisée comme valeur par
     * défaut pour les rôles de App\Database\Seeders\RoleSeeder::
     * ROLES_ORGANISATION (voir le docblock de la classe).
     *
     * @return array<string, array{view: bool, create: bool, edit: bool, delete: bool}>
     */
    protected function permissionsLectureSeule(): array
    {
        return array_map(
            fn () => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            array_fill_keys(array_keys(ResourceAccess::RESSOURCES), null)
        );
    }
}
