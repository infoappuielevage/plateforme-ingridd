<?php

namespace Tests\Unit;

use App\Models\ChampSupplementaire;
use App\Models\SessionDeliberation;
use Tests\TestCase;

/**
 * App\Models\ChampSupplementaire — vérifie la logique pure
 * (applicablePv()/applicableReleve()/cyclesLibelle()), sans base de données.
 * La synchronisation rétroactive elle-même (création effective des cases) est
 * testée dans tests/Feature/ChampSupplementaireControllerTest.php.
 */
class ChampSupplementaireTest extends TestCase
{
    public function test_applicable_pv_exige_actif_pour_pv_et_le_bon_cycle(): void
    {
        $champ = new ChampSupplementaire([
            'actif' => true,
            'pour_pv' => true,
            'pour_releve' => false,
            'cycles' => [SessionDeliberation::CYCLE_LICENCE],
        ]);

        $this->assertTrue($champ->applicablePv(SessionDeliberation::CYCLE_LICENCE));
        $this->assertFalse($champ->applicablePv(SessionDeliberation::CYCLE_MASTER));
        $this->assertFalse($champ->applicableReleve(SessionDeliberation::CYCLE_LICENCE));
    }

    public function test_un_champ_inactif_nest_jamais_applicable(): void
    {
        $champ = new ChampSupplementaire([
            'actif' => false,
            'pour_pv' => true,
            'pour_releve' => true,
            'cycles' => [SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_MASTER],
        ]);

        $this->assertFalse($champ->applicablePv(SessionDeliberation::CYCLE_LICENCE));
        $this->assertFalse($champ->applicableReleve(SessionDeliberation::CYCLE_LICENCE));
    }

    public function test_cycles_libelle_joint_les_libelles_lisibles(): void
    {
        $champ = new ChampSupplementaire([
            'cycles' => [SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_DOCTORAT],
        ]);

        $this->assertSame('Licence, Doctorat', $champ->cyclesLibelle());
    }

    public function test_cycles_libelle_sans_cycle_affiche_un_tiret(): void
    {
        $champ = new ChampSupplementaire(['cycles' => null]);

        $this->assertSame('—', $champ->cyclesLibelle());
    }
}
