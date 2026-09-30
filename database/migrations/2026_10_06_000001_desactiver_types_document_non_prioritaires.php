<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nettoyage du menu (demande explicite du client, "il y'a trop d'élément
 * dessus dont on a plus besoin pour le moment... on reconstruira au fur et
 * à mesure qu'on avance") : désactive les types de document qui ne sont pas
 * encore réellement utilisés au quotidien, pour ne garder dans les menus
 * (sidebar "Documents" > "Types de documents", formulaire "Nouveau
 * document") que les trois volets déjà construits (PV de soutenance, PV de
 * délibération, Relevés de notes) plus "Autre" comme filet de sécurité
 * générique.
 *
 * Une MIGRATION plutôt qu'un simple changement dans TypeDocumentSeeder
 * (voir son docblock, désormais mis à jour pour ne plus jamais écraser
 * `actif` sur une ligne déjà existante) : appliquer ce changement une seule
 * fois, ici, permet au seeder de redevenir rejouable sans danger ensuite —
 * un `php artisan db:seed --class=TypeDocumentSeeder` lors d'un futur
 * déploiement ne doit jamais annuler en silence une réactivation que le
 * client aura faite entre-temps depuis Administration > Référentiels >
 * Types de documents.
 *
 * Aucune donnée supprimée : les documents déjà classés sous l'un de ces
 * types restent inchangés et consultables (voir "Tous les documents",
 * filtrable par type) — seuls les MENUS et le formulaire de création
 * changent. Réversible en un clic, sans redéploiement, depuis
 * l'administration (ou via `down()` ci-dessous).
 */
return new class extends Migration
{
    /**
     * Les 23 codes désactivés — tout le reste de $types dans
     * TypeDocumentSeeder moins PV_SOUTENANCE/PV_DELIBERATION/RELEVE_NOTES/
     * AUTRE (voir TypeDocumentSeeder::CODES_ACTIFS_PAR_DEFAUT).
     */
    private const CODES_DESACTIVES = [
        'ATTESTATION', 'CONGE', 'BILAN_COURS_LIGNE', 'BORDEREAU', 'CANDIDAT_BTS',
        'CAHIER_PEDAGOGIQUE', 'CAHIER_CHARGE_MINISTERE', 'DOSSIER_EXTENSION_FILIERE',
        'CONSEIL_ETABLISSEMENT', 'CONSEIL_SCIENTIFIQUE', 'CONTRAT', 'COURRIER',
        'NOTICE_RENSEIGNEMENT', 'ORDRE_MISSION', 'PV_REUNION', 'SYLLABUS_FILIERES',
        'UNUPEL', 'CANEVAS_RAPPORT_RENTREE', 'LISTE_PERSONNEL', 'LISTE_DIPLOME',
        'NOTE_INFORMATION', 'ORGANIGRAMME', 'SPOT_TELE_RADIO',
    ];

    public function up(): void
    {
        DB::table('type_documents')->whereIn('code', self::CODES_DESACTIVES)->update(['actif' => false]);
    }

    public function down(): void
    {
        DB::table('type_documents')->whereIn('code', self::CODES_DESACTIVES)->update(['actif' => true]);
    }
};
