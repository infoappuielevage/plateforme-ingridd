<?php

namespace Tests\Feature;

use App\Support\FamillesDocument;
use Tests\TestCase;

/**
 * App\Support\FamillesDocument — reprend le regroupement qu'avait
 * ListDocuments::getTabs() côté Filament (retiré). Pas de RefreshDatabase
 * ici : ces méthodes sont de pures fonctions, aucun accès base.
 */
class FamillesDocumentTest extends TestCase
{
    public function test_onglets_contient_tous_en_premier_sans_filtre(): void
    {
        $onglets = FamillesDocument::onglets();

        $this->assertSame('tous', array_key_first($onglets));
        $this->assertNull($onglets['tous']['codes']);
    }

    public function test_codes_pour_un_onglet_connu(): void
    {
        $this->assertSame(['DIPLOME', 'ATTESTATION', 'LISTE_DIPLOME'], FamillesDocument::codesPour('diplomes_attestations'));
    }

    public function test_codes_pour_tous_est_null(): void
    {
        $this->assertNull(FamillesDocument::codesPour('tous'));
    }

    public function test_codes_pour_un_onglet_inconnu_est_null(): void
    {
        $this->assertNull(FamillesDocument::codesPour('onglet_qui_nexiste_pas'));
    }

    public function test_onglet_valide_retombe_sur_tous_si_inconnu(): void
    {
        $this->assertSame('tous', FamillesDocument::ongletValide('onglet_qui_nexiste_pas'));
        $this->assertSame('academique', FamillesDocument::ongletValide('academique'));
    }

    /** Chaque code n'appartient qu'à une seule famille — sinon un document se retrouverait dans deux onglets à la fois. */
    public function test_aucun_code_nappartient_a_deux_familles(): void
    {
        $tousLesCodes = [];

        foreach (FamillesDocument::onglets() as $cle => $donnees) {
            if ($donnees['codes'] === null) {
                continue;
            }

            foreach ($donnees['codes'] as $code) {
                $this->assertArrayNotHasKey($code, $tousLesCodes, "Le code {$code} apparaît dans plusieurs familles.");
                $tousLesCodes[$code] = $cle;
            }
        }
    }
}
