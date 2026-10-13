<?php

namespace Tests\Feature;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\MenuDocuments;
use App\Support\TypesEcranDedie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Support\MenuDocuments — construit le sous-menu "Types de documents"
 * du layout (voir son docblock) : exclusion des types à écran dédié
 * (App\Support\TypesEcranDedie — PV de soutenance, Délibération, Relevés de
 * notes, PV de réunion...) et seau "Autres types" pour tout type actif sans
 * sous-type, sans écran dédié et hors groupe — sur demande explicite du
 * client ("n'est-il pas temps d'enlever ce qui est en bas... du menu"),
 * repéré en constatant que ces entrées menaient à une liste "Tous les
 * documents" toujours vide (ces types en sont exclus, voir
 * DocumentController::requeteListe()).
 */
class MenuDocumentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ancien comportement ("Délibération"/"PV de soutenance" regroupés sous
     * une entrée dépliable "PV") retiré : ces deux types ont rejoint
     * TypesEcranDedie::CODES (chacun a son propre écran, voir
     * SessionDeliberationController/PvSoutenanceController) et doivent donc
     * disparaître ENTIÈREMENT de ce menu — ni groupés, ni en "Autres types",
     * ni en entrée individuelle — plutôt que de continuer à pointer vers une
     * liste "Tous les documents" systématiquement vide pour ces types.
     */
    public function test_un_type_a_ecran_dedie_napparait_nulle_part_dans_ce_menu(): void
    {
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'actif' => true]);
        TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'actif' => true]);
        $releveNotes = TypeDocument::factory()->create(['code' => 'RELEVE_NOTES', 'libelle' => 'Relevés de notes', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $releveNotes->id, 'libelle' => 'Licence', 'actif' => true]);

        $elements = MenuDocuments::elements();

        $this->assertNull($elements->firstWhere('cle', 'groupe-pv'));
        $this->assertNull($elements->firstWhere('cle', 'type-'.$releveNotes->id));
        foreach ($elements as $element) {
            $this->assertNotContains('Délibération', $element['enfants']->pluck('libelle')->all());
            $this->assertNotContains('PV de soutenance', $element['enfants']->pluck('libelle')->all());
            $this->assertNotContains('Relevés de notes', $element['enfants']->pluck('libelle')->all());
        }
    }

    /** Tous les codes TypesEcranDedie sont couverts par l'exclusion, pas seulement les trois les plus anciens (PV_REUNION, par exemple, a rejoint la liste plus tard). */
    public function test_chaque_type_a_ecran_dedie_est_exclu(): void
    {
        foreach (TypesEcranDedie::CODES as $code) {
            TypeDocument::factory()->create(['code' => $code, 'libelle' => 'Type '.$code, 'actif' => true]);
        }

        $elements = MenuDocuments::elements();

        $this->assertCount(0, $elements);
    }

    public function test_un_type_avec_sous_types_actifs_recoit_sa_propre_entree(): void
    {
        // `ordre` explicite pour la même raison que ci-dessus (sousTypes()
        // trie aussi par `ordre`, voir App\Models\TypeDocument).
        $type = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'libelle' => 'Attestation', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Succès', 'actif' => true, 'ordre' => 1]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Inscription', 'actif' => true, 'ordre' => 2]);

        $entree = MenuDocuments::elements()->firstWhere('cle', 'type-'.$type->id);

        $this->assertNotNull($entree);
        $this->assertSame('Attestation', $entree['libelle']);
        $this->assertNotNull($entree['urlType']);
        $this->assertSame(['Succès', 'Inscription'], $entree['enfants']->pluck('libelle')->all());
    }

    public function test_un_sous_type_inactif_napparait_pas_parmi_les_enfants(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'ATTESTATION', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Succès', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'libelle' => 'Ancien', 'actif' => false]);

        $entree = MenuDocuments::elements()->firstWhere('cle', 'type-'.$type->id);

        $this->assertSame(['Succès'], $entree['enfants']->pluck('libelle')->all());
    }

    public function test_un_type_sans_sous_type_actif_est_range_dans_autres_types(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'CONTRAT', 'libelle' => 'Contrats', 'actif' => true]);

        $elements = MenuDocuments::elements();

        $this->assertNull($elements->firstWhere('cle', 'type-'.$type->id));
        $autres = $elements->firstWhere('cle', 'autres');
        $this->assertNotNull($autres);
        $this->assertSame('Autres types', $autres['libelle']);
        $this->assertNull($autres['urlType']);
        $this->assertContains('Contrats', $autres['enfants']->pluck('libelle')->all());
    }

    /** Un type dont tous les sous-types sont inactifs équivaut, pour ce menu, à un type sans sous-type. */
    public function test_un_type_dont_tous_les_sous_types_sont_inactifs_va_dans_autres_types(): void
    {
        $type = TypeDocument::factory()->create(['code' => 'CONGE', 'libelle' => 'Autorisation de congé', 'actif' => true]);
        SousTypeDocument::factory()->create(['type_document_id' => $type->id, 'actif' => false]);

        $elements = MenuDocuments::elements();

        $this->assertNull($elements->firstWhere('cle', 'type-'.$type->id));
        $this->assertContains('Autorisation de congé', $elements->firstWhere('cle', 'autres')['enfants']->pluck('libelle')->all());
    }

    public function test_un_type_inactif_napparait_nulle_part(): void
    {
        TypeDocument::factory()->create(['code' => 'ARCHIVE', 'libelle' => 'Type désactivé', 'actif' => false]);

        $elements = MenuDocuments::elements();

        $this->assertNull($elements->firstWhere('cle', 'autres'));
        foreach ($elements as $element) {
            $this->assertNotContains('Type désactivé', $element['enfants']->pluck('libelle')->all());
        }
    }

    public function test_aucune_entree_quand_le_referentiel_est_vide(): void
    {
        $this->assertCount(0, MenuDocuments::elements());
    }
}
