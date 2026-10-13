<?php

namespace Tests\Unit;

use App\Models\PvSoutenance;
use Tests\TestCase;

/**
 * App\Models\PvSoutenance — petits helpers d'affichage (nomComplet(),
 * niveauLibelle(), decisionLibelle(), mentionLibelle()).
 */
class PvSoutenanceTest extends TestCase
{
    public function test_nom_complet_joint_prenoms_et_nom(): void
    {
        $pv = new PvSoutenance(['nom' => 'Ouédraogo', 'prenoms' => 'Aïcha']);

        $this->assertSame('Aïcha Ouédraogo', $pv->nomComplet());
    }

    public function test_les_libelles_reprennent_les_constantes(): void
    {
        $pv = new PvSoutenance([
            'niveau' => PvSoutenance::NIVEAU_MASTER,
            'decision_jury' => PvSoutenance::DECISION_ADMIS,
            'mention' => PvSoutenance::MENTION_TRES_BIEN,
        ]);

        $this->assertSame('Master', $pv->niveauLibelle());
        $this->assertSame('Admis', $pv->decisionLibelle());
        $this->assertSame('Très bien', $pv->mentionLibelle());
    }

    /** Les 4 mentions sont EXACTEMENT celles demandées par le client, aucune ajoutée. */
    public function test_les_mentions_sont_exactement_celles_demandees(): void
    {
        $this->assertSame(
            ['passable', 'assez_bien', 'bien', 'tres_bien'],
            array_keys(PvSoutenance::MENTIONS)
        );
    }

    /** Les 5 niveaux : ceux du registre papier fourni par le client (BTS, Licence, Master, Doctorat), plus DTS ajouté après coup à côté du BTS (cycles courts équivalents). */
    public function test_les_niveaux_sont_ceux_du_registre(): void
    {
        $this->assertSame(
            ['bts', 'dts', 'licence', 'master', 'doctorat'],
            array_keys(PvSoutenance::NIVEAUX)
        );
    }

    /** niveauBadgeClasse() donne un repère visuel par niveau (voir public/css/style.css) — demande explicite du client. */
    public function test_niveau_badge_classe_une_classe_par_niveau_connu(): void
    {
        $this->assertSame('bg-niveau-bts', (new PvSoutenance(['niveau' => PvSoutenance::NIVEAU_BTS]))->niveauBadgeClasse());
        $this->assertSame('bg-niveau-dts', (new PvSoutenance(['niveau' => PvSoutenance::NIVEAU_DTS]))->niveauBadgeClasse());
        $this->assertSame('bg-niveau-doctorat', (new PvSoutenance(['niveau' => PvSoutenance::NIVEAU_DOCTORAT]))->niveauBadgeClasse());
        // Valeur inconnue (donnée corrompue/ancienne) : repli neutre, jamais d'erreur.
        $this->assertSame('text-bg-secondary', (new PvSoutenance(['niveau' => 'inconnu']))->niveauBadgeClasse());
    }

    /** estDiplomeRetire() se base sur diplome_date_retrait (renseignée <=> retiré), pas un booléen dédié. */
    public function test_est_diplome_retire_se_base_sur_la_date_de_retrait(): void
    {
        $nonRetire = new PvSoutenance();
        $this->assertFalse($nonRetire->estDiplomeRetire());

        $retire = new PvSoutenance(['diplome_date_retrait' => '2026-07-01']);
        $this->assertTrue($retire->estDiplomeRetire());
    }

    public function test_diplome_retirant_libelle_selon_qui_est_venu(): void
    {
        $parLetudiant = new PvSoutenance(['diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT]);
        $this->assertSame("L'étudiant lui-même", $parLetudiant->diplomeRetirantLibelle());

        $parAutre = new PvSoutenance(['diplome_retirant' => PvSoutenance::RETIRANT_AUTRE, 'diplome_nom_retirant' => 'Un cousin']);
        $this->assertSame('Un cousin', $parAutre->diplomeRetirantLibelle());

        $nonRetire = new PvSoutenance();
        $this->assertNull($nonRetire->diplomeRetirantLibelle());
    }
}
