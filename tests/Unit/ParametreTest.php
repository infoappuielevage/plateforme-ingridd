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

    /** Ajoutée sur demande explicite du client ("pourquoi pas une sauvegarde automatique également chaque année") — en plus de quotidienne/hebdomadaire/mensuelle. */
    public function test_frequence_annuelle_ne_sexecute_que_le_1er_janvier(): void
    {
        $parametre = new Parametre(['frequence_sauvegarde_auto' => 'annuelle']);

        $this->travelTo(Carbon::parse('2027-01-01'));
        $this->assertTrue($parametre->doitSauvegarderAujourdhui());

        $this->travelTo(Carbon::parse('2026-12-31'));
        $this->assertFalse($parametre->doitSauvegarderAujourdhui());

        $this->travelTo(Carbon::parse('2027-01-02'));
        $this->assertFalse($parametre->doitSauvegarderAujourdhui());
    }

    /**
     * appliquerConfigurationMail() — voir son docblock et celui de la
     * migration 2026_10_10_000001_add_configuration_mail_to_parametres_table.php.
     * Restaure explicitement la config mail à la fin (même si chaque test
     * PHPUnit démarre normalement avec une application fraîche) : par
     * prudence, après avoir déjà rencontré un cas de configuration non
     * restaurée qui fuitait entre deux tests (voir
     * tests/Feature/CreerSauvegardeTest.php).
     */
    public function test_la_configuration_mail_est_appliquee_quand_un_hote_est_renseigne(): void
    {
        $configOrigine = config('mail');

        $parametre = new Parametre([
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_username' => 'contact@ingridd.bf',
            'mail_password' => 'un-mot-de-passe-dapplication',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'contact@ingridd.bf',
            'mail_from_name' => 'INGRIDD Gestion Archives',
        ]);

        $parametre->appliquerConfigurationMail();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('contact@ingridd.bf', config('mail.mailers.smtp.username'));
        $this->assertSame('un-mot-de-passe-dapplication', config('mail.mailers.smtp.password'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('contact@ingridd.bf', config('mail.from.address'));
        $this->assertSame('INGRIDD Gestion Archives', config('mail.from.name'));

        config(['mail' => $configOrigine]);
    }

    /** Tant que "Hôte SMTP" n'a jamais été renseigné, cette méthode ne touche à rien — .env continue de faire foi. */
    public function test_la_configuration_mail_nest_pas_touchee_sans_hote(): void
    {
        $configOrigine = config('mail');

        $parametre = new Parametre(['mail_host' => null]);
        $parametre->appliquerConfigurationMail();

        $this->assertSame($configOrigine, config('mail'));
    }
}
