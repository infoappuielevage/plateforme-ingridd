<?php

namespace Tests\Feature;

use App\Models\ChampSupplementaire;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use Database\Seeders\ChampSupplementaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChampSupplementaireSeeder — deux champs supplémentaires de démonstration,
 * demandés par le client pour que l'écran Administration > Champs
 * supplémentaires ne soit pas vide, et pour illustrer la synchronisation
 * rétroactive (voir ChampSupplementaireControllerTest, même logique
 * reproduite ici sans passer par le contrôleur — voir le docblock de la
 * classe).
 */
class ChampSupplementaireSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_deux_champs(): void
    {
        (new ChampSupplementaireSeeder)->run();

        $this->assertDatabaseHas('champs_supplementaires', ['libelle' => 'Démo — Fiche de notation du jury', 'pour_soutenance' => true]);
        $this->assertDatabaseHas('champs_supplementaires', ['libelle' => 'Démo — Attestation de bourse', 'pour_pv' => true, 'pour_releve' => true]);
    }

    /** La case "Fiche de notation du jury" doit apparaître sur un PV de soutenance déjà existant dont le niveau correspond (licence/master). */
    public function test_synchronise_les_pv_de_soutenance_existants(): void
    {
        $pvMaster = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_MASTER]);
        $pvBts = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_BTS]);

        (new ChampSupplementaireSeeder)->run();

        $champ = ChampSupplementaire::query()->where('libelle', 'Démo — Fiche de notation du jury')->firstOrFail();

        $this->assertDatabaseHas('pv_soutenance_fichiers', [
            'pv_soutenance_id' => $pvMaster->id,
            'champ_supplementaire_id' => $champ->id,
            'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
        ]);
        // Le BTS n'est pas dans les cycles de ce champ : pas de case créée.
        $this->assertDatabaseMissing('pv_soutenance_fichiers', [
            'pv_soutenance_id' => $pvBts->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** La case "Attestation de bourse" doit apparaître sur une session de délibération déjà existante dont le cycle correspond. */
    public function test_synchronise_les_sessions_de_deliberation_existantes(): void
    {
        $sessionLicence = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_LICENCE]);
        $sessionDoctorat = SessionDeliberation::factory()->create(['cycle' => SessionDeliberation::CYCLE_DOCTORAT]);

        (new ChampSupplementaireSeeder)->run();

        $champ = ChampSupplementaire::query()->where('libelle', 'Démo — Attestation de bourse')->firstOrFail();

        $this->assertDatabaseHas('session_deliberation_fichiers', [
            'session_deliberation_id' => $sessionLicence->id,
            'champ_supplementaire_id' => $champ->id,
            'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
        ]);
        // Le doctorat n'est pas dans les cycles de ce champ : pas de case créée.
        $this->assertDatabaseMissing('session_deliberation_fichiers', [
            'session_deliberation_id' => $sessionDoctorat->id,
            'champ_supplementaire_id' => $champ->id,
        ]);
    }

    /** updateOrCreate() par libellé : rejouer le seeder ne doit jamais créer de doublon. */
    public function test_rejouer_le_seeder_ne_cree_pas_de_doublon(): void
    {
        (new ChampSupplementaireSeeder)->run();
        (new ChampSupplementaireSeeder)->run();

        $this->assertSame(2, ChampSupplementaire::query()->count());
    }
}
