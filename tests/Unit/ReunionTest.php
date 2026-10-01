<?php

namespace Tests\Unit;

use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\SousTypeDocument;
use Tests\TestCase;

/**
 * App\Models\Reunion::typeLibelle() et App\Models\ReunionFichier::libelle()/
 * estRemplie() — purs calculs sans accès base de données (comme
 * tests/Unit/SessionReleveTest.php), les deux modèles restant non persistés.
 */
class ReunionTest extends TestCase
{
    public function test_type_libelle_renvoie_le_libelle_du_sous_type(): void
    {
        $reunion = new Reunion();
        $reunion->setRelation('sousType', new SousTypeDocument(['libelle' => 'Réunion hebdomadaire']));

        $this->assertSame('Réunion hebdomadaire', $reunion->typeLibelle());
    }

    /** Aucun sous-type renseigné (ex. sous-type supprimé définitivement, voir le docblock de la migration) : texte générique plutôt qu'une erreur. */
    public function test_type_libelle_renvoie_un_texte_generique_sans_sous_type(): void
    {
        $reunion = new Reunion(['sous_type_document_id' => null]);

        $this->assertSame('Non précisé', $reunion->typeLibelle());
    }

    public function test_est_remplie_depend_uniquement_du_document_id(): void
    {
        $caseVide = new ReunionFichier(['role' => ReunionFichier::ROLE_RAPPORT, 'document_id' => null]);
        $caseRemplie = new ReunionFichier(['role' => ReunionFichier::ROLE_RAPPORT, 'document_id' => 42]);

        $this->assertFalse($caseVide->estRemplie());
        $this->assertTrue($caseRemplie->estRemplie());
    }

    public function test_libelle_du_rapport_est_toujours_le_meme_texte_fixe(): void
    {
        $fichier = new ReunionFichier(['role' => ReunionFichier::ROLE_RAPPORT, 'libelle' => null]);

        $this->assertSame('Rapport de la réunion', $fichier->libelle());
    }

    /** Une pièce jointe utilise le libellé saisi à l'ajout, quand il existe. */
    public function test_libelle_dune_piece_jointe_utilise_le_libelle_saisi(): void
    {
        $fichier = new ReunionFichier(['role' => ReunionFichier::ROLE_PIECE_JOINTE, 'libelle' => 'Liste de présence']);

        $this->assertSame('Liste de présence', $fichier->libelle());
    }

    /** Sans libellé saisi, retombe sur le nom original du fichier déposé — jamais un texte vide dans la liste. */
    public function test_libelle_dune_piece_jointe_sans_libelle_retombe_sur_le_nom_du_fichier(): void
    {
        $fichier = new ReunionFichier(['role' => ReunionFichier::ROLE_PIECE_JOINTE, 'libelle' => null]);
        $fichier->setRelation('document', new \App\Models\Document(['nom_original' => 'annexe-budget.pdf']));

        $this->assertSame('annexe-budget.pdf', $fichier->libelle());
    }

    /** Ni libellé, ni document rattaché (cas limite) : texte générique plutôt qu'une chaîne vide. */
    public function test_libelle_dune_piece_jointe_sans_rien_renvoie_un_texte_generique(): void
    {
        $fichier = new ReunionFichier(['role' => ReunionFichier::ROLE_PIECE_JOINTE, 'libelle' => null]);

        $this->assertSame('Pièce jointe', $fichier->libelle());
    }
}
