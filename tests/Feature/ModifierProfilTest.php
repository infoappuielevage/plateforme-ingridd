<?php

namespace Tests\Feature;

use App\Filament\Pages\ModifierProfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Repris du correctif de sécurité livré sur ingridd-platform : un fichier
 * SVG ne doit jamais être accepté comme photo de profil (risque de XSS
 * stockée — voir le docblock de App\Filament\Pages\ModifierProfil).
 */
class ModifierProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_fichier_svg_est_refuse_comme_photo_de_profil(): void
    {
        $utilisateur = User::factory()->admin()->create();

        Livewire::actingAs($utilisateur)
            ->test(ModifierProfil::class)
            ->fillForm([
                'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
            ])
            ->call('enregistrer')
            ->assertHasFormErrors(['avatar']);

        $this->assertNull($utilisateur->fresh()->avatar);
    }

    public function test_un_fichier_png_est_accepte_comme_photo_de_profil(): void
    {
        $utilisateur = User::factory()->admin()->create();

        Livewire::actingAs($utilisateur)
            ->test(ModifierProfil::class)
            ->fillForm([
                'avatar' => UploadedFile::fake()->image('avatar.png'),
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
            ])
            ->call('enregistrer')
            ->assertHasNoFormErrors();
    }
}
