<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration — un par rôle (voir App\Models\User::libellesRoles()
 * et database/seeders/RoleSeeder.php), pour pouvoir se connecter avec chacun
 * et voir immédiatement ce que chaque rôle donne à voir/gérer, sans avoir à
 * créer les comptes un par un depuis Administration > Utilisateurs.
 *
 * Mot de passe identique pour tous (voir self::MOT_DE_PASSE) : seulement pour
 * la démonstration, à ne jamais garder en production (voir README.md, même
 * avertissement que le compte admin@ingridd.local de DatabaseSeeder).
 *
 * VOLONTAIREMENT PAS appelé depuis DatabaseSeeder, comme DocumentSeeder (voir
 * son docblock) : ce sont des comptes de test, pas un point de départ à
 * garder en production. À lancer à la demande :
 *
 *     php artisan db:seed --class=UserSeeder
 *
 * Tous identifiables par leur domaine @ingridd.local (même convention que le
 * compte admin créé par DatabaseSeeder) — à retrouver et supprimer facilement
 * avant une mise en production réelle (Administration > Utilisateurs,
 * recherche "ingridd.local").
 *
 * updateOrCreate() par email (idempotent, comme RoleSeeder) : rejouer ce
 * seeder ne crée jamais de doublon.
 */
class UserSeeder extends Seeder
{
    protected const MOT_DE_PASSE = 'password';

    /**
     * email => [name, role] — un compte par rôle "métier" (gestionnaire/
     * consultation) et par rôle "organisation" (voir RoleSeeder::ROLES_ORGANISATION).
     * Le compte admin@ingridd.local existe déjà (créé par DatabaseSeeder) :
     * pas recréé ici.
     */
    protected const COMPTES = [
        'gestionnaire@ingridd.local' => ['Démo Gestionnaire', User::ROLE_GESTIONNAIRE],
        'consultation@ingridd.local' => ['Démo Consultation', User::ROLE_CONSULTATION],
        'direction@ingridd.local' => ['Démo Direction', 'direction'],
        'secretariat@ingridd.local' => ['Démo Secrétariat', 'secretariat'],
        'comptabilite@ingridd.local' => ['Démo Comptabilité', 'comptabilite'],
        'patrimoine@ingridd.local' => ['Démo Patrimoine', 'patrimoine'],
        'chargees.cours@ingridd.local' => ['Démo Chargées de cours', 'chargees_cours'],
    ];

    public function run(): void
    {
        foreach (self::COMPTES as $email => [$nom, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nom,
                    'password' => bcrypt(self::MOT_DE_PASSE),
                    'role' => $role,
                ]
            );
        }

        // Un compte supplémentaire, déjà bloqué dès sa création — pour
        // illustrer tout de suite l'écran Administration > Utilisateurs
        // (badge "Bloqué", voir UserController::basculerBlocage()) sans
        // avoir à bloquer soi-même un compte pour voir à quoi ça ressemble.
        // `bloque_at` n'est pas dans User::$fillable (voir son docblock) :
        // forceFill() est nécessaire ici, comme dans basculerBlocage()
        // lui-même.
        $compteBloque = User::query()->updateOrCreate(
            ['email' => 'compte.bloque@ingridd.local'],
            [
                'name' => 'Démo Compte bloqué',
                'password' => bcrypt(self::MOT_DE_PASSE),
                'role' => User::ROLE_CONSULTATION,
            ]
        );
        $compteBloque->forceFill(['bloque_at' => now()])->save();

        $this->command?->info((count(self::COMPTES) + 1).' compte(s) de démonstration créé(s)/mis à jour (mot de passe : "'.self::MOT_DE_PASSE.'").');
    }
}
