<?php

namespace Tests\Feature;

use App\Filament\Pages\Parametres;
use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vérifie ce qui fait l'objet de la demande explicite du client : tout ce
 * qui était codé en dur (nom, couleur, tailles d'upload, rétention de
 * l'historique) doit être modifiable depuis l'écran Paramètres après le
 * déploiement, et réservé au rôle admin (au même titre que Users/Roles).
 */
class ParametresTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_modifier_les_parametres(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Parametres::class)
            ->fillForm([
                'nom_application' => 'Archives Test',
                'couleur_principale' => '#22c55e',
                'taille_max_document_mo' => 50,
                'taille_max_avatar_mo' => 5,
                'retention_audit_jours' => 365,
                'retention_sauvegardes_nombre' => 5,
                'frequence_sauvegarde_auto' => 'hebdomadaire',
                'mysqldump_chemin' => 'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe',
                'types_fichiers_autorises' => ['documents', 'images'],
            ])
            ->call('enregistrer')
            ->assertHasNoFormErrors();

        $parametres = Parametre::query()->firstOrFail();

        $this->assertSame('Archives Test', $parametres->nom_application);
        $this->assertSame(50, $parametres->taille_max_document_mo);
        $this->assertSame(365, $parametres->retention_audit_jours);
        $this->assertSame(5, $parametres->retention_sauvegardes_nombre);
        $this->assertSame('hebdomadaire', $parametres->frequence_sauvegarde_auto);
        $this->assertSame('C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe', $parametres->mysqldump_chemin);
        $this->assertSame(['documents', 'images'], $parametres->types_fichiers_autorises);
    }

    /**
     * La colonne `types_fichiers_autorises` n'existait pas avant la
     * migration 2026_09_20_000013 : une ligne "parametres" créée
     * auparavant (comme celle déjà en place chez le client au moment de ce
     * changement) se retrouve avec cette colonne à NULL après la migration
     * — le formulaire ne doit surtout pas s'afficher avec rien de coché
     * (ce qui bloquerait l'enregistrement, ->required()), mais avec toutes
     * les familles pré-cochées (comportement identique à avant ce réglage).
     */
    public function test_le_formulaire_precoche_toutes_les_familles_si_le_reglage_nest_pas_encore_enregistre(): void
    {
        $admin = User::factory()->admin()->create();

        Parametre::actuel();
        Parametre::query()->update(['types_fichiers_autorises' => null]);

        Livewire::actingAs($admin)
            ->test(Parametres::class)
            ->assertFormSet([
                'types_fichiers_autorises' => TypesFichiers::toutesLesCles(),
            ]);
    }

    public function test_un_non_admin_ne_peut_pas_acceder_aux_parametres(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->assertFalse(Parametres::canAccess());

        Livewire::actingAs($utilisateur);
        $this->assertFalse(Parametres::canAccess());
    }
}
