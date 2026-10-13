<?php

namespace Database\Seeders;

use App\Models\TypeDocument;
use Illuminate\Database\Seeder;

/**
 * Référentiel des types de documents — REFONTE COMPLÈTE (sur demande du
 * client, qui a recensé la liste réelle des documents gérés à INGRIDD, bien
 * plus riche que les quelques types génériques de départ).
 *
 * Principe (même logique que le passage Diplôme/Attestation avant elle) :
 * `updateOrCreate(['code' => ...], [...])`, jamais un `delete()` — un type
 * qui existe déjà (même code) est simplement renommé/réordonné, un document
 * déjà classé dedans ne perd donc jamais son type. Pour les types de
 * l'ancienne liste qui n'ont PAS d'équivalent évident dans la nouvelle
 * (`LISTE_ETUDIANTS`, `DIPLOME`, `FACTURE`, `RAPPORT`), on les désactive
 * (`actif = false`) plutôt que de les supprimer : les documents déjà
 * classés dedans restent visibles/modifiables tels quels (voir
 * DocumentResource, `Select::make('type_document_id')` ne propose que les
 * types actifs pour un NOUVEAU document), mais on arrête d'en proposer de
 * nouveaux avec ce type précis.
 *
 * Trois types de l'ancienne liste sont réutilisés tels quels par la
 * nouvelle (mêmes documents déjà classés, aucune perte) :
 * - `ATTESTATION` (libellé déjà identique) — garde ses champs dédiés
 *   (titulaire, numéro, date de délivrance, mention, statut de retrait, voir
 *   App\Support\TypesDiplome et DocumentResource) et reçoit en plus des
 *   sous-types (voir SousTypeDocumentSeeder) ;
 * - `PV_SOUTENANCE` (libellé déjà identique) — garde ses champs dédiés
 *   (thème, date de soutenance, jury, voir App\Support\TypesPvSoutenance) ;
 * - `PV_DELIBERATION` → renommé "Délibération", reçoit des sous-types
 *   (Licence/Master) mais AUCUN champ dédié nouveau : sur demande explicite
 *   du client, les champs dédiés existants restent réservés aux deux types
 *   ci-dessus, pas étendus à "Délibération" ni aux nouveaux types.
 * `CONTRAT`, `COURRIER` et `RELEVE_NOTES` sont aussi réutilisés (juste
 * renommés) : `CONTRAT` → "Contrats", `COURRIER` → "Correspondance et
 * lettre de partenariat", `RELEVE_NOTES` → "Relevés de notes".
 *
 * Quelques libellés fournis par le client ont été légèrement corrigés
 * (fautes de frappe/orthographe : "Carnevas" → "Canevas", "rentré" →
 * "rentrée", "personel" → "personnel", "Diplomé" → "diplômés", "cahier de
 * charge" → "cahier des charges") — le sens n'a pas changé.
 *
 * `AUTRE` ("Autre") est conservé activement en plus de la liste du client,
 * comme filet de sécurité générique pour un document qui ne correspond à
 * aucun type précis — sans ça, un tel document n'aurait litéralement aucun
 * type possible à choisir.
 *
 * Quatre types avaient été désignés comme priorité de mise en place de
 * l'espace de gestion (sur demande explicite du client, "avant de revoir
 * les autres"), chacun avec ses champs complémentaires propres :
 * - `ATTESTATION` (et `DIPLOME`, son équivalent retiré, voir plus bas) —
 *   "Gestion des diplômes" : `champs_diplome_actifs`.
 * - `PV_SOUTENANCE` — "Registre des soutenances" (numérisation du registre
 *   papier, voir App\Support\TypesPvSoutenance) : `champs_soutenance_actifs`.
 * - `RELEVE_NOTES` — "Gestion des relevés de notes" : `champ_semestre_actif`.
 * - `PV_DELIBERATION` — "Gestion des PV de délibération" : volontairement
 *   SANS champ complémentaire (voir le docblock plus haut : demande
 *   explicite du client de ne pas étendre les champs dédiés à ce type).
 *
 * À l'origine, ces quatre types recevaient aussi `epingle_secretariat =
 * true` — une entrée dans le menu "Secrétariat" (voir
 * resources/views/layouts/gestion.blade.php) pointant vers une page
 * générique listant les App\Models\Document de ce type (voir
 * SecretariatController). PV_SOUTENANCE, RELEVE_NOTES et PV_DELIBERATION
 * ont depuis chacun reçu un véritable écran dédié — App\Models\PvSoutenance/
 * SessionDeliberation/SessionReleve, avec leur propre lien direct dans la
 * sidebar (voir PvSoutenanceController/SessionDeliberationController/
 * SessionReleveController) — bien plus complet que la simple liste de
 * fichiers du Secrétariat (un enregistrement par étudiant/session, pas
 * juste un fichier). `epingle_secretariat` est donc retiré des trois pour
 * ne plus avoir deux chemins différents vers la même chose (demande
 * explicite du client, "plus de doublons"). Seul `ATTESTATION`, qui n'a pas
 * encore d'écran dédié équivalent, garde son entrée Secrétariat.
 *
 * Les documents déjà déposés via le Secrétariat pour ces trois types AVANT
 * ce changement restent inchangés en base (toujours de vrais
 * App\Models\Document, `type_document_id` intact) — mais ne sont PLUS
 * consultables depuis "Tous les documents" (voir App\Support\TypesEcranDedie
 * et le docblock de App\Http\Controllers\DocumentController::index() : un
 * document d'écran dédié en est désormais exclu de la liste générale, faille
 * de sécurité corrigée, "revoir la sécurité pour éviter l'accessibilité à
 * des pages confidentielles sans autorisation") ; seul leur écran dédié
 * respectif (PvSoutenanceController/SessionDeliberationController/
 * SessionReleveController) les affiche désormais, le raccourci Secrétariat
 * ayant lui aussi disparu — aucune donnée n'est touchée, seule leur voie
 * d'accès change.
 *
 * Tout autre type reste utilisable normalement (liste générale des
 * documents, filtrable par type) mais sans entrée dédiée tant que l'admin
 * ne l'épingle pas lui-même depuis Administration > Référentiels > Types de
 * documents — voir la migration 2026_09_29_000021_... pour le détail de ces
 * quatre colonnes, toutes modifiables depuis l'administration sans toucher
 * au code.
 *
 * Menu allégé (demande explicite du client, "trop d'éléments dont on a plus
 * besoin pour le moment... on reconstruira au fur et à mesure qu'on
 * avance") : sur une INSTALLATION NEUVE, seuls `PV_SOUTENANCE`,
 * `PV_DELIBERATION`, `RELEVE_NOTES`, `PV_REUNION`, `DOSSIER_ETUDIANT`,
 * `ETAT_FINANCIER`, `RECETTE`, `DEPENSE`, `LISTE_ETUDIANTS_FINANCES`,
 * `CONTRAT` (les écrans dédiés déjà construits) et `AUTRE`
 * (filet de sécurité, voir plus haut) sont créés `actif = true` — voir
 * CODES_ACTIFS_PAR_DEFAUT ci-dessous. Sur la base déjà en service, ce même
 * changement a été appliqué une fois pour toutes par la migration
 * 2026_10_06_000001_desactiver_types_document_non_prioritaires (voir son
 * docblock) plutôt qu'ici. `DOSSIER_ETUDIANT`, `ETAT_FINANCIER`,
 * `RECETTE`, `DEPENSE` et `LISTE_ETUDIANTS_FINANCES`, arrivés plus tard
 * (avec leurs contrôleurs dédiés respectifs), n'ont pas besoin d'une
 * migration d'activation équivalente :
 * code entièrement NOUVEAU, absent de
 * `$types` avant leur ajout, la ligne n'existait donc pas encore sur la base
 * déjà en service — `updateOrCreate()` la crée directement avec le bon
 * `actif` (voir `run()` ci-dessous, `$actifExistant` vaut `null`). Une fois
 * une ligne créée, `actif` n'est PLUS JAMAIS réécrit par ce seeder — sinon
 * rejouer ce seeder lors d'un futur déploiement annulerait en silence une
 * réactivation faite entre-temps par le client depuis Administration >
 * Référentiels > Types de documents. Tous les autres types restent
 * entièrement définis dans `$types` ci-dessous (rien n'est supprimé de la
 * liste recensée par le client), juste invisibles pour l'instant dans les
 * menus et le formulaire de création tant qu'ils ne sont pas réactivés.
 *
 * `PV_REUNION` et `CONTRAT`, à l'inverse, existaient DÉJÀ avant d'obtenir
 * leur écran dédié (tous deux visés par la désactivation du
 * 2026_10_06_000001) : chacun a donc reçu sa PROPRE migration de
 * réactivation (2026_10_07_000003_activer_pv_reunion.php,
 * 2026_10_23_000004_activer_contrat.php) en plus de son ajout ici à
 * CODES_ACTIFS_PAR_DEFAUT (utile seulement pour une installation neuve).
 */
