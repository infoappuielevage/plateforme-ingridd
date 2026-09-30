<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\Semestres;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documents de démonstration — sur demande du client, pour que le tableau
 * de bord, les listes et les statistiques (répartition par type/sous-type,
 * retraits de diplômes/attestations, tendance sur 12 mois, écran
 * "Corbeille", menu "Secrétariat" avec une page par type de document...)
 * montrent immédiatement quelque chose de parlant, plutôt qu'un écran vide
 * après une première installation.
 *
 * VOLONTAIREMENT PAS appelé depuis DatabaseSeeder : contrairement aux
 * référentiels (types de documents, filières...), ce sont de fausses
 * données, pas un point de départ à garder en production. On l'exécute à la
 * demande :
 *
 *     php artisan db:seed --class=DocumentSeeder
 *
 * Chaque document créé ici a un vrai (petit) fichier écrit sur le disque
 * `documents`, pour que "Télécharger" fonctionne réellement — mais le
 * contenu est un texte de remplacement générique, pas un vrai PDF/Word/
 * Excel : inutile d'essayer de l'ouvrir comme un document bureautique.
 *
 * Chaque document de démo porte la mention "Document de démonstration" dans
 * sa description : c'est ce qui sert de garde-fou pour ne jamais rejouer ce
 * seeder deux fois (voir le début de run()), et c'est aussi le moyen le
 * plus simple de tous les retrouver plus tard (recherche globale du panneau
 * d'administration, ou Documents > filtre + recherche "démonstration") pour
 * les supprimer d'un coup avant une mise en production réelle.
 *
 * Mis à jour avec l'arrivée des écrans dédiés PV de soutenance/PV de
 * délibération/Relevés de notes (voir App\Models\PvSoutenance,
 * SessionDeliberation, SessionReleve) : ces trois types ne sont plus
 * illustrés par de simples Document "à plat" (l'ancien comportement, hérité
 * d'avant la refonte) mais par de VRAIS enregistrements dédiés, avec leurs
 * "cases" (voir PvSoutenanceFichier/SessionDeliberationFichier/
 * SessionReleveFichier) — sans quoi ces données de démonstration
 * n'apparaîtraient tout simplement pas sur les nouveaux écrans dédiés.
 */
class DocumentSeeder extends Seeder
{
    protected const DESCRIPTION_DEMO = 'Document de démonstration (généré par DocumentSeeder, à supprimer avant la mise en production réelle).';

    protected const CONTENU_DEMO = "Ceci est un contenu de remplacement généré automatiquement par DocumentSeeder pour illustrer la plateforme.\nCe n'est pas un vrai document.";

    public function run(): void
    {
        if (Document::query()->withTrashed()->where('description', self::DESCRIPTION_DEMO)->exists()) {
            $this->command?->warn('DocumentSeeder a déjà été exécuté (des documents de démonstration existent) : rien à refaire.');

            return;
        }

        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        $filieres = Filiere::query()->where('actif', true)->get();
        $niveaux = Niveau::query()->where('actif', true)->orderBy('ordre')->get();
        $anneesAcademiques = AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get();

        if (! $admin || $filieres->isEmpty() || $niveaux->isEmpty() || $anneesAcademiques->isEmpty()) {
            $this->command?->warn('DocumentSeeder : référentiels manquants (jouer RoleSeeder/FiliereSeeder/NiveauSeeder/AnneeAcademiqueSeeder d\'abord) — rien de généré.');

            return;
        }

        $anneeRecente = $anneesAcademiques->first();
        // SANS filtre `actif` (contrairement à avant le "menu allégé" — voir
        // TypeDocumentSeeder/la migration 2026_10_06_000001_...) : la plupart
        // des types ci-dessous (dont ATTESTATION) sont désormais désactivés
        // par défaut sur une base neuve, mais ça ne concerne que l'offre de
        // création d'un NOUVEAU document (voir DocumentController) — rien
        // n'empêche un type désactivé d'avoir déjà des documents classés
        // dedans (déposés avant sa désactivation, ou reclassés). Un document
        // de démonstration pour un type désactivé illustre d'ailleurs
        // exactement ce cas — même raisonnement déjà appliqué plus bas à
        // $typesRetires (section 5).
        $typeListeEtudiants = TypeDocument::query()->where('code', 'LISTE_ETUDIANTS')->first();
        $typePvDeliberation = TypeDocument::query()->where('code', 'PV_DELIBERATION')->first();
        $typeReleveNotes = TypeDocument::query()->where('code', 'RELEVE_NOTES')->first();
        $typeAttestation = TypeDocument::query()->where('code', 'ATTESTATION')->first();
        $typePvSoutenance = TypeDocument::query()->where('code', 'PV_SOUTENANCE')->first();
        // Liste mise à jour après la refonte complète du référentiel des
        // types (voir TypeDocumentSeeder) : couvre tous les types
        // administratifs génériques restants (sans champs dédiés, sans
        // filière/niveau), pour que chaque page dédiée du menu "Secrétariat"
        // ait au moins quelques documents à montrer — SANS filtre `actif`,
        // pour la même raison que ci-dessus (la quasi-totalité de cette
        // liste est désactivée par défaut depuis le "menu allégé").
        $typesGeneriques = TypeDocument::query()->whereIn('code', [
            'CONTRAT', 'COURRIER', 'CAHIER_PEDAGOGIQUE', 'CAHIER_CHARGE_MINISTERE',
            'DOSSIER_EXTENSION_FILIERE', 'CONSEIL_ETABLISSEMENT', 'CONSEIL_SCIENTIFIQUE',
            'CANDIDAT_BTS', 'NOTICE_RENSEIGNEMENT', 'ORDRE_MISSION', 'NOTE_INFORMATION',
            'ORGANIGRAMME', 'SPOT_TELE_RADIO', 'SYLLABUS_FILIERES', 'UNUPEL',
            'LISTE_PERSONNEL', 'LISTE_DIPLOME', 'PV_REUNION', 'CANEVAS_RAPPORT_RENTREE', 'AUTRE',
        ])->get();

        // Sous-types actifs, regroupés par code du type parent (voir
        // App\Models\SousTypeDocument) — calculé une seule fois, réutilisé
        // par les sections 1bis, 2, 3 et 3ter ci-dessous pour illustrer le
        // champ "Sous-type" sur quelques documents de démonstration.
        $sousTypesParType = SousTypeDocument::query()
            ->where('actif', true)
            ->with('typeDocument:id,code')
            ->orderBy('ordre')
            ->get()
            ->groupBy(fn (SousTypeDocument $sousType) => $sousType->typeDocument?->code);

        $sousTypeParLibelle = fn (string $codeType, string $libelle) => $sousTypesParType
            ->get($codeType, collect())
            ->firstWhere('libelle', $libelle)?->id;

        $niveauPourNumeroSemestre = fn (int $numero) => $niveaux->firstWhere('code', Semestres::codeNiveauPourSemestre($numero));

        $documentsCrees = [];
        $pvSoutenancesCreees = [];
        $sessionsDeliberationCreees = [];
        $sessionsReleveCreees = [];

        // --------------------------------------------------------------
        // 1) Un document "Liste des étudiants" par filière/niveau (année
        // académique la plus récente). Le PV de délibération, lui, a
        // désormais son propre écran dédié — voir section 1bis ci-dessous.
        // --------------------------------------------------------------
        foreach ($filieres as $filiere) {
            foreach ($niveaux as $niveau) {
                if ($typeListeEtudiants) {
                    $documentsCrees[] = $this->creerDocument(
                        $admin,
                        "Liste des étudiants - {$filiere->code} {$niveau->code} ({$anneeRecente->libelle})",
                        [
                            'type_document_id' => $typeListeEtudiants->id,
                            'filiere_id' => $filiere->id,
                            'niveau_id' => $niveau->id,
                            'annee_academique_id' => $anneeRecente->id,
                        ],
                        extension: 'xlsx',
                    );
                }
            }
        }

        // --------------------------------------------------------------
        // 1bis) Sessions de délibération : une par filière et par cycle
        // (Licence/Master — pas Doctorat, pour ne pas générer un nombre
        // excessif de documents), pour la session la plus récente. Voir
        // App\Models\SessionDeliberation : contrairement à un simple
        // Document, UNE session couvre tout un cycle (ex. "Licence" = L1 à
        // L3), pas un seul niveau — d'où un enregistrement par filière x
        // cycle plutôt que par filière x niveau comme avant la refonte.
        //
        // Les "cases" sont recréées ici à l'identique de
        // SessionDeliberationController::creerCasesVides() (deux notes
        // d'information + une case par semestre/année de thèse), puis
        // seules les 3 premières sont remplies d'un document — une session
        // récemment créée, en cours de constitution, est plus représentative
        // qu'une session déjà entièrement complète.
        // --------------------------------------------------------------
        if ($typePvDeliberation) {
            foreach ($filieres as $filiere) {
                foreach ([SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_MASTER] as $cycle) {
                    $session = SessionDeliberation::create([
                        'annee_academique_id' => $anneeRecente->id,
                        'session' => SessionDeliberation::SESSION_PREMIERE,
                        'cycle' => $cycle,
                        'filiere_id' => $filiere->id,
                        'created_by' => $admin->id,
                    ]);
                    $sessionsDeliberationCreees[] = $session;

                    $cases = [];
                    foreach (array_keys(SessionDeliberationFichier::ROLES_NOTES) as $role) {
                        $cases[] = SessionDeliberationFichier::create([
                            'session_deliberation_id' => $session->id,
                            'role' => $role,
                            'numero' => null,
                            'document_id' => null,
                        ]);
                    }

                    $roleNumerote = $session->roleNumerote();
                    foreach ($session->numerosAttendus() as $numero) {
                        $cases[] = SessionDeliberationFichier::create([
                            'session_deliberation_id' => $session->id,
                            'role' => $roleNumerote,
                            'numero' => $numero,
                            'document_id' => null,
                        ]);
                    }

                    $sousType = $sousTypeParLibelle('PV_DELIBERATION', $cycle === SessionDeliberation::CYCLE_MASTER ? 'Master' : 'Licence');

                    foreach (array_slice($cases, 0, 3) as $case) {
                        $document = $this->creerDocument(
                            $admin,
                            "Délibération {$session->cycleLibelle()} {$session->sessionLibelle()} - {$filiere->code} - {$case->libelle()}",
                            [
                                'type_document_id' => $typePvDeliberation->id,
                                'sous_type_document_id' => $sousType,
                                'filiere_id' => $filiere->id,
                                'niveau_id' => $case->role === $roleNumerote ? $niveauPourNumeroSemestre($case->numero)?->id : null,
                                'annee_academique_id' => $anneeRecente->id,
                                'semestre' => $case->role === SessionDeliberationFichier::ROLE_SEMESTRE ? $case->numero : null,
                            ],
                        );
                        $case->update(['document_id' => $document->id]);
                        $documentsCrees[] = $document;
                    }
                }
            }
        }

        // --------------------------------------------------------------
        // 2) Sessions de relevés de notes : sur deux filières seulement
        // (pas toutes, pour ne pas générer un nombre excessif de
        // documents), une session par cycle (Licence/Master) pour la
        // session la plus récente — voir App\Models\SessionReleve, miroir
        // de SessionDeliberation ci-dessus mais sans les deux cases "Note
        // d'information" (voir SessionReleveFichier). Environ la moitié des
        // cases numérotées est déjà remplie d'un document.
        // --------------------------------------------------------------
        if ($typeReleveNotes) {
            foreach ($filieres->take(2) as $filiere) {
                foreach ([SessionReleve::CYCLE_LICENCE, SessionReleve::CYCLE_MASTER] as $cycle) {
                    $session = SessionReleve::create([
                        'annee_academique_id' => $anneeRecente->id,
                        'session' => SessionReleve::SESSION_PREMIERE,
                        'cycle' => $cycle,
                        'filiere_id' => $filiere->id,
                        'created_by' => $admin->id,
                    ]);
                    $sessionsReleveCreees[] = $session;

                    $roleNumerote = $session->roleNumerote();
                    $cases = [];
                    foreach ($session->numerosAttendus() as $numero) {
                        $cases[] = SessionReleveFichier::create([
                            'session_releve_id' => $session->id,
                            'role' => $roleNumerote,
                            'numero' => $numero,
                            'document_id' => null,
                        ]);
                    }

                    $sousType = $sousTypeParLibelle('RELEVE_NOTES', $cycle === SessionReleve::CYCLE_MASTER ? 'Master' : 'Licence');

                    foreach ($cases as $index => $case) {
                        if ($index % 2 !== 0) {
                            continue;
                        }

                        $document = $this->creerDocument(
                            $admin,
                            "Relevé de notes {$session->cycleLibelle()} - {$filiere->code} - {$case->libelle()}",
                            [
                                'type_document_id' => $typeReleveNotes->id,
                                'sous_type_document_id' => $sousType,
                                'filiere_id' => $filiere->id,
                                'niveau_id' => $niveauPourNumeroSemestre($case->numero)?->id,
                                'annee_academique_id' => $anneeRecente->id,
                                'semestre' => $case->role === SessionReleveFichier::ROLE_SEMESTRE ? $case->numero : null,
                            ],
                        );
                        $case->update(['document_id' => $document->id]);
                        $documentsCrees[] = $document;
                    }
                }
            }
        }

        // --------------------------------------------------------------
        // 3) Attestations, avec titulaire/numéro/date de délivrance/
        // mention, et un mélange retiré/non retiré — pour que les widgets
        // de retrait (tableau de bord, écran Documents) aient vraiment
        // quelque chose à montrer. Le sous-type (Succès, Vacation,
        // Inscription...) est réparti sur tous les étudiants tour à tour
        // (plutôt que tiré au hasard) pour être sûr que CHAQUE sous-type ait
        // au moins un document de démonstration — utile car chacun a
        // désormais sa propre page dans le menu "Secrétariat".
        // --------------------------------------------------------------
        $etudiants = [
            'OUEDRAOGO Awa', 'KABORE Issa', 'SAWADOGO Fatimata', 'ZONGO Boureima',
            'COMPAORE Aminata', 'TRAORE Moussa', 'SANOU Rasmata', 'BAMBARA Idrissa',
            'NIKIEMA Salamata', 'OUATTARA Boubacar',
        ];
        $mentions = ['Passable', 'Assez bien', 'Bien', 'Très bien'];
        $numeroDiplome = 1;
        $sousTypesAttestation = $sousTypesParType->get('ATTESTATION', collect());

        if ($typeAttestation) {
            foreach ($etudiants as $index => $nomTitulaire) {
                $filiere = $filieres->random();
                $niveau = $niveaux->firstWhere('code', 'L3') ?? $niveaux->last();
                // Environ deux tiers déjà retirées, le reste encore en attente.
                $estRetiree = $index % 3 !== 0;

                $sousType = $sousTypesAttestation->isNotEmpty()
                    ? $sousTypesAttestation[$index % $sousTypesAttestation->count()]
                    : null;

                $documentsCrees[] = $this->creerDocument(
                    $admin,
                    "{$typeAttestation->libelle}".($sousType ? " {$sousType->libelle}" : '')." - {$nomTitulaire}",
                    [
                        'type_document_id' => $typeAttestation->id,
                        'sous_type_document_id' => $sousType?->id,
                        'filiere_id' => $filiere->id,
                        'niveau_id' => $niveau->id,
                        'annee_academique_id' => $anneeRecente->id,
                        'nom_titulaire' => $nomTitulaire,
                        'numero_diplome' => sprintf('ATTESTATION-%04d', $numeroDiplome++),
                        'date_delivrance' => now()->subMonths(random_int(1, 10))->toDateString(),
                        'mention' => $mentions[array_rand($mentions)],
                        'statut_retrait' => $estRetiree ? Document::STATUT_RETRAIT_RETIRE : Document::STATUT_RETRAIT_NON_RETIRE,
                        'date_retrait' => $estRetiree ? now()->subDays(random_int(1, 60))->toDateString() : null,
                        'retrait_enregistre_par_id' => $estRetiree ? $admin->id : null,
                    ],
                );
            }
        }

        // --------------------------------------------------------------
        // 3bis) PV de soutenance : un enregistrement par étudiant — voir
        // App\Models\PvSoutenance (remplace l'ancien Document "à plat" par
        // la vraie structure dédiée, avec ses deux cases fixes "Diplôme"/
        // "Image du registre", recréées ici à l'identique de
        // PvSoutenanceController::creerCasesVides()). Numéros d'ordre de
        // registre et de PV générés séquentiellement (uniques, comme
        // l'exige PvSoutenanceController::valider()).
        // --------------------------------------------------------------
        if ($typePvSoutenance) {
            $themesMemoire = [
                'Digitalisation des processus administratifs dans les PME burkinabè',
                'Impact du changement climatique sur l\'agriculture en zone sahélienne',
                'Gestion des ressources humaines dans le secteur informel',
                'Contribution des technologies mobiles à l\'inclusion financière',
            ];
            $lieuxNaissance = ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Ouahigouya', 'Banfora'];
            $jurys = [
                ['president' => 'Pr. OUEDRAOGO Jean', 'directeur' => 'Dr. KABORE Aïcha', 'rapporteur' => 'M. SAWADOGO Paul'],
                ['president' => 'Dr. TRAORE Fatou', 'directeur' => 'Pr. ZONGO Marc', 'rapporteur' => 'Mme COMPAORE Rita'],
            ];

            foreach ($etudiants as $index => $nomComplet) {
                [$nom, $prenoms] = array_pad(explode(' ', $nomComplet, 2), 2, '');
                $jury = $jurys[$index % count($jurys)];
                // Environ deux tiers déjà retirés (même répartition que les
                // attestations ci-dessus), le reste encore en attente.
                $estRetire = $index % 3 !== 0;

                $pvSoutenance = PvSoutenance::create([
                    'niveau' => PvSoutenance::NIVEAU_MASTER,
                    'date_soutenance' => now()->subMonths(random_int(1, 8))->toDateString(),
                    'numero_ordre_registre' => sprintf('REG-%04d', $index + 1),
                    'nom' => $nom,
                    'prenoms' => $prenoms,
                    'date_naissance' => now()->subYears(random_int(22, 27))->subDays(random_int(0, 365))->toDateString(),
                    'lieu_naissance' => $lieuxNaissance[$index % count($lieuxNaissance)],
                    'numero_pv' => sprintf('PV-%04d', $index + 1),
                    'filiere_id' => $filieres->random()->id,
                    'theme' => $themesMemoire[$index % count($themesMemoire)],
                    'decision_jury' => PvSoutenance::DECISION_ADMIS,
                    'moyenne' => random_int(100, 180) / 10,
                    'mention' => $mentions[array_rand($mentions)],
                    'president_jury' => $jury['president'],
                    'directeur_memoire' => $jury['directeur'],
                    'rapporteur' => $jury['rapporteur'],
                    'maitre_stage' => null,
                    'created_by' => $admin->id,
                ]);
                $pvSoutenancesCreees[] = $pvSoutenance;

                $casesParRole = [];
                foreach (array_keys(PvSoutenanceFichier::ROLES_FIXES) as $role) {
                    $casesParRole[$role] = PvSoutenanceFichier::create([
                        'pv_soutenance_id' => $pvSoutenance->id,
                        'role' => $role,
                        'document_id' => null,
                    ]);
                }

                // Image du registre : toujours déposée (la numérisation est
                // faite en même temps que la saisie du PV).
                $documentRegistre = $this->creerDocument(
                    $admin,
                    "PV de soutenance - {$pvSoutenance->nomComplet()} - Image du registre",
                    [
                        'type_document_id' => $typePvSoutenance->id,
                        'filiere_id' => $pvSoutenance->filiere_id,
                        'theme_memoire' => $pvSoutenance->theme,
                        'date_soutenance' => $pvSoutenance->date_soutenance,
                    ],
                );
                $casesParRole[PvSoutenanceFichier::ROLE_IMAGE_REGISTRE]->update(['document_id' => $documentRegistre->id]);
                $documentsCrees[] = $documentRegistre;

                // Diplôme : environ deux tiers déjà déposés et retirés, le
                // reste encore en attente — voir le docblock de la migration
                // 2026_10_05_000001_add_retrait_diplome_to_pv_soutenances_table.php
                // pour le détail des champs de retrait.
                if ($estRetire) {
                    $documentDiplome = $this->creerDocument(
                        $admin,
                        "PV de soutenance - {$pvSoutenance->nomComplet()} - Diplôme",
                        [
                            'type_document_id' => $typePvSoutenance->id,
                            'filiere_id' => $pvSoutenance->filiere_id,
                            'theme_memoire' => $pvSoutenance->theme,
                            'date_soutenance' => $pvSoutenance->date_soutenance,
                        ],
                    );
                    $casesParRole[PvSoutenanceFichier::ROLE_DIPLOME]->update(['document_id' => $documentDiplome->id]);
                    $documentsCrees[] = $documentDiplome;

                    $pvSoutenance->update([
                        'diplome_retirant' => PvSoutenance::RETIRANT_ETUDIANT,
                        'diplome_date_retrait' => now()->subDays(random_int(1, 60))->toDateString(),
                        'diplome_retrait_enregistre_par_id' => $admin->id,
                        'diplome_retrait_enregistre_le' => now()->subDays(random_int(0, 60)),
                    ]);
                }
            }
        }

        // --------------------------------------------------------------
        // 3ter) Quelques exemples avec sous-type, pour des types emblématiques
        // de la refonte du référentiel (Bordereau, Autorisation de congé,
        // Bilan des cours en ligne) — pour que le champ/filtre "Sous-type"
        // ait, lui aussi, quelque chose à montrer dès la première installation.
        // --------------------------------------------------------------
        $exemplesAvecSousType = [
            ['code' => 'BORDEREAU', 'libelle' => 'Bordereau de versement', 'nombre' => 4],
            ['code' => 'CONGE', 'libelle' => 'Autorisation de congé', 'nombre' => 3],
            ['code' => 'BILAN_COURS_LIGNE', 'libelle' => 'Bilan des cours', 'nombre' => 2],
        ];

        foreach ($exemplesAvecSousType as $exemple) {
            // SANS filtre `actif` (les trois codes ci-dessus sont désactivés
            // par défaut depuis le "menu allégé") — même raisonnement que les
            // lookups en tête de run().
            $type = TypeDocument::query()->where('code', $exemple['code'])->first();
            $sousTypesDuType = $sousTypesParType->get($exemple['code'], collect());

            if (! $type || $sousTypesDuType->isEmpty()) {
                continue;
            }

            foreach (range(1, $exemple['nombre']) as $i) {
                /** @var SousTypeDocument $sousType */
                $sousType = $sousTypesDuType->random();

                $documentsCrees[] = $this->creerDocument(
                    $admin,
                    "{$exemple['libelle']} - {$sousType->libelle} #{$i}",
                    [
                        'type_document_id' => $type->id,
                        'sous_type_document_id' => $sousType->id,
                    ],
                    ajouteLe: now()->subDays(random_int(0, 200)),
                );
            }
        }

        // --------------------------------------------------------------
        // 4) Documents administratifs génériques, sans filière/niveau/
        // année, étalés sur les 12 derniers mois pour que le graphique
        // "Documents par mois" du tableau de bord montre une vraie
        // tendance plutôt qu'un seul pic sur le mois en cours.
        // --------------------------------------------------------------
        if ($typesGeneriques->isNotEmpty()) {
            $libellesGeneriques = [
                'Contrat de prestation de services',
                'Correspondance avec un partenaire',
                'Cahier pédagogique du semestre',
                'Cahier des charges du Ministère',
                'Dossier d\'extension de filière',
                'Compte rendu du conseil d\'établissement',
                'Compte rendu du conseil scientifique',
                'Dossier de candidature BTS',
                'Notice de renseignement',
                'Ordre de mission',
                'Note d\'information',
                'Organigramme mis à jour',
                'Spot télé et radio',
                'Syllabus de filière',
                'UNUPEL',
                'Liste du personnel',
                'Liste des diplômés',
                'PV de réunion de service',
                'Canevas de rapport de rentrée',
            ];

            foreach (range(1, 20) as $i) {
                $documentsCrees[] = $this->creerDocument(
                    $admin,
                    $libellesGeneriques[array_rand($libellesGeneriques)]." #{$i}",
                    ['type_document_id' => $typesGeneriques->random()->id],
                    ajouteLe: now()->subDays(random_int(0, 365)),
                );
            }
        }

        // --------------------------------------------------------------
        // 5) Anciens types retirés du référentiel (Liste des étudiants,
        // Diplôme, Facture, Rapport — voir TypeDocumentSeeder) : quelques
        // documents historiques, pour que leur page dédiée dans le menu
        // "Secrétariat" (conservée pour les documents déjà classés dedans)
        // ne soit pas vide. Recherche SANS le filtre `actif` (contrairement
        // à tous les types ci-dessus) : ces lignes existent bien en base,
        // juste désactivées pour qu'on ne puisse plus en choisir pour un
        // NOUVEAU document — un document de démonstration peut donc
        // parfaitement y être classé, comme un vrai document ancien le
        // serait.
        // --------------------------------------------------------------
        $typesRetires = TypeDocument::query()
            ->whereIn('code', ['LISTE_ETUDIANTS', 'DIPLOME', 'FACTURE', 'RAPPORT'])
            ->get();

        foreach ($typesRetires as $typeRetire) {
            foreach (range(1, 2) as $i) {
                $documentsCrees[] = $this->creerDocument(
                    $admin,
                    "{$typeRetire->libelle} (archive) #{$i}",
                    ['type_document_id' => $typeRetire->id],
                    ajouteLe: now()->subYears(1)->subDays(random_int(0, 300)),
                );
            }
        }

        // --------------------------------------------------------------
        // 6) Quelques enregistrements mis à la corbeille, pour que les
        // écrans "Corbeille" (le générique, sur Documents, ET ceux propres
        // à chaque écran dédié — voir PvSoutenanceController::index()/
        // SessionDeliberationController::index()/SessionReleveController::index(),
        // filtre `corbeille`) aient du contenu dès le départ.
        // --------------------------------------------------------------
        // ->random(0) sur une collection vide renvoie simplement une
        // collection vide (pas d'exception) : sûr même si $documentsCrees
        // est vide (référentiels/types tous manquants, cas déjà couvert par
        // les vérifications ci-dessus, mais on ne suppose rien).
        collect($documentsCrees)
            ->random(min(3, count($documentsCrees)))
            ->each(fn (Document $document) => $document->delete());

        collect($pvSoutenancesCreees)
            ->random(min(1, count($pvSoutenancesCreees)))
            ->each(fn (PvSoutenance $pvSoutenance) => $pvSoutenance->delete());

        collect($sessionsDeliberationCreees)
            ->random(min(1, count($sessionsDeliberationCreees)))
            ->each(fn (SessionDeliberation $session) => $session->delete());

        collect($sessionsReleveCreees)
            ->random(min(1, count($sessionsReleveCreees)))
            ->each(fn (SessionReleve $session) => $session->delete());

        $this->command?->info(count($documentsCrees).' document(s) de démonstration créé(s) (dont '.count($pvSoutenancesCreees).' PV de soutenance, '.count($sessionsDeliberationCreees).' session(s) de délibération et '.count($sessionsReleveCreees).' session(s) de relevé).');
    }

    /**
     * Crée un document de démo avec un vrai fichier physique sur le disque
     * `documents` (pour que "Télécharger" fonctionne). `$libelle` ne sert
     * qu'à générer un nom de fichier lisible et le contenu du fichier de
     * remplacement — il n'est plus enregistré sur le document lui-même (le
     * champ "Titre" a été retiré, sur demande du client : il faisait doublon
     * avec le nom du fichier d'origine).
     *
     * `$ajouteLe` permet d'antidater `created_at`/`updated_at` (pour étaler
     * les documents administratifs génériques sur 12 mois) — fait via une
     * requête directe (Illuminate\Support\Facades\DB), pas via le modèle,
     * pour ne pas déclencher une seconde entrée "modification" dans
     * l'historique (App\Models\Concerns\Auditable) juste pour antidater une
     * date.
     */
    protected function creerDocument(User $admin, string $libelle, array $attributs, string $extension = 'pdf', ?Carbon $ajouteLe = null): Document
    {
        $nomOriginal = Str::limit(Str::slug($libelle, '-'), 80, '').'.'.$extension;
        $chemin = 'uploads/'.Str::uuid().'.'.$extension;

        Storage::disk('documents')->put($chemin, self::CONTENU_DEMO."\n\n".$libelle);

        $document = Document::create(array_merge($attributs, [
            'description' => self::DESCRIPTION_DEMO,
            'chemin' => $chemin,
            'nom_original' => $nomOriginal,
            'extension' => $extension,
            'taille' => Storage::disk('documents')->size($chemin),
            'uploaded_by' => $admin->id,
        ]));

        if ($ajouteLe) {
            DB::table('documents')->where('id', $document->id)->update([
                'created_at' => $ajouteLe,
                'updated_at' => $ajouteLe,
            ]);
        }

        return $document;
    }
}
