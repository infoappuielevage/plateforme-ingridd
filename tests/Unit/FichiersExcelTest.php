<?php

namespace Tests\Unit;

use App\Support\FichiersExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * App\Support\FichiersExcel — point de passage unique pour l'export/import
 * Excel (.xlsx) demandé par le client pour les Étudiants, Documents,
 * Référentiels et écrans dédiés. Testé ici de façon purement unitaire
 * (aucun accès base de données) : exporter() produit bien un fichier que
 * lireLignes() peut relire, à l'identique.
 *
 * exporter() renvoie une StreamedResponse (son contenu n'existe qu'au
 * moment où Laravel l'envoie à la réponse HTTP, pas avant) — chaque test
 * capture donc ce contenu via sendContent() + ob_start(), puis l'écrit dans
 * un fichier temporaire pour le relire avec PhpSpreadsheet, exactement
 * comme le ferait un navigateur recevant le téléchargement.
 */
class FichiersExcelTest extends TestCase
{
    protected function capturer(\Symfony\Component\HttpFoundation\StreamedResponse $reponse): string
    {
        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'test-export-');

        ob_start();
        $reponse->sendContent();
        file_put_contents($cheminTemporaire, ob_get_clean());

        return $cheminTemporaire;
    }

    public function test_exporter_produit_un_fichier_que_lireLignes_relit_a_lidentique(): void
    {
        $entetes = ['Code', 'Libellé'];
        $lignes = [['FIL1', 'Première filière'], ['FIL2', 'Deuxième filière']];

        $reponse = FichiersExcel::exporter('test.xlsx', $entetes, $lignes);
        $cheminTemporaire = $this->capturer($reponse);

        $lignesRelues = FichiersExcel::lireLignes($cheminTemporaire);
        unlink($cheminTemporaire);

        // lireLignes() indexe à partir de 2 (ligne 2 = première donnée, voir
        // son docblock) et retire la ligne d'en-tête.
        $this->assertSame(['FIL1', 'Première filière'], $lignesRelues[2]);
        $this->assertSame(['FIL2', 'Deuxième filière'], $lignesRelues[3]);
        $this->assertCount(2, $lignesRelues);
    }

    public function test_lireLignes_ignore_les_lignes_entierement_vides(): void
    {
        $reponse = FichiersExcel::exporter('test.xlsx', ['Libellé'], [['Une valeur'], [null], ['Une autre valeur']]);
        $cheminTemporaire = $this->capturer($reponse);

        $lignes = FichiersExcel::lireLignes($cheminTemporaire);
        unlink($cheminTemporaire);

        $this->assertCount(2, $lignes);
    }

    public function test_exporter_met_len_tete_en_gras(): void
    {
        $reponse = FichiersExcel::exporter('test.xlsx', ['Libellé'], [['valeur']]);
        $cheminTemporaire = $this->capturer($reponse);

        $feuille = IOFactory::load($cheminTemporaire);
        $gras = $feuille->getActiveSheet()->getStyle('A1')->getFont()->getBold();
        unlink($cheminTemporaire);

        $this->assertTrue($gras);
    }
}
