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

    /**
     * PV_REUNION a rejoint l'onglet "Gouvernance" en même temps que son écran
     * dédié (voir App\Http\Controllers\ReunionController) — regroupement
     * INDÉPENDANT de "avoir un écran dédié" (voir le docblock de
     * App\Support\TypesEcranDedie : "deux usages différents, pas une
     * duplication"), donc aucun changement de ce côté n'était requis ; ce
     * test le vérifie explicitement plutôt que de compter uniquement sur
     * test_aucun_code_nappartient_a_deux_familles ci-dessous.
     */
    public function test_pv_reunion_reste_dans_longlet_gouvernance(): void
    {
        $this->assertContains('PV_REUNION', FamillesDocument::codesPour('gouvernance'));
    }

    /**
     * DOSSIER_ETUDIANT a rejoint l'onglet "Académique" en même temps que son
     * écran dédié (voir App\Http\Controllers\EtudiantController) — aux côtés
     * de LISTE_ETUDIANTS/RELEVE_NOTES/PV_DELIBERATION/PV_SOUTENANCE, les
     * autres types liés aux étudiants.
     */
    public function test_dossier_etudiant_est_dans_longlet_academique(): void
    {
        $this->assertContains('DOSSIER_ETUDIANT', FamillesDocument::codesPour('academique'));
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
