<?php

namespace Tests\Feature;

use App\Models\TypeDocument;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TypeDocumentSeeder — refonte complète du référentiel des types de
 * documents (liste recensée par le client). Voir son docblock : les codes
 * déjà existants (ATTESTATION, PV_SOUTENANCE, PV_DELIBERATION, CONTRAT,
 * COURRIER, RELEVE_NOTES) sont réutilisés (renommés au besoin) plutôt que
 * recréés, pour ne jamais faire perdre son type à un document déjà classé ;
 * les types sans équivalent évident (LISTE_ETUDIANTS, DIPLOME, FACTURE,
 * RAPPORT) sont désactivés, jamais supprimés. Menu allégé (voir aussi le
 * docblock de la classe) : sur une base neuve, seuls PV_SOUTENANCE/
 * PV_DELIBERATION/RELEVE_NOTES/AUTRE sont créés actifs, tout le reste de la
 * liste est créé désactivé (réactivable sans toucher au code).
 */
class TypeDocumentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_types_reutilises_sont_renommes(): void
    {
        (new TypeDocumentSeeder)->run();

        $this->assertDatabaseHas('type_documents', ['code' => 'ATTESTATION', 'libelle' => 'Attestation']);
        $this->assertDatabaseHas('type_documents', ['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance']);
        $this->assertDatabaseHas('type_documents', ['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération']);
        $this->assertDatabaseHas('type_documents', ['code' => 'CONTRAT', 'libelle' => 'Contrats']);
        $this->assertDatabaseHas('type_documents', ['code' => 'COURRIER', 'libelle' => 'Correspondance et lettre de partenariat']);
        $this->assertDatabaseHas('type_documents', ['code' => 'RELEVE_NOTES', 'libelle' => 'Relevés de notes']);
    }

    public function test_les_nouveaux_types_sont_crees(): void
    {
        (new TypeDocumentSeeder)->run();

        $this->assertDatabaseHas('type_documents', ['code' => 'BORDEREAU', 'libelle' => 'Bordereau']);
        $this->assertDatabaseHas('type_documents', ['code' => 'CONGE', 'libelle' => 'Autorisation de congé']);
        $this->assertDatabaseHas('type_documents', ['code' => 'LISTE_DIPLOME']);
        $this->assertDatabaseHas('type_documents', ['code' => 'AUTRE', 'libelle' => 'Autre']);
    }

    /**
     * Menu allégé (demande explicite du client, "trop d'éléments dont on a
     * plus besoin pour le moment") : sur une installation neuve, seuls les
     * quatre écrans dédiés déjà construits (dont PV_REUNION, arrivé depuis)
     * et "Autre" (filet de sécurité) sont actifs — tout le reste est créé
     * désactivé, réactivable
     * individuellement depuis Administration > Référentiels > Types de
     * documents sans toucher au code (voir CODES_ACTIFS_PAR_DEFAUT).
     */
    public function test_seuls_les_ecrans_dedies_et_autre_sont_actifs_sur_une_base_neuve(): void
    {
        (new TypeDocumentSeeder)->run();

        foreach (['PV_SOUTENANCE', 'PV_DELIBERATION', 'RELEVE_NOTES', 'PV_REUNION', 'AUTRE'] as $code) {
            $this->assertDatabaseHas('type_documents', ['code' => $code, 'actif' => true]);
        }

        foreach (['ATTESTATION', 'CONTRAT', 'COURRIER', 'BORDEREAU', 'CONGE', 'LISTE_DIPLOME', 'ORGANIGRAMME'] as $code) {
            $this->assertDatabaseHas('type_documents', ['code' => $code, 'actif' => false]);
        }
    }

    /**
     * Une fois une ligne créée, `actif` appartient à l'administrateur — un
     * type que le client a réactivé lui-même depuis Administration >
     * Référentiels > Types de documents ne doit jamais être redésactivé en
     * silence par un futur `php artisan db:seed` (ex. lors d'un déploiement).
     */
    public function test_rejouer_le_seeder_ne_touche_plus_a_lactif_dun_type_deja_existant(): void
    {
        (new TypeDocumentSeeder)->run();

        $attestation = TypeDocument::query()->where('code', 'ATTESTATION')->firstOrFail();
        $this->assertFalse($attestation->actif);
        $attestation->update(['actif' => true]);

        $releve = TypeDocument::query()->where('code', 'RELEVE_NOTES')->firstOrFail();
        $releve->update(['actif' => false]);

        (new TypeDocumentSeeder)->run();

        $this->assertTrue($attestation->fresh()->actif);
        $this->assertFalse($releve->fresh()->actif);
    }

    /**
     * Un document déjà classé "Diplôme" (code DIPLOME) avant la refonte ne
     * doit pas perdre son type : la ligne référentielle reste en base,
     * juste désactivée (pas de nouveau document ne pourra plus choisir ce
     * type, mais les documents déjà classés restent valides).
     */
    public function test_les_types_sans_equivalent_sont_desactives_pas_supprimes(): void
    {
        (new TypeDocumentSeeder)->run();

        foreach (['LISTE_ETUDIANTS', 'DIPLOME', 'FACTURE', 'RAPPORT'] as $code) {
            $this->assertDatabaseHas('type_documents', ['code' => $code, 'actif' => false]);
        }
    }

    public function test_rejouer_le_seeder_ne_duplique_rien(): void
    {
        (new TypeDocumentSeeder)->run();
        $premierTotal = TypeDocument::query()->count();

        (new TypeDocumentSeeder)->run();
        $secondTotal = TypeDocument::query()->count();

        $this->assertSame($premierTotal, $secondTotal);
    }

    /**
     * Les quatre types désignés comme priorité par le client ("PV de
     * délibération, relevés de notes, diplômes, registre des soutenances —
     * avant de revoir les autres") reçoivent, pour ceux qui en ont besoin,
     * les champs complémentaires correspondants — voir
     * TypeDocumentSeeder::CONFIGURATION et la migration
     * 2026_09_29_000021_.... Seul ATTESTATION reçoit encore
     * `epingle_secretariat` : les trois autres ont depuis un écran dédié à
     * part entière (PvSoutenanceController/SessionDeliberationController/
     * SessionReleveController), voir
     * test_les_types_avec_ecran_dedie_ne_sont_plus_epingles_au_secretariat
     * dans SecretariatControllerTest pour la vérification bout en bout.
     */
    public function test_les_quatre_types_prioritaires_recoivent_leurs_champs_complementaires(): void
    {
        (new TypeDocumentSeeder)->run();

        $this->assertDatabaseHas('type_documents', [
            'code' => 'ATTESTATION',
            'champs_diplome_actifs' => true,
            'epingle_secretariat' => true,
        ]);
        $this->assertDatabaseHas('type_documents', [
            'code' => 'PV_SOUTENANCE',
            'champs_soutenance_actifs' => true,
            'epingle_secretariat' => false,
        ]);
        $this->assertDatabaseHas('type_documents', [
            'code' => 'RELEVE_NOTES',
            'champ_semestre_actif' => true,
            'epingle_secretariat' => false,
        ]);
        $this->assertDatabaseHas('type_documents', [
            'code' => 'PV_DELIBERATION',
            'champs_diplome_actifs' => false,
            'champs_soutenance_actifs' => false,
            'champ_semestre_actif' => false,
            'epingle_secretariat' => false,
        ]);
    }

    /**
     * "Diplôme" (code historique, désactivé — voir plus haut) reçoit quand
     * même les champs complémentaires "Diplôme" : les documents déjà
     * classés dedans avant la refonte doivent continuer d'afficher
     * titulaire/numéro/mention/statut de retrait sur leur fiche. Pas
     * épinglé au menu Secrétariat en revanche (type désactivé, retrouvable
     * via la liste générale filtrée par type).
     */
    public function test_le_type_diplome_retire_garde_les_champs_diplome_mais_nest_pas_epingle(): void
    {
        (new TypeDocumentSeeder)->run();

        $this->assertDatabaseHas('type_documents', [
            'code' => 'DIPLOME',
            'actif' => false,
            'champs_diplome_actifs' => true,
            'epingle_secretariat' => false,
        ]);
    }

    /** Un type sans configuration particulière (ex. "Contrats") reste générique : aucun drapeau activé. */
    public function test_un_type_sans_configuration_particuliere_reste_generique(): void
    {
        (new TypeDocumentSeeder)->run();

        $this->assertDatabaseHas('type_documents', [
            'code' => 'CONTRAT',
            'champs_diplome_actifs' => false,
            'champs_soutenance_actifs' => false,
            'champ_semestre_actif' => false,
            'epingle_secretariat' => false,
        ]);
    }
}
