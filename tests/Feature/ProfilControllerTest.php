<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\ProfilController — remplace App\Filament\Pages\
 * ModifierProfil sur demande du client (plus de Filament). Voir tests/
 * Feature/ModifierProfilTest.php pour l'équivalent Filament (encore en place
 * le temps de la transition) : mêmes garanties de sécurité vérifiées ici
 * côté HTTP classique (SVG refusé, mot de passe actuel exigé pour en changer).
 */
class ProfilControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_modifier_son_nom_et_son_email(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => 'Nouveau nom',
                'email' => 'nouveau-email@ingridd.test',
            ])
            ->assertRedirect(route('gestion.profil.edit'));

        $utilisateur->refresh();
        $this->assertSame('Nouveau nom', $utilisateur->name);
        $this->assertSame('nouveau-email@ingridd.test', $utilisateur->email);
    }

    /** Un SVG est un format texte pouvant contenir du JavaScript — risque de XSS stockée, voir le docblock du contrôleur. */
    public function test_un_fichier_svg_est_refuse_comme_photo_de_profil(): void
    {
        Storage::fake('public');
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($utilisateur->fresh()->avatar);
    }

    /**
     * `avatar` n'est pas dans User::$fillable (voir le docblock du modèle) :
     * ce test vérifie que le contrôleur utilise bien forceFill() pour ce
     * champ précis, pas seulement que la requête ne plante pas — un simple
     * ->update() de masse laisserait ce champ silencieusement inchangé.
     */
    public function test_un_fichier_png_est_accepte_et_persiste_comme_photo_de_profil(): void
    {
        Storage::fake('public');
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'avatar' => UploadedFile::fake()->image('avatar.png'),
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $utilisateur->refresh();
        $this->assertNotNull($utilisateur->avatar);
        Storage::disk('public')->assertExists($utilisateur->avatar);
    }

    public function test_lancienne_photo_est_supprimee_quand_elle_est_remplacee(): void
    {
        Storage::fake('public');
        $utilisateur = User::factory()->create(['avatar' => 'avatars/ancien.png']);
        Storage::disk('public')->put('avatars/ancien.png', 'contenu');

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'avatar' => UploadedFile::fake()->image('nouveau.png'),
            ])
            ->assertRedirect();

        Storage::disk('public')->assertMissing('avatars/ancien.png');
    }

    /**
     * "nouveau-mot-de-passe" (tout en minuscules, sans chiffre) ne suffirait
     * pas : App\Providers\AppServiceProvider impose min 10 caractères +
     * casse mixte + au moins un chiffre à TOUT mot de passe, y compris en
     * environnement de test (seul ->uncompromised() — l'appel réseau vers
     * l'API "Have I Been Pwned" — est réservé à la production). Un mot de
     * passe non conforme ici ferait échouer la validation sur
     * nouveau_mot_de_passe en plus de mot_de_passe_actuel, ce qui n'est pas
     * ce que ces tests veulent vérifier.
     */
    private const NOUVEAU_MOT_DE_PASSE_VALIDE = 'NouveauMotDePasse123';

    public function test_le_mot_de_passe_actuel_est_exige_pour_changer_de_mot_de_passe(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'nouveau_mot_de_passe' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
                'nouveau_mot_de_passe_confirmation' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
            ])
            ->assertSessionHasErrors('mot_de_passe_actuel');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $utilisateur->fresh()->password));
    }

    public function test_le_mot_de_passe_change_avec_le_bon_mot_de_passe_actuel(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'mot_de_passe_actuel' => 'ancien-mot-de-passe',
                'nouveau_mot_de_passe' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
                'nouveau_mot_de_passe_confirmation' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
            ])
            ->assertRedirect(route('gestion.profil.edit'));

        $this->assertTrue(Hash::check(self::NOUVEAU_MOT_DE_PASSE_VALIDE, $utilisateur->fresh()->password));
    }

    public function test_un_mauvais_mot_de_passe_actuel_est_refuse(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);

        $this->actingAs($utilisateur)
            ->put(route('gestion.profil.update'), [
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'mot_de_passe_actuel' => 'mauvais-mot-de-passe',
                'nouveau_mot_de_passe' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
                'nouveau_mot_de_passe_confirmation' => self::NOUVEAU_MOT_DE_PASSE_VALIDE,
            ])
            ->assertSessionHasErrors('mot_de_passe_actuel');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $utilisateur->fresh()->password));
    }
}
