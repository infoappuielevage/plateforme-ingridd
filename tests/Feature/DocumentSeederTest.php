<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
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

        // Un PV de soutenance existe bien en tant qu'enregistrement DÉDIÉ
        // (voir App\Models\PvSoutenance, qui remplace l'ancien Document "à
        // plat" pour ce type depuis la refonte), avec son identité/thème/
        // jury renseignés et sa case "Image du registre" effectivement
        // remplie d'un vrai Document (type PV_SOUTENANCE, avec
        // theme_memoire/date_soutenance renseignés — voir
        // PvSoutenanceController::uploaderFichier() pour le mapping exact
        // reproduit par le seeder).
        $pvSoutenance = PvSoutenance::query()->with('fichiers.document')->first();

        $this->assertNotNull($pvSoutenance);
        $this->assertNotNull($pvSoutenance->nom);
        $this->assertNotNull($pvSoutenance->prenoms);
        $this->assertNotNull($pvSoutenance->theme);
        $this->assertNotNull($pvSoutenance->president_jury);

        $caseRegistre = $pvSoutenance->fichiers->firstWhere('role', PvSoutenanceFichier::ROLE_IMAGE_REGISTRE);
        $this->assertNotNull($caseRegistre);
        $this->assertNotNull($caseRegistre->document_id);
        $this->assertSame('PV_SOUTENANCE', $caseRegistre->document->typeDocument->code);
        $this->assertNotNull($caseRegistre->document->theme_memoire);
        $this->assertNotNull($caseRegistre->document->date_soutenance);
        $this->assertNull($caseRegistre->document->statut_retrait);

        // Au moins un diplôme déjà retiré, le reste encore en attente (voir
        // DocumentSeeder, section 3bis, et le docblock de la migration
        // 2026_10_05_000001_add_retrait_diplome_to_pv_soutenances_table.php).
        $this->assertTrue(PvSoutenance::query()->whereNotNull('diplome_date_retrait')->exists());
        $this->assertTrue(PvSoutenance::query()->whereNull('diplome_date_retrait')->exists());

        // Les sessions de délibération et de relevés de notes existent elles
        // aussi en tant qu'enregistrements dédiés, avec leurs cases (voir
        // App\Models\SessionDeliberation/SessionReleve, DocumentSeeder
        // sections 1bis et 2).
        $this->assertGreaterThan(0, SessionDeliberation::query()->count());
        $this->assertGreaterThan(0, SessionReleve::query()->count());

        $sessionDeliberation = SessionDeliberation::query()->with('fichiers')->first();
        $this->assertTrue(
            $sessionDeliberation->fichiers->contains(fn (SessionDeliberationFichier $f) => $f->document_id !== null)
        );

        // Quelques PV de soutenance / sessions sont eux aussi dans leur
        // propre corbeille (pas seulement la corbeille générale des
        // documents, déjà vérifiée plus haut).
        $this->assertGreaterThan(0, PvSoutenance::onlyTrashed()->count());
        $this->assertGreaterThan(0, SessionDeliberation::onlyTrashed()->count());
        $this->assertGreaterThan(0, SessionReleve::onlyTrashed()->count());
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
