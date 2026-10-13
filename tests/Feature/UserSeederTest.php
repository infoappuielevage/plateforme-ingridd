<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UserSeeder — comptes de démonstration (un par rôle), demandés par le
 * client pour avoir un aperçu de ce que chaque rôle donne à voir/gérer.
 * Volontairement pas appelé automatiquement par DatabaseSeeder (voir son
 * docblock).
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_un_compte_par_role(): void
    {
        (new UserSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'gestionnaire@ingridd.local', 'role' => 'gestionnaire']);
        $this->assertDatabaseHas('users', ['email' => 'consultation@ingridd.local', 'role' => 'consultation']);
        $this->assertDatabaseHas('users', ['email' => 'direction@ingridd.local', 'role' => 'direction']);
        $this->assertDatabaseHas('users', ['email' => 'secretariat@ingridd.local', 'role' => 'secretariat']);
        $this->assertDatabaseHas('users', ['email' => 'comptabilite@ingridd.local', 'role' => 'comptabilite']);
        $this->assertDatabaseHas('users', ['email' => 'patrimoine@ingridd.local', 'role' => 'patrimoine']);
        $this->assertDatabaseHas('users', ['email' => 'chargees.cours@ingridd.local', 'role' => 'chargees_cours']);
    }

    public function test_le_mot_de_passe_des_comptes_de_demonstration_fonctionne(): void
    {
        (new UserSeeder)->run();

        $utilisateur = User::query()->where('email', 'gestionnaire@ingridd.local')->firstOrFail();

        $this->assertTrue(Hash::check('password', $utilisateur->password));
    }

    /** Un compte supplémentaire, déjà bloqué — voir UserController::basculerBlocage() et son badge "Bloqué". */
    public function test_le_seeder_cree_un_compte_deja_bloque(): void
    {
        (new UserSeeder)->run();

        $compte = User::query()->where('email', 'compte.bloque@ingridd.local')->firstOrFail();

        $this->assertTrue($compte->estBloque());
    }

    /** updateOrCreate() par email : rejouer le seeder ne doit jamais créer de doublon. */
    public function test_rejouer_le_seeder_ne_cree_pas_de_doublon(): void
    {
        (new UserSeeder)->run();
        $nombreApresUnePasse = User::query()->count();

        (new UserSeeder)->run();

        $this->assertSame($nombreApresUnePasse, User::query()->count());
    }
}
