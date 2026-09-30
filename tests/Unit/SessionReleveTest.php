<?php

namespace Tests\Unit;

use App\Models\SessionReleve;
use Tests\TestCase;

/**
 * App\Models\SessionReleve::groupePourNumero() — miroir de
 * tests/Unit/SessionDeliberationTest.php (voir son docblock), même
 * regroupement "Licence 1"/"Licence 2"/"Licence 3" (ou "Master 1"/"Master 2")
 * appliqué ici aux Relevés de notes.
 */
class SessionReleveTest extends TestCase
{
    public function test_les_semestres_licence_sont_groupes_par_annee(): void
    {
        $session = new SessionReleve(['cycle' => SessionReleve::CYCLE_LICENCE]);

        $this->assertSame('Licence 1', $session->groupePourNumero(1));
        $this->assertSame('Licence 1', $session->groupePourNumero(2));
        $this->assertSame('Licence 2', $session->groupePourNumero(3));
        $this->assertSame('Licence 2', $session->groupePourNumero(4));
        $this->assertSame('Licence 3', $session->groupePourNumero(5));
        $this->assertSame('Licence 3', $session->groupePourNumero(6));
    }

    public function test_les_semestres_master_sont_groupes_par_annee(): void
    {
        $session = new SessionReleve(['cycle' => SessionReleve::CYCLE_MASTER]);

        $this->assertSame('Master 1', $session->groupePourNumero(7));
        $this->assertSame('Master 1', $session->groupePourNumero(8));
        $this->assertSame('Master 2', $session->groupePourNumero(9));
        $this->assertSame('Master 2', $session->groupePourNumero(10));
    }

    /** Le Doctorat n'a pas de regroupement intermédiaire : chaque "Année de thèse N" est déjà son propre groupe. */
    public function test_le_doctorat_na_pas_de_groupe(): void
    {
        $session = new SessionReleve(['cycle' => SessionReleve::CYCLE_DOCTORAT]);

        $this->assertNull($session->groupePourNumero(1));
        $this->assertNull($session->groupePourNumero(3));
    }
}
