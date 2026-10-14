<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Les seeders de DÉMONSTRATION suivants ne sont volontairement PAS appelés
 * ici : contrairement aux référentiels ci-dessous, ce sont de fausses
 * données à ne pas retrouver automatiquement sur une base de production ou
 * après un `migrate:fresh --seed`. À lancer à la demande, dans cet ordre
 * (voir le docblock de chacun pour le détail, et comment les retirer
 * ensuite) :
 *
 *     php artisan db:seed --class=UserSeeder
 *     php artisan db:seed --class=DocumentSeeder
 *     php artisan db:seed --class=GestionFinanciereDemoSeeder
 *     php artisan db:seed --class=ChampSupplementaireSeeder
 *     php artisan db:seed --class=SauvegardeDemoSeeder
 *
 * - UserSeeder : un compte par rôle, pour se connecter et voir ce que
 *   chaque rôle donne à voir/gérer.
 * - DocumentSeeder : documents/étudiants/PV/sessions/réunions de
 *   démonstration, pour que le tableau de bord et les listes montrent
 *   immédiatement quelque chose de parlant.
 * - GestionFinanciereDemoSeeder : Contrats/États financiers/Recettes
 *   (Scolarité et Dossier)/Listes des étudiants (finances) de
 *   démonstration — les modules arrivés après DocumentSeeder, qu'il ne
 *   couvre pas (voir son propre docblock). Dépenses s'alimente déjà seule
 *   (voir TypeDepenseSeeder) : ce seeder se contente d'y déposer quelques
 *   fichiers.
 * - ChampSupplementaireSeeder : quelques champs supplémentaires d'exemple
 *   (à lancer APRÈS DocumentSeeder pour qu'il ait des sessions/PV existants
 *   à rétro-synchroniser).
 * - SauvegardeDemoSeeder : fichiers de sauvegarde factices, pour que
 *   l'écran Administration > Sauvegardes ne soit pas vide.
 *
 * ChampSupplementaireSoutenanceSeeder ci-dessous est différent : ce ne sont
 * pas des données de démonstration mais 4 documents RÉELS attendus par le
 * client sur tout PV de soutenance (autorisation de soutenance, fiche de
 * note, fiche de délibération, fiche de notation de stage) — voir son
 * docblock. Il est donc bien appelé automatiquement ici, pour qu'un
 * `migrate:fresh --seed` les recrée sans passer par Administration > Champs
 * supplémentaires.
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
            TypePieceEtudiantSeeder::class,
            TypeDepenseSeeder::class,
            TypeContratSeeder::class,
            ChampSupplementaireSoutenanceSeeder::class,
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
