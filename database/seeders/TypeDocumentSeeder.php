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
 * Quatre types sont désignés comme priorité de mise en place de l'espace de
 * gestion (sur demande explicite du client, "avant de revoir les autres") —
 * `epingle_secretariat = true` leur donne une entrée dédiée dans le menu
 * Secrétariat (voir resources/views/layouts/gestion.blade.php) :
 * - `ATTESTATION` (et `DIPLOME`, son équivalent retiré, voir plus bas) —
 *   "Gestion des diplômes" : `champs_diplome_actifs`.
 * - `PV_SOUTENANCE` — "Registre des soutenances" (numérisation du registre
 *   papier, voir App\Support\TypesPvSoutenance) : `champs_soutenance_actifs`.
 * - `RELEVE_NOTES` — "Gestion des relevés de notes" : `champ_semestre_actif`.
 * - `PV_DELIBERATION` — "Gestion des PV de délibération" : épinglé, mais
 *   volontairement SANS champ complémentaire (voir le docblock plus haut :
 *   demande explicite du client de ne pas étendre les champs dédiés à ce
 *   type).
 *
 * Tout autre type reste utilisable normalement (liste générale des
 * documents, filtrable par type) mais sans entrée dédiée tant que l'admin
 * ne l'épingle pas lui-même depuis Administration > Référentiels > Types de
 * documents — voir la migration 2026_09_29_000021_... pour le détail de ces
 * quatre colonnes, toutes modifiables depuis l'administration sans toucher
 * au code.
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
        'PV_SOUTENANCE' => ['champs_soutenance_actifs' => true, 'epingle_secretariat' => true],
        'RELEVE_NOTES' => ['champ_semestre_actif' => true, 'epingle_secretariat' => true],
        'PV_DELIBERATION' => ['epingle_secretariat' => true],
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
            ['code' => 'PV_REUNION', 'libelle' => 'PV réunions', 'ordre' => 17],
            ['code' => 'SYLLABUS_FILIERES', 'libelle' => 'Syllabus des filières', 'ordre' => 18],
            ['code' => 'UNUPEL', 'libelle' => 'UNUPEL', 'ordre' => 19],
            ['code' => 'CANEVAS_RAPPORT_RENTREE', 'libelle' => 'Canevas de rapport de rentrée', 'ordre' => 20],
            ['code' => 'LISTE_PERSONNEL', 'libelle' => 'Liste du personnel', 'ordre' => 21],
            ['code' => 'LISTE_DIPLOME', 'libelle' => 'Listes des diplômés', 'ordre' => 22],
            ['code' => 'NOTE_INFORMATION', 'libelle' => 'Note d\'information', 'ordre' => 23],
            ['code' => 'ORGANIGRAMME', 'libelle' => 'Organigramme INGRIDD', 'ordre' => 24],
            ['code' => 'SPOT_TELE_RADIO', 'libelle' => 'Spot télé et radio', 'ordre' => 25],
            ['code' => 'PV_SOUTENANCE', 'libelle' => 'PV de soutenance', 'ordre' => 26],
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

            TypeDocument::query()->updateOrCreate(['code' => $type['code']], $type + $drapeaux + ['actif' => true]);
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
