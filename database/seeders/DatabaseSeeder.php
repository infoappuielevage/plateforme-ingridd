<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DocumentSeeder (documents de démonstration, pour un aperçu du tableau de
 * bord/des listes) n'est volontairement PAS appelé ici : contrairement aux
 * référentiels ci-dessous, ce sont de fausses données à ne pas retrouver
 * automatiquement sur une base de production ou après un `migrate:fresh
 * --seed`. À lancer à la demande : `php artisan db:seed --class=DocumentSeeder`
 * (voir le docblock de ce seeder pour le détail, et comment retirer ces
 * documents ensuite).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TypeDocumentSeeder::class,
            SousTypeDocumentSeeder::class,
            NiveauSeeder::class,
            AnneeAcademiqueSeeder::class,
            FiliereSeeder::class,
        ]);

        // Compte administrateur de démarrage — à changer immédiatement en
        // production (voir README.md).
        User::query()->updateOrCreate(
            ['email' => 'admin@ingridd.local'],
            [
                'name' => 'Administrateur',
                'password' => bcrypt('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
