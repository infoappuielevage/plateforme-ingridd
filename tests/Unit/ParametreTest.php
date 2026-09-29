<?php

namespace Tests\Unit;

use App\Models\Parametre;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Parametre::doitSauvegarderAujourdhui() — voir routes/console.php, qui s'en
 * sert pour que changer "Fréquence de la sauvegarde automatique"
 * (Administration > Paramètres) n'exige jamais de toucher au code, alors que
 * la planification Laravel elle-même reste quotidienne.
 */
class ParametreTest extends TestCase
{
    public function test_frequence_quotidienne_sexecute_tous_les_jours(): void
    {
        $parametre = new Parametre(['frequence_sauvegarde_auto' => 'quotidienne']);

        $this->travelTo(Carbon::parse('2026-09-16')); // un mercredi
        $this->assertTrue($parametre->doitSauvegarderAujourdhui());

        $this->travelTo(Carbon::parse('2026-09-20')); // un dimanche
        $this->assertTrue($parametre->doitSauvegarderAujourdhui());
    }

    public function test_frequence_hebdomadaire_ne_sexecute_que_le_lundi(): void
    {
        $parametre = new Parametre(['frequence_sauvegarde_auto' => 'hebdomadaire']);

        $this->travelTo(Carbon::parse('2026-09-14')); // un lundi
        $this->assertTrue($parametre->doitSauvegarderAujourdhui());

        $this->travelTo(Carbon::parse('2026-09-15')); // un mardi
        $this->assertFalse($parametre->doitSauvegarderAujourdhui());
    }

    public function test_frequence_mensuelle_ne_sexecute_que_le_premier_du_mois(): void
    {
        $parametre = new Parametre(['frequence_sauvegarde_auto' => 'mensuelle']);

        $this->travelTo(Carbon::parse('2026-10-01'));
        $this->assertTrue($parametre->doitSauvegarderAujourdhui());

        $this->travelTo(Carbon::parse('2026-10-02'));
        $this->assertFalse($parametre->doitSauvegarderAujourdhui());
    }
}
