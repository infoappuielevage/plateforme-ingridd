<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
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
        $typeListeEtudiants = TypeDocument::query()->where('code', 'LISTE_ETUDIANTS')->where('actif', true)->first();
        $typePvDeliberation = TypeDocument::query()->where('code', 'PV_DELIBERATION')->where('actif', true)->first();
        $typeReleveNotes = TypeDocument::query()->where('code', 'RELEVE_NOTES')->where('actif', true)->first();
        $typeAttestation = TypeDocument::query()->where('code', 'ATTESTATION')->where('actif', true)->first();
        $typePvSoutenance = TypeDocument::query()->where('code', 'PV_SOUTENANCE')->where('actif', true)->first();
        // Liste mise à jour après la refonte complète du référentiel des
        // types (voir TypeDocumentSeeder) : couvre tous les types
        // administratifs génériques restants (sans champs dédiés, sans
        // filière/niveau), pour que chaque page dédiée du menu "Secrétariat"
        // ait au moins quelques documents à montrer.
        $typesGeneriques = TypeDocument::query()->whereIn('code', [
            'CONTRAT', 'COURRIER', 'CAHIER_PEDAGOGIQUE', 'CAHIER_CHARGE_MINISTERE',
            'DOSSIER_EXTENSION_FILIERE', 'CONSEIL_ETABLISSEMENT', 'CONSEIL_SCIENTIFIQUE',
            'CANDIDAT_BTS', 'NOTICE_RENSEIGNEMENT', 'ORDRE_MISSION', 'NOTE_INFORMATION',
            'ORGANIGRAMME', 'SPOT_TELE_RADIO', 'SYLLABUS_FILIERES', 'UNUPEL',
            'LISTE_PERSONNEL', 'LISTE_DIPLOME', 'PV_REUNION', 'CANEVAS_RAPPORT_RENTREE', 'AUTRE',
        ])->where('actif', true)->get();

        // Sous-types actifs, regroupés par code du type parent (voir
        // App\Models\SousTypeDocument) — calculé une seule fois, réutilisé
        // par les sections 1, 3 et 3ter ci-dessous pour illustrer le champ
        // "Sous-type" sur quelques documents de démonstration.
        $sousTypesParType = SousTypeDocument::query()
            ->where('actif', true)
            ->with('typeDocument:id,code')
            ->orderBy('ordre')
            ->get()
            ->groupBy(fn (SousTypeDocument $sousType) => $sousType->typeDocument?->code);

        $sousTypeParLibelle = fn (string $codeType, string $libelle) => $sousTypesParType
            ->get($codeType, collect())
            ->firstWhere('libelle', $libelle)?->id;

        $documentsCrees = [];

        // --------------------------------------------------------------
        // 1) Un document par filière/niveau (année académique la plus
        // récente) : liste des étudiants et PV de délibération.
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

                if ($typePvDeliberation) {
                    // Sous-type "Licence"/"Master" selon le niveau concerné
                    // (voir SousTypeDocumentSeeder) — dégradé silencieusement
                    // à null si le référentiel des sous-types n'a pas encore
                    // été seedé, ou si le niveau n'est ni L* ni M*.
                    $sousTypeDeliberation = str_starts_with($niveau->code, 'M')
                        ? $sousTypeParLibelle('PV_DELIBERATION', 'Master')
                        : $sousTypeParLibelle('PV_DELIBERATION', 'Licence');

                    $documentsCrees[] = $this->creerDocument(
                        $admin,
                        "Délibération - {$filiere->code} {$niveau->code} ({$anneeRecente->libelle})",
                        [
                            'type_document_id' => $typePvDeliberation->id,
                            'sous_type_document_id' => $sousTypeDeliberation,
                            'filiere_id' => $filiere->id,
                            'niveau_id' => $niveau->id,
                            'annee_academique_id' => $anneeRecente->id,
                        ],
                    );
                }
            }
        }

        // --------------------------------------------------------------
        // 2) Relevés de notes : sur deux filières seulement (pas toutes,
        // pour ne pas générer un nombre excessif de documents), un par
        // semestre réellement possible pour chaque niveau — voir
        // App\Support\Semestres.
        // --------------------------------------------------------------
        if ($typeReleveNotes) {
            foreach ($filieres->take(2) as $filiere) {
                foreach ($niveaux as $niveau) {
                    foreach (Semestres::pourCodeNiveau($niveau->code) as $semestre) {
                        $documentsCrees[] = $this->creerDocument(
                            $admin,
                            "Relevé de notes - {$filiere->code} {$niveau->code} Semestre {$semestre} ({$anneeRecente->libelle})",
                            [
                                'type_document_id' => $typeReleveNotes->id,
                                'filiere_id' => $filiere->id,
                                'niveau_id' => $niveau->id,
                                'annee_academique_id' => $anneeRecente->id,
                                'semestre' => $semestre,
                            ],
                        );
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
        // 3bis) PV de soutenance : numérisation du registre papier, avec
        // thème du mémoire, date de soutenance et membres du jury — pour
        // que ce type ait, lui aussi, des exemples dans les listes/
        // statistiques dès la première installation.
        // --------------------------------------------------------------
        if ($typePvSoutenance) {
            $themesMemoire = [
                'Digitalisation des processus administratifs dans les PME burkinabè',
                'Impact du changement climatique sur l\'agriculture en zone sahélienne',
                'Gestion des ressources humaines dans le secteur informel',
                'Contribution des technologies mobiles à l\'inclusion financière',
            ];
            $juryExemples = [
                "Pr. OUEDRAOGO Jean (Président)\nDr. KABORE Aïcha (Rapporteur)\nM. SAWADOGO Paul (Membre)",
                "Dr. TRAORE Fatou (Présidente)\nPr. ZONGO Marc (Rapporteur)\nMme COMPAORE Rita (Membre)",
            ];
            $niveauSoutenance = $niveaux->firstWhere('code', 'M2') ?? $niveaux->last();

            foreach ($etudiants as $index => $nomTitulaire) {
                $documentsCrees[] = $this->creerDocument(
                    $admin,
                    "PV de soutenance - {$nomTitulaire}",
                    [
                        'type_document_id' => $typePvSoutenance->id,
                        'filiere_id' => $filieres->random()->id,
                        'niveau_id' => $niveauSoutenance->id,
                        'annee_academique_id' => $anneeRecente->id,
                        'nom_titulaire' => $nomTitulaire,
                        'theme_memoire' => $themesMemoire[$index % count($themesMemoire)],
                        'date_soutenance' => now()->subMonths(random_int(1, 8))->toDateString(),
                        'membres_jury' => $juryExemples[$index % count($juryExemples)],
                        'mention' => $mentions[array_rand($mentions)],
                    ],
                );
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
            $type = TypeDocument::query()->where('code', $exemple['code'])->where('actif', true)->first();
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
        // 6) Quelques documents dans la corbeille, pour que l'écran
        // "Corbeille" (menu dédié) ait du contenu dès le départ.
        // --------------------------------------------------------------
        // ->random(0) sur une collection vide renvoie simplement une
        // collection vide (pas d'exception) : sûr même si $documentsCrees
        // est vide (référentiels/types tous manquants, cas déjà couvert par
        // les vérifications ci-dessus, mais on ne suppose rien).
        collect($documentsCrees)
            ->random(min(3, count($documentsCrees)))
            ->each(fn (Document $document) => $document->delete());

        $this->command?->info(count($documentsCrees).' document(s) de démonstration créé(s).');
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
