<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use App\Models\EtudiantInscription;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\Reunion;
use App\Models\ReunionFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\TypePieceEtudiant;
use App\Models\User;
use App\Support\Semestres;
use App\Support\TypesEcranDedie;
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
        $typeReunion = TypeDocument::query()->where('code', 'PV_REUNION')->first();
        $typeEtudiant = TypeDocument::query()->where('code', 'DOSSIER_ETUDIANT')->first();
        // Liste mise à jour après la refonte complète du référentiel des
        // types (voir TypeDocumentSeeder) : couvre tous les types
        // administratifs génériques restants (sans champs dédiés, sans
        // filière/niveau), pour que chaque page dédiée du menu "Secrétariat"
        // ait au moins quelques documents à montrer — SANS filtre `actif`,
        // pour la même raison que ci-dessus (la quasi-totalité de cette
        // liste est désactivée par défaut depuis le "menu allégé"). PV_REUNION
        // n'y figure plus : il a désormais son propre écran dédié (section
        // 3ter bis ci-dessous), comme PV_SOUTENANCE/PV_DELIBERATION/
        // RELEVE_NOTES avant lui.
        $typesGeneriques = TypeDocument::query()->whereIn('code', [
            'CONTRAT', 'COURRIER', 'CAHIER_PEDAGOGIQUE', 'CAHIER_CHARGE_MINISTERE',
            'DOSSIER_EXTENSION_FILIERE', 'CONSEIL_ETABLISSEMENT', 'CONSEIL_SCIENTIFIQUE',
            'CANDIDAT_BTS', 'NOTICE_RENSEIGNEMENT', 'ORDRE_MISSION', 'NOTE_INFORMATION',
            'ORGANIGRAMME', 'SPOT_TELE_RADIO', 'SYLLABUS_FILIERES', 'UNUPEL',
            'LISTE_PERSONNEL', 'LISTE_DIPLOME', 'CANEVAS_RAPPORT_RENTREE', 'AUTRE',
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
        $reunionsCreees = [];
        $etudiantsCrees = [];

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
        // (Licence/Master sur toutes les filières — Doctorat seulement sur
        // la première, pour ne pas générer un nombre excessif de
        // documents), pour la session la plus récente. Voir
        // App\Models\SessionDeliberation : contrairement à un simple
        // Document, UNE session couvre tout un cycle (ex. "Licence" = L1 à
        // L3), pas un seul niveau — d'où un enregistrement par filière x
        // cycle plutôt que par filière x niveau comme avant la refonte.
        //
        // Le Doctorat est ajouté ici (en plus de Licence/Master) pour que
        // l'onglet "Doctorat" de la liste — voir resources/views/gestion/sessions-deliberation/index.blade.php
        // et App\Models\SessionDeliberation::cycleBadgeClasse() — ait lui
        // aussi un exemple à afficher une fois les démos relancées (demande
        // explicite du client : "readapte a present les demos pour qu'on
        // voit a quoi sa resemble").
        //
        // Les "cases" sont recréées ici à l'identique de
        // SessionDeliberationController::creerCasesVides() (deux notes
        // d'information + une case par semestre/année de thèse), puis
        // seules les 3 premières sont remplies d'un document — une session
        // récemment créée, en cours de constitution, est plus représentative
        // qu'une session déjà entièrement complète.
        // --------------------------------------------------------------
        if ($typePvDeliberation) {
            foreach ($filieres as $filiereIndex => $filiere) {
                $cyclesPourCetteFiliere = $filiereIndex === 0
                    ? [SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_MASTER, SessionDeliberation::CYCLE_DOCTORAT]
                    : [SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_MASTER];

                foreach ($cyclesPourCetteFiliere as $cycle) {
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

                    // Pas de sous-type pour le Doctorat (ni de "niveau" pour
                    // ses cases numérotées, qui sont des années de thèse, pas
                    // des semestres) — même règle que
                    // SessionDeliberationController::sousTypePour()/niveauPour().
                    $estDoctorat = $cycle === SessionDeliberation::CYCLE_DOCTORAT;
                    $sousType = $estDoctorat ? null : $sousTypeParLibelle('PV_DELIBERATION', $cycle === SessionDeliberation::CYCLE_MASTER ? 'Master' : 'Licence');

                    foreach (array_slice($cases, 0, 3) as $case) {
                        $document = $this->creerDocument(
                            $admin,
                            "Délibération {$session->cycleLibelle()} {$session->sessionLibelle()} - {$filiere->code} - {$case->libelle()}",
                            [
                                'type_document_id' => $typePvDeliberation->id,
                                'sous_type_document_id' => $sousType,
                                'filiere_id' => $filiere->id,
                                'niveau_id' => ($case->role === $roleNumerote && ! $estDoctorat) ? $niveauPourNumeroSemestre($case->numero)?->id : null,
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
        // documents), une session par cycle Licence/Master pour la session
        // la plus récente — voir App\Models\SessionReleve, miroir de
        // SessionDeliberation ci-dessus mais sans les deux cases "Note
        // d'information" (voir SessionReleveFichier). Environ la moitié des
        // cases numérotées est déjà remplie d'un document. Le Doctorat est
        // ajouté en plus sur la première de ces deux filières, pour la même
        // raison que pour les PV de délibération ci-dessus (onglet "Doctorat"
        // de la liste à montrer, demande explicite du client).
        // --------------------------------------------------------------
        if ($typeReleveNotes) {
            foreach ($filieres->take(2) as $filiereIndex => $filiere) {
                $cyclesPourCetteFiliere = $filiereIndex === 0
                    ? [SessionReleve::CYCLE_LICENCE, SessionReleve::CYCLE_MASTER, SessionReleve::CYCLE_DOCTORAT]
                    : [SessionReleve::CYCLE_LICENCE, SessionReleve::CYCLE_MASTER];

                foreach ($cyclesPourCetteFiliere as $cycle) {
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

                    // Pas de sous-type ni de "niveau" pour le Doctorat — voir
                    // le commentaire équivalent sur les PV de délibération
                    // ci-dessus (même règle que SessionReleveController::
                    // sousTypePour()/niveauPour()).
                    $estDoctorat = $cycle === SessionReleve::CYCLE_DOCTORAT;
                    $sousType = $estDoctorat ? null : $sousTypeParLibelle('RELEVE_NOTES', $cycle === SessionReleve::CYCLE_MASTER ? 'Master' : 'Licence');

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
                                'niveau_id' => $estDoctorat ? null : $niveauPourNumeroSemestre($case->numero)?->id,
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
        //
        // `niveau` est réparti tour à tour sur les 5 valeurs de
        // PvSoutenance::NIVEAUX (plutôt que figé sur Master comme avant) —
        // demande explicite du client ("readapte a present les demos pour
        // qu'on voit a quoi sa resemble") : sans cette variété, les onglets
        // BTS/DTS/Licence/Doctorat de la liste (voir
        // resources/views/gestion/pv-soutenances/index.blade.php et
        // App\Models\PvSoutenance::niveauBadgeClasse()) resteraient tous
        // vides en démonstration, et les couleurs par niveau ne se
        // verraient jamais nulle part.
        // --------------------------------------------------------------
        if ($typePvSoutenance) {
            $niveauxPvSoutenance = array_keys(PvSoutenance::NIVEAUX);
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
                    'niveau' => $niveauxPvSoutenance[$index % count($niveauxPvSoutenance)],
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
        // 3quater) PV de réunion : un enregistrement par réunion — voir
        // App\Models\Reunion (remplace, comme les trois volets précédents,
        // l'ancien Document "à plat" pour ce type). Chacune a son rapport
        // déposé (case fixe, toujours remplie — un rapport est rédigé à
        // chaque réunion) et environ une réunion sur deux a en plus une
        // pièce jointe (liste en nombre libre, voir le docblock du
        // contrôleur).
        //
        // `annee_academique_id` est réparti tour à tour sur les 4 années les
        // plus récentes (plutôt que figé sur $anneeRecente) — demande
        // explicite du client ("fait de même pour les PV de réunion pour les
        // couleurs et pour le tableau fait par année académique") : sans
        // cette variété, les onglets par année de la liste (voir
        // resources/views/gestion/reunions/index.blade.php et
        // App\Models\AnneeAcademique::paletteIndex()) resteraient tous vides
        // sauf un en démonstration. Limité aux 4 plus récentes (pas les 7
        // de AnneeAcademiqueSeeder) : des réunions d'archives remontant à
        // 2020 ne seraient pas réalistes pour ce type de document.
        // --------------------------------------------------------------
        if ($typeReunion) {
            $sousTypesReunion = $sousTypesParType->get('PV_REUNION', collect());
            $anneesReunion = $anneesAcademiques->take(4)->values();
            $objetsReunion = [
                'Point hebdomadaire sur l\'avancement des inscriptions',
                'Préparation de la rentrée académique',
                'Suivi budgétaire du trimestre',
                'Organisation des soutenances de fin d\'année',
                'Révision du calendrier pédagogique',
                'Bilan des admissions BTS',
            ];

            foreach ($objetsReunion as $index => $objet) {
                $sousType = $sousTypesReunion->isNotEmpty()
                    ? $sousTypesReunion[$index % $sousTypesReunion->count()]
                    : null;
                $anneeReunion = $anneesReunion->isNotEmpty()
                    ? $anneesReunion[$index % $anneesReunion->count()]
                    : $anneeRecente;

                $reunion = Reunion::create([
                    'annee_academique_id' => $anneeReunion->id,
                    'date_reunion' => now()->subWeeks($index + 1)->toDateString(),
                    'sous_type_document_id' => $sousType?->id,
                    'description' => $objet,
                    'created_by' => $admin->id,
                ]);
                $reunionsCreees[] = $reunion;

                $caseRapport = ReunionFichier::create([
                    'reunion_id' => $reunion->id,
                    'role' => ReunionFichier::ROLE_RAPPORT,
                    'numero' => null,
                    'libelle' => null,
                    'document_id' => null,
                ]);

                $documentRapport = $this->creerDocument(
                    $admin,
                    ($sousType?->libelle ?? 'Réunion').' du '.$reunion->date_reunion->format('d/m/Y').' - Rapport',
                    [
                        'type_document_id' => $typeReunion->id,
                        'sous_type_document_id' => $sousType?->id,
                        'annee_academique_id' => $anneeReunion->id,
                    ],
                );
                $caseRapport->update(['document_id' => $documentRapport->id]);
                $documentsCrees[] = $documentRapport;

                // Environ une réunion sur deux a une pièce jointe (liste de
                // présence) en plus du rapport.
                if ($index % 2 === 0) {
                    $documentPieceJointe = $this->creerDocument(
                        $admin,
                        ($sousType?->libelle ?? 'Réunion').' du '.$reunion->date_reunion->format('d/m/Y').' - Liste de présence',
                        [
                            'type_document_id' => $typeReunion->id,
                            'sous_type_document_id' => $sousType?->id,
                            'annee_academique_id' => $anneeReunion->id,
                        ],
                    );
                    ReunionFichier::create([
                        'reunion_id' => $reunion->id,
                        'role' => ReunionFichier::ROLE_PIECE_JOINTE,
                        'numero' => 1,
                        'libelle' => 'Liste de présence',
                        'document_id' => $documentPieceJointe->id,
                    ]);
                    $documentsCrees[] = $documentPieceJointe;
                }
            }
        }

        // --------------------------------------------------------------
        // 3quinquies) Dossier étudiant : un enregistrement par étudiant —
        // voir App\Models\Etudiant (remplace, comme les quatre volets
        // précédents, l'ancien Document "à plat" pour ce type). Depuis la
        // refonte "liste fixe" (voir App\Models\TypePieceEtudiant), CHAQUE
        // étudiant reçoit ici les mêmes cases que créerait
        // EtudiantController::creerCasesIdentite()/creerCasesInscription() —
        // seules certaines sont effectivement remplies, pour illustrer à la
        // fois des cases pleines et des cases encore vides dans l'écran.
        // Deux des cinq étudiants ci-dessous ont DEUX inscriptions (année
        // précédente + année récente), pour illustrer concrètement la
        // progression d'un niveau à l'autre d'une année sur l'autre (demande
        // explicite du client) ; les trois autres n'ont que l'inscription de
        // l'année récente.
        // --------------------------------------------------------------
        if ($typeEtudiant) {
            $anneePrecedente = $anneesAcademiques->get(1) ?? $anneeRecente;
            $niveauParCode = fn (string $code) => $niveaux->firstWhere('code', $code);

            $typesPieceParCode = TypePieceEtudiant::query()->where('actif', true)->get()->keyBy('code');
            $typesPiecesIdentite = $typesPieceParCode->where('recurrente', false)->sortBy('ordre');
            $typesPiecesInscription = $typesPieceParCode->where('recurrente', true)->sortBy('ordre');

            $profilsEtudiants = [
                ['matricule' => 'ING-2024-001', 'nom' => 'Ouedraogo', 'prenoms' => 'Aïcha', 'niveauPrecedent' => 'L1', 'niveauRecent' => 'L2'],
                ['matricule' => 'ING-2025-014', 'nom' => 'Sawadogo', 'prenoms' => 'Ibrahim', 'niveauPrecedent' => null, 'niveauRecent' => 'L1'],
                ['matricule' => 'ING-2023-032', 'nom' => 'Kaboré', 'prenoms' => 'Fatimata', 'niveauPrecedent' => 'L3', 'niveauRecent' => 'M1'],
                ['matricule' => 'ING-2022-007', 'nom' => 'Traoré', 'prenoms' => 'Boukari', 'niveauPrecedent' => null, 'niveauRecent' => 'M2'],
                ['matricule' => 'ING-2025-021', 'nom' => 'Zongo', 'prenoms' => 'Awa', 'niveauPrecedent' => null, 'niveauRecent' => 'L1'],
            ];

            foreach ($profilsEtudiants as $index => $profil) {
                $filiere = $filieres[$index % $filieres->count()];

                $etudiant = Etudiant::create([
                    'matricule' => $profil['matricule'],
                    'nom' => $profil['nom'],
                    'prenoms' => $profil['prenoms'],
                    'created_by' => $admin->id,
                ]);
                $etudiantsCrees[] = $etudiant;

                $inscriptionPrecedente = null;
                if ($profil['niveauPrecedent'] && ($niveauPrecedent = $niveauParCode($profil['niveauPrecedent'])) && $anneePrecedente->id !== $anneeRecente->id) {
                    $inscriptionPrecedente = $etudiant->inscriptions()->create([
                        'annee_academique_id' => $anneePrecedente->id,
                        'niveau_id' => $niveauPrecedent->id,
                        'filiere_id' => $filiere->id,
                    ]);
                }

                $niveauRecent = $niveauParCode($profil['niveauRecent']);
                $inscriptionRecente = null;
                if ($niveauRecent) {
                    $inscriptionRecente = $etudiant->inscriptions()->create([
                        'annee_academique_id' => $anneeRecente->id,
                        'niveau_id' => $niveauRecent->id,
                        'filiere_id' => $filiere->id,
                    ]);
                }

                // Cases d'identité (une par type non récurrent) — l'acte de
                // naissance est toujours rempli, la CNIB seulement pour un
                // étudiant sur deux, le reste (diplôme antérieur, photo)
                // volontairement laissé vide pour montrer une case non
                // remplie dans l'écran.
                $numero = 0;
                foreach ($typesPiecesIdentite as $type) {
                    $document = null;

                    if ($type->code === 'ACTE_NAISSANCE' || ($type->code === 'CNIB' && $index % 2 === 0)) {
                        $document = $this->creerDocument(
                            $admin,
                            $profil['prenoms'].' '.$profil['nom'].' - '.$type->libelle,
                            [
                                'type_document_id' => $typeEtudiant->id,
                                'filiere_id' => $filiere->id,
                                'niveau_id' => $niveauRecent?->id,
                                'annee_academique_id' => $anneeRecente->id,
                            ],
                        );
                        $documentsCrees[] = $document;
                    }

                    EtudiantFichier::create([
                        'etudiant_id' => $etudiant->id,
                        'numero' => ++$numero,
                        'type_piece_etudiant_id' => $type->id,
                        'document_id' => $document?->id,
                    ]);
                }

                // Cases par année académique (une par type récurrent, par
                // inscription) — seul le relevé de notes de l'inscription la
                // plus récente est rempli, le reste reste vide.
                foreach ([$inscriptionPrecedente, $inscriptionRecente] as $inscription) {
                    if (! $inscription) {
                        continue;
                    }

                    foreach ($typesPiecesInscription as $type) {
                        $document = null;

                        if ($type->code === 'RELEVES_NOTES' && $inscription->is($inscriptionRecente)) {
                            $document = $this->creerDocument(
                                $admin,
                                $profil['prenoms'].' '.$profil['nom'].' - '.$type->libelle.' ('.$inscription->anneeAcademique?->libelle.')',
                                [
                                    'type_document_id' => $typeEtudiant->id,
                                    'filiere_id' => $inscription->filiere_id,
                                    'niveau_id' => $inscription->niveau_id,
                                    'annee_academique_id' => $inscription->annee_academique_id,
                                ],
                            );
                            $documentsCrees[] = $document;
                        }

                        EtudiantFichier::create([
                            'etudiant_id' => $etudiant->id,
                            'numero' => ++$numero,
                            'type_piece_etudiant_id' => $type->id,
                            'etudiant_inscription_id' => $inscription->id,
                            'document_id' => $document?->id,
                        ]);
                    }
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
        // Jamais un document d'écran dédié dans ce tirage (voir
        // App\Support\TypesEcranDedie) : un tel document reste référencé par
        // la case (App\Models\EtudiantFichier/PvSoutenanceFichier/...) de son
        // étudiant/PV/session, qui elle n'est JAMAIS vidée ici — le corbeiller
        // directement créerait donc une case "orpheline" (document_id
        // renseigné mais pointant vers un document invisible, puisque
        // corbeillé), exactement ce que TypesEcranDedie empêche déjà ailleurs
        // dans l'application (voir DocumentController::abortSiEcranDedie()).
        // Ces cinq types ont de toute façon déjà leur propre mise en
        // corbeille de démonstration juste après (on corbeille l'étudiant/le
        // PV/la session lui-même, pas un de ses documents isolément).
        $idsEcranDedie = TypesEcranDedie::ids();

        // ->random(0) sur une collection vide renvoie simplement une
        // collection vide (pas d'exception) : sûr même si $documentsCrees
        // est vide (référentiels/types tous manquants, cas déjà couvert par
        // les vérifications ci-dessus, mais on ne suppose rien).
        $documentsCorbeillables = collect($documentsCrees)
            ->reject(fn (Document $document) => in_array($document->type_document_id, $idsEcranDedie, true));

        $documentsCorbeillables
            ->random(min(3, $documentsCorbeillables->count()))
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

        collect($reunionsCreees)
            ->random(min(1, count($reunionsCreees)))
            ->each(fn (Reunion $reunion) => $reunion->delete());

        collect($etudiantsCrees)
            ->random(min(1, count($etudiantsCrees)))
            ->each(fn (Etudiant $etudiant) => $etudiant->delete());

        $this->command?->info(count($documentsCrees).' document(s) de démonstration créé(s) (dont '.count($pvSoutenancesCreees).' PV de soutenance, '.count($sessionsDeliberationCreees).' session(s) de délibération, '.count($sessionsReleveCreees).' session(s) de relevé, '.count($reunionsCreees).' PV de réunion et '.count($etudiantsCrees).' dossier(s) étudiant).');
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
