<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\ResourceAccess;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
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
    }
}
