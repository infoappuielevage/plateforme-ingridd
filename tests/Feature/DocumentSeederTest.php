<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\AnneeAcademiqueSeeder;
use Database\Seeders\DocumentSeeder;
use Database\Seeders\FiliereSeeder;
use Database\Seeders\NiveauSeeder;
use Database\Seeders\SousTypeDocumentSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DocumentSeeder — génère des documents de démonstration (pour un aperçu du
 * tableau de bord/des listes, demandé par le client), volontairement pas
 * appelé automatiquement par DatabaseSeeder (voir son docblock). Ces tests
 * jouent les référentiels nécessaires directement plutôt que via
 * DatabaseSeeder complet, pour rester rapides et indépendants de son compte
 * admin par défaut.
 */
class DocumentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedReferentiels(): void
    {
        (new TypeDocumentSeeder)->run();
        (new SousTypeDocumentSeeder)->run();
        (new NiveauSeeder)->run();
        (new AnneeAcademiqueSeeder)->run();
        (new FiliereSeeder)->run();
    }

    public function test_le_seeder_ne_genere_rien_sans_administrateur_ni_referentiels(): void
    {
        Storage::fake('documents');

        (new DocumentSeeder)->run();

        $this->assertSame(0, Document::query()->withTrashed()->count());
    }

    public function test_le_seeder_genere_des_documents_varies(): void
    {
        Storage::fake('documents');
        User::factory()->admin()->create();
        $this->seedReferentiels();

        (new DocumentSeeder)->run();

        $total = Document::query()->withTrashed()->count();
        $this->assertGreaterThan(0, $total);

        // Une attestation a bien un titulaire, un numéro et un statut de
        // retrait renseignés (voir DocumentResource::form()) — le type
        // "Diplôme" n'est plus utilisé par le seeder depuis la refonte du
        // référentiel (voir TypeDocumentSeeder : DIPLOME est désactivé,
        // sans équivalent évident dans la nouvelle liste).
        $attestation = Document::query()
            ->whereHas('typeDocument', fn ($q) => $q->where('code', 'ATTESTATION'))
            ->first();

        $this->assertNotNull($attestation);
        $this->assertNotNull($attestation->nom_titulaire);
        $this->assertNotNull($attestation->numero_diplome);
        $this->assertNotNull($attestation->statut_retrait);

        // Le fichier physique existe réellement sur le disque `documents`.
        Storage::disk('documents')->assertExists($attestation->chemin);

        // Au moins une attestation a un sous-type renseigné (voir
        // App\Models\SousTypeDocument et SousTypeDocumentSeeder).
        $this->assertTrue(
            Document::query()
                ->whereHas('typeDocument', fn ($q) => $q->where('code', 'ATTESTATION'))
                ->whereNotNull('sous_type_document_id')
                ->exists()
        );

        // Un bordereau (nouveau type, avec sous-type obligatoire dans la
        // démo — voir DocumentSeeder, section 3ter) a bien un sous-type
        // renseigné (ex. "CNSS", "BNSP"...).
        $bordereau = Document::query()
            ->whereHas('typeDocument', fn ($q) => $q->where('code', 'BORDEREAU'))
            ->first();

        $this->assertNotNull($bordereau);
        $this->assertNotNull($bordereau->sous_type_document_id);

        // Les deux statuts de retrait sont représentés, sinon les widgets
        // de retrait n'auraient rien à montrer.
        $this->assertTrue(
            Document::query()->where('statut_retrait', Document::STATUT_RETRAIT_RETIRE)->exists()
            && Document::query()->where('statut_retrait', Document::STATUT_RETRAIT_NON_RETIRE)->exists()
        );

        // Quelques documents sont dans la corbeille (écran "Corbeille" du menu).
        $this->assertGreaterThan(0, Document::onlyTrashed()->count());

        // Un document existe pour un ancien type retiré du référentiel (voir
        // TypeDocumentSeeder : LISTE_ETUDIANTS/DIPLOME/FACTURE/RAPPORT sont
        // désactivés, pas supprimés) — pour que sa page dédiée dans le menu
        // "Secrétariat" ne soit pas vide (voir DocumentSeeder, section 5).
        $this->assertTrue(
            Document::query()
                ->withTrashed()
                ->whereHas('typeDocument', fn ($q) => $q->where('code', 'DIPLOME'))
                ->exists()
        );

        // Un PV de soutenance a bien un titulaire, un thème de mémoire, une
        // date de soutenance et des membres du jury renseignés (voir
        // DocumentResource::form()) — et pas de statut de retrait, ce type
        // n'étant pas concerné par ce suivi.
        $pvSoutenance = Document::query()
            ->whereHas('typeDocument', fn ($q) => $q->where('code', 'PV_SOUTENANCE'))
            ->first();

        $this->assertNotNull($pvSoutenance);
        $this->assertNotNull($pvSoutenance->nom_titulaire);
        $this->assertNotNull($pvSoutenance->theme_memoire);
        $this->assertNotNull($pvSoutenance->date_soutenance);
        $this->assertNotNull($pvSoutenance->membres_jury);
        $this->assertNull($pvSoutenance->statut_retrait);
    }

    public function test_le_seeder_ne_duplique_pas_sil_est_rejoue(): void
    {
        Storage::fake('documents');
        User::factory()->admin()->create();
        $this->seedReferentiels();

        (new DocumentSeeder)->run();
        $premierTotal = Document::query()->withTrashed()->count();

        (new DocumentSeeder)->run();
        $secondTotal = Document::query()->withTrashed()->count();

        $this->assertSame($premierTotal, $secondTotal);
    }
}