class TypeDocumentSeeder extends Seeder
{
    /**
     * Drapeaux de configuration (voir le docblock de la classe) par code de
     * type — absent de cette liste = tout à `false` (comportement
     * générique), même principe "présent seulement si différent du défaut"
     * que le reste de ce seeder.
     */
    protected const CONFIGURATION = [
        'ATTESTATION' => ['champs_diplome_actifs' => true, 'epingle_secretariat' => true],
        'DIPLOME' => ['champs_diplome_actifs' => true],
        // Pas (ou plus) d'epingle_secretariat pour ces trois-là : ils ont
        // désormais leur propre écran dédié (voir le docblock de la
        // classe) — le raccourci Secrétariat générique ferait double
        // emploi avec leur lien direct dans la sidebar.
        'PV_SOUTENANCE' => ['champs_soutenance_actifs' => true],
        'RELEVE_NOTES' => ['champ_semestre_actif' => true],
    ];

    /**
     * `actif` initial d'un type qui n'existe pas encore en base (voir le
     * docblock de la classe) — ignoré pour un type déjà existant, qui garde
     * toujours sa valeur actuelle. Liste volontairement courte pour
     * l'instant : à compléter au fur et à mesure que chaque nouveau volet
     * est prêt, sans jamais avoir besoin de revenir modifier autre chose
     * dans ce fichier.
     */
    protected const CODES_ACTIFS_PAR_DEFAUT = [
        'PV_SOUTENANCE', 'PV_DELIBERATION', 'RELEVE_NOTES', 'PV_REUNION', 'DOSSIER_ETUDIANT',
        'ETAT_FINANCIER', 'RECETTE', 'DEPENSE', 'LISTE_ETUDIANTS_FINANCES', 'CONTRAT', 'AUTRE',
    ];

