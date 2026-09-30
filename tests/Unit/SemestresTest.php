<?php

namespace Tests\Unit;

use App\Support\Semestres;
use Tests\TestCase;

/**
 * Numérotation des semestres LMD par niveau — voir
 * App\Filament\Resources\DocumentResource::form() (champ "Semestre") et le
 * message du client précisant cette numérotation (L1 = S1/S2 ... M2 = S9/S10,
 * continue sur les 5 années plutôt que remise à 1/2 à chaque niveau).
 */
class SemestresTest extends TestCase
{
    public function test_chaque_niveau_lmd_a_ses_deux_semestres(): void
    {
        $this->assertSame([1, 2], Semestres::pourCodeNiveau('L1'));
        $this->assertSame([3, 4], Semestres::pourCodeNiveau('L2'));
        $this->assertSame([5, 6], Semestres::pourCodeNiveau('L3'));
        $this->assertSame([7, 8], Semestres::pourCodeNiveau('M1'));
        $this->assertSame([9, 10], Semestres::pourCodeNiveau('M2'));
    }

    public function test_un_code_niveau_inconnu_ne_propose_aucun_semestre(): void
    {
        $this->assertSame([], Semestres::pourCodeNiveau(null));
        $this->assertSame([], Semestres::pourCodeNiveau('DOCTORAT'));
    }

    public function test_les_options_de_formulaire_sont_libellees(): void
    {
        $this->assertSame([3 => 'Semestre 3', 4 => 'Semestre 4'], Semestres::optionsPourCodeNiveau('L2'));
    }

    public function test_toutes_les_options_couvrent_les_dix_semestres(): void
    {
        $options = Semestres::toutesLesOptions();

        $this->assertCount(10, $options);
        $this->assertSame('Semestre 1', $options[1]);
        $this->assertSame('Semestre 10', $options[10]);
    }

    /** Sens inverse de PAR_CODE_NIVEAU — voir App\Http\Controllers\SessionDeliberationController::niveauPour(). */
    public function test_le_code_niveau_dun_semestre_est_correctement_deduit(): void
    {
        $this->assertSame('L1', Semestres::codeNiveauPourSemestre(1));
        $this->assertSame('L1', Semestres::codeNiveauPourSemestre(2));
        $this->assertSame('L2', Semestres::codeNiveauPourSemestre(3));
        $this->assertSame('L3', Semestres::codeNiveauPourSemestre(6));
        $this->assertSame('M1', Semestres::codeNiveauPourSemestre(7));
        $this->assertSame('M2', Semestres::codeNiveauPourSemestre(10));
        $this->assertNull(Semestres::codeNiveauPourSemestre(11));
    }
}
