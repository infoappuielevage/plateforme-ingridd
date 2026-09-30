<?php

namespace Tests\Feature;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\MenuDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Support\MenuDocuments — construit le sous-menu "Types de documents"
 * du layout (voir son docblock) : regroupement visuel "PV" (Délibération +
 * PV de soutenance, deux types distincts du référentiel) et seau "Autres
 * types" pour tout type actif sans sous-type et hors groupe — sur demande
 * explicite du client, ni fusion de données ni fausses sous-catégories
 * inventées.
 */
class MenuDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliberation_et_soutenance_sont_regroupes_sous_pv(): void
    {
        // `ordre` explicite : MenuDocuments préserve l'ordre du référentiel
        // (orderBy('ordre')) — sans valeur fixe ici, l'ordre par défaut de la
        // factory (aléatoire) rendrait ce test intermittent.
        $deliberation = TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'actif' => true, 'ordre' => 1]);
        $soutenance = TypeDocument::factory()->create(['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'actif' => true, 'ordre' => 2]);

        $elements = MenuDocuments::elements();
        $groupePv = $elements->firstWhere('cle', 'groupe-pv');

        $this->assertNotNull($groupePv);
        $this->assertSame('PV', $groupePv['libelle']);
        $this->assertNull($groupePv['urlType']);
        $this->assertCount(2, $groupePv['enfants']);
        $this->assertSame(
            ['Délibération', 'PV de soutenance'],
            $groupePv['enfants']->pluck('libelle')->all(),
        );

        // Aucune fusion : chacun garde son propre id / URL de filtrage.
        $this->assertStringContainsString((string) $deliberation->id, $groupePv['enfants'][0]['url']);
        $this->assertStringContainsString((string) $soutenance->id, $groupePv['enfants'][1]['url']);
    }

    public function test_le_groupe_pv_napparait_pas_si_aucun_des_deux_types_nexiste(): void
    {
        TypeDocument::factory()->create(['code' => 'CONTRAT', 'actif' => true]);

        $elements = MenuDocuments::elements();

        $this->assertNull($elements->firstWhere('cle', 'groupe-pv'));
    }

    public function test_le_groupe_pv_apparait_meme_avec_un_seul_des_deux_types_actif(): void
    {
        TypeDocument::factory()->create(['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'actif' => true]);

        $groupePv = MenuDocuments::elements()->firstWhere('cle', 'groupe-pv');

        $this->assertNotNull($groupePv);
        $this->assertCount(1, $groupePv['enfants']);
        $this->assertSame('Délibération', $groupePv['enfants'][0]['libelle']);
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