    public function run(): void
    {
        $types = [
            ['code' => 'ATTESTATION', 'libelle' => 'Attestation', 'ordre' => 1],
            ['code' => 'CONGE', 'libelle' => 'Autorisation de congé', 'ordre' => 2],
            ['code' => 'BILAN_COURS_LIGNE', 'libelle' => 'Bilan des cours en ligne', 'ordre' => 3],
            ['code' => 'BORDEREAU', 'libelle' => 'Bordereau', 'ordre' => 4],
            ['code' => 'CANDIDAT_BTS', 'libelle' => 'Candidat BTS', 'ordre' => 5],
            ['code' => 'CAHIER_PEDAGOGIQUE', 'libelle' => 'Cahier pédagogique', 'ordre' => 6],
            ['code' => 'CAHIER_CHARGE_MINISTERE', 'libelle' => 'Cahier des charges (Ministère)', 'ordre' => 7],
            ['code' => 'DOSSIER_EXTENSION_FILIERE', 'libelle' => 'Dossier extension de filière', 'ordre' => 8],
            ['code' => 'CONSEIL_ETABLISSEMENT', 'libelle' => 'Conseil d\'établissement', 'ordre' => 9],
            ['code' => 'CONSEIL_SCIENTIFIQUE', 'libelle' => 'Conseil scientifique', 'ordre' => 10],
            ['code' => 'CONTRAT', 'libelle' => 'Contrats', 'ordre' => 11],
            ['code' => 'COURRIER', 'libelle' => 'Correspondance et lettre de partenariat', 'ordre' => 12],
            ['code' => 'PV_DELIBERATION', 'libelle' => 'Délibération', 'ordre' => 13],
            ['code' => 'NOTICE_RENSEIGNEMENT', 'libelle' => 'Notice de renseignement', 'ordre' => 14],
            ['code' => 'ORDRE_MISSION', 'libelle' => 'Ordre de mission', 'ordre' => 15],
            ['code' => 'RELEVE_NOTES', 'libelle' => 'Relevés de notes', 'ordre' => 16],
            ['code' => 'PV_REUNION', 'libelle' => 'PV de réunion', 'ordre' => 17],
            ['code' => 'SYLLABUS_FILIERES', 'libelle' => 'Syllabus des filières', 'ordre' => 18],
            ['code' => 'UNUPEL', 'libelle' => 'UNUPEL', 'ordre' => 19],
            ['code' => 'CANEVAS_RAPPORT_RENTREE', 'libelle' => 'Canevas de rapport de rentrée', 'ordre' => 20],
            ['code' => 'LISTE_PERSONNEL', 'libelle' => 'Liste du personnel', 'ordre' => 21],
            ['code' => 'LISTE_DIPLOME', 'libelle' => 'Listes des diplômés', 'ordre' => 22],
            ['code' => 'NOTE_INFORMATION', 'libelle' => 'Note d\'information', 'ordre' => 23],
            ['code' => 'ORGANIGRAMME', 'libelle' => 'Organigramme INGRIDD', 'ordre' => 24],
            ['code' => 'SPOT_TELE_RADIO', 'libelle' => 'Spot télé et radio', 'ordre' => 25],
            ['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'ordre' => 26],
            ['code' => 'DOSSIER_ETUDIANT', 'libelle' => 'Dossier étudiant', 'ordre' => 27],
            ['code' => 'ETAT_FINANCIER', 'libelle' => 'État financier', 'ordre' => 28],
            ['code' => 'RECETTE', 'libelle' => 'Recette', 'ordre' => 29],
            ['code' => 'DEPENSE', 'libelle' => 'Dépense', 'ordre' => 30],
            ['code' => 'LISTE_ETUDIANTS_FINANCES', 'libelle' => 'Liste des étudiants (finances)', 'ordre' => 31],
            ['code' => 'AUTRE', 'libelle' => 'Autre', 'ordre' => 99],
        ];

        $drapeauxParDefaut = [
            'champs_diplome_actifs' => false,
            'champs_soutenance_actifs' => false,
            'champ_semestre_actif' => false,
            'epingle_secretariat' => false,
        ];

        foreach ($types as $type) {
            $drapeaux = $drapeauxParDefaut;
            $drapeaux = array_merge($drapeaux, self::CONFIGURATION[$type['code']] ?? []);

            // `actif` n'est repris de CODES_ACTIFS_PAR_DEFAUT que pour une
            // ligne qui n'existe pas encore (première installation) — une
            // fois créée, sa valeur appartient à l'administrateur (voir le
            // docblock de la classe), ce seeder ne la touche plus jamais.
            $actifExistant = TypeDocument::query()->where('code', $type['code'])->value('actif');
            $actif = $actifExistant ?? in_array($type['code'], self::CODES_ACTIFS_PAR_DEFAUT, true);

            TypeDocument::query()->updateOrCreate(['code' => $type['code']], $type + $drapeaux + ['actif' => $actif]);
        }

        // Types de l'ancienne liste sans équivalent évident dans la nouvelle
        // — désactivés, jamais supprimés (voir le docblock ci-dessus). On
        // utilise `updateOrCreate` (et non un simple `update()` sur les
        // lignes existantes) : sur une base déjà en service, la ligne
        // existe déjà et est juste désactivée ; sur une base neuve
        // (nouvelle installation, tests), ces lignes n'existent pas encore
        // et doivent bien être créées désactivées, sinon un document
        // existant qui y serait déjà classé n'aurait plus de type valide.
        $typesRetires = [
            ['code' => 'LISTE_ETUDIANTS', 'libelle' => 'Liste des étudiants', 'ordre' => 90],
            ['code' => 'DIPLOME', 'libelle' => 'Diplôme', 'ordre' => 91],
            ['code' => 'FACTURE', 'libelle' => 'Facture', 'ordre' => 92],
            ['code' => 'RAPPORT', 'libelle' => 'Rapport', 'ordre' => 93],
        ];

        foreach ($typesRetires as $type) {
            $drapeaux = array_merge($drapeauxParDefaut, self::CONFIGURATION[$type['code']] ?? []);

            TypeDocument::query()->updateOrCreate(
                ['code' => $type['code']],
                ['libelle' => $type['libelle'], 'ordre' => $type['ordre'], 'actif' => false] + $drapeaux
            );
        }
    }
}
