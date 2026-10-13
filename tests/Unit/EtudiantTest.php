<?php

namespace Tests\Unit;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use App\Models\EtudiantInscription;
use App\Models\TypePieceEtudiant;
use Tests\TestCase;

/**
 * App\Models\Etudiant::nomComplet()/inscriptionActuelle() et
 * App\Models\EtudiantFichier::libelle()/estRemplie() — purs calculs sans
 * accès base de données (comme tests/Unit/ReunionTest.php), les relations
 * étant injectées à la main via setRelation().
 */
class EtudiantTest extends TestCase
{
    public function test_nom_complet_assemble_prenoms_et_nom(): void
    {
        $etudiant = new Etudiant(['nom' => 'Ouedraogo', 'prenoms' => 'Aïcha']);

        $this->assertSame('Aïcha Ouedraogo', $etudiant->nomComplet());
    }

    /** L'inscription la plus récente (par libellé d'année décroissant) est celle retenue, peu importe l'ordre de la collection d'origine. */
    public function test_inscription_actuelle_retient_lannee_academique_la_plus_recente(): void
    {
        $etudiant = new Etudiant();
        $ancienne = new EtudiantInscription(['niveau_id' => 1]);
        $ancienne->setRelation('anneeAcademique', new AnneeAcademique(['libelle' => '2024-2025']));
        $recente = new EtudiantInscription(['niveau_id' => 2]);
        $recente->setRelation('anneeAcademique', new AnneeAcademique(['libelle' => '2025-2026']));

        $etudiant->setRelation('inscriptions', collect([$ancienne, $recente]));

        $this->assertSame(2, $etudiant->inscriptionActuelle()->niveau_id);
    }

    /** Aucune inscription (dossier tout juste créé, cas limite) : pas d'erreur, juste null. */
    public function test_inscription_actuelle_est_nulle_sans_aucune_inscription(): void
    {
        $etudiant = new Etudiant();
        $etudiant->setRelation('inscriptions', collect());

        $this->assertNull($etudiant->inscriptionActuelle());
    }

    public function test_est_remplie_depend_uniquement_du_document_id(): void
    {
        $pieceVide = new EtudiantFichier(['document_id' => null]);
        $pieceRemplie = new EtudiantFichier(['document_id' => 42]);

        $this->assertFalse($pieceVide->estRemplie());
        $this->assertTrue($pieceRemplie->estRemplie());
    }

    /**
     * Le type de pièce rattaché (voir App\Models\TypePieceEtudiant, refonte
     * "liste fixe gérée en admin") a priorité sur tout le reste, même quand
     * un ancien libellé libre est encore présent sur la case.
     */
    public function test_libelle_privilegie_le_type_de_piece_rattache(): void
    {
        $fichier = new EtudiantFichier(['libelle' => 'ancien libellé libre']);
        $fichier->setRelation('typePieceEtudiant', new TypePieceEtudiant(['libelle' => 'CNIB']));

        $this->assertSame('CNIB', $fichier->libelle());
    }

    /** Sans type rattaché (pièce créée avant la refonte), utilise le libellé saisi à l'ajout, quand il existe. */
    public function test_libelle_utilise_le_libelle_saisi(): void
    {
        $fichier = new EtudiantFichier(['libelle' => 'CNIB']);

        $this->assertSame('CNIB', $fichier->libelle());
    }

    /** Sans type rattaché ni libellé saisi, retombe sur le nom original du fichier déposé — jamais un texte vide dans la liste. */
    public function test_libelle_sans_libelle_retombe_sur_le_nom_du_fichier(): void
    {
        $fichier = new EtudiantFichier(['libelle' => null]);
        $fichier->setRelation('document', new Document(['nom_original' => 'acte-naissance.pdf']));

        $this->assertSame('acte-naissance.pdf', $fichier->libelle());
    }

    /** Ni type rattaché, ni libellé, ni document (cas limite) : texte générique plutôt qu'une chaîne vide. */
    public function test_libelle_sans_rien_renvoie_un_texte_generique(): void
    {
        $fichier = new EtudiantFichier(['libelle' => null]);

        $this->assertSame('Pièce du dossier', $fichier->libelle());
    }
}
