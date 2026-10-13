<?php

namespace Tests\Feature;

use App\Models\ChampSupplementaire;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use Database\Seeders\ChampSupplementaireSoutenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChampSupplementaireSoutenanceSeeder — les 5 documents réels attendus par
 * le client sur tout PV de soutenance (dont "Attestation de soutenance",
 * ajoutée après coup), créés automatiquement (contrairement à
 * ChampSupplementaireSeeder qui est demandé pour des exemples de
 * démonstration — voir ChampSupplementaireSeederTest, dont ce fichier
 * reprend le même plan de tests).
 */
class ChampSupplementaireSoutenanceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected const LIBELLES = [
        'Autorisation de soutenance',
        'Attestation de soutenance',
        'Fiche de note',
        'Fiche de délibération',
        'Fiche de notation de stage',
    ];

    public function test_le_seeder_cree_les_cinq_champs_pour_la_soutenance(): void
    {
        (new ChampSupplementaireSoutenanceSeeder)->run();

        foreach (self::LIBELLES as $libelle) {
            $this->assertDatabaseHas('champs_supplementaires', [
                'libelle' => $libelle,
                'pour_pv' => false,
                'pour_releve' => false,
                'pour_soutenance' => true,
                'actif' => true,
            ]);
        }

        $this->assertSame(5, ChampSupplementaire::query()->count());
    }

    /** Les 5 champs s'appliquent par défaut à tous les niveaux de PV de soutenance, DTS inclus. */
    public function test_les_champs_sappliquent_a_tous_les_niveaux(): void
    {
        (new ChampSupplementaireSoutenanceSeeder)->run();

        $champ = ChampSupplementaire::query()->where('libelle', 'Autorisation de soutenance')->firstOrFail();

        $this->assertSame(array_keys(PvSoutenance::NIVEAUX), $champ->cycles);
        $this->assertContains(PvSoutenance::NIVEAU_DTS, $champ->cycles);
    }

    /** Un PV de soutenance déjà existant doit recevoir les 5 cases dès que le seeder tourne. */
    public function test_synchronise_les_pv_de_soutenance_existants(): void
    {
        $pv = PvSoutenance::factory()->create(['niveau' => PvSoutenance::NIVEAU_MASTER]);

        (new ChampSupplementaireSoutenanceSeeder)->run();

        $champs = ChampSupplementaire::query()->pluck('id');
        $this->assertCount(5, $champs);

        foreach ($champs as $champId) {
            $this->assertDatabaseHas('pv_soutenance_fichiers', [
                'pv_soutenance_id' => $pv->id,
                'champ_supplementaire_id' => $champId,
                'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
            ]);
        }
    }

    /** updateOrCreate() par libellé : rejouer le seeder (ex. à chaque migrate:fresh --seed) ne doit jamais créer de doublon. */
    public function test_rejouer_le_seeder_ne_cree_pas_de_doublon(): void
    {
        (new ChampSupplementaireSoutenanceSeeder)->run();
        (new ChampSupplementaireSoutenanceSeeder)->run();

        $this->assertSame(5, ChampSupplementaire::query()->count());
    }
}
