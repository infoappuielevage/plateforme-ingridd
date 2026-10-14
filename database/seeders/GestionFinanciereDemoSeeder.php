<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Contrat;
use App\Models\ContratFichier;
use App\Models\Depense;
use App\Models\Document;
use App\Models\EtatFinancier;
use App\Models\ListeFinanceEtudiant;
use App\Models\ListeFinanceEtudiantFichier;
use App\Models\RecetteDossier;
use App\Models\RecetteDossierFichier;
use App\Models\RecetteScolarite;
use App\Models\RecetteScolariteFichier;
use App\Models\TypeContrat;
use App\Models\TypeDepense;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\FichiersDemo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données de démonstration pour les cinq écrans "Gestion financière" les
 * plus récents — Contrats, États financiers, Recettes (Scolarité et
 * Dossier), Listes des étudiants (finances) — qu'App\Database\Seeders\
 * DocumentSeeder ne couvre PAS : ces modules sont arrivés après lui (voir
 * le docblock de App\Support\TypesEcranDedie pour l'historique), et leurs
 * propres écrans restaient donc vides après un `migrate:fresh --seed` suivi
 * de `db:seed --class=DocumentSeeder` — demande explicite du client ("met a
 * jour les demos et les seeders pour qu'on puisse voir ce que sa donne").
 *
 * Dépenses (App\Models\Depense) n'a VOLONTAIREMENT aucune ligne créée ici :
 * ses lignes existent déjà automatiquement (une par catégorie active ×
 * année académique, voir App\Models\TypeDepense::synchroniserPourAnnee()/
 * synchroniserCasesRetroactives(), déclenchées dès TypeDepenseSeeder/
 * AnneeAcademiqueSeeder) — ce seeder se contente d'y déposer quelques
 * fichiers de démonstration (voir section E). Aucune mise à la corbeille
 * n'est générée pour les Dépenses : ce module n'a pas d'écran de corbeille
 * (voir App\Support\ResourceAccess::ACTIONS, commentaire sur
 * "delete_definitif") — y mettre un enregistrement de démonstration
 * laisserait un compteur sur Administration > Corbeilles sans aucun moyen
 * de l'ouvrir pour de vrai.
 *
 * VOLONTAIREMENT PAS appelé depuis DatabaseSeeder, comme DocumentSeeder (voir
 * son docblock) : à lancer à la demande, APRÈS les seeders de référentiels
 * (déjà joués par défaut) :
 *
 *     php artisan db:seed --class=GestionFinanciereDemoSeeder
 *
 * Chaque document créé ici a un vrai (petit) fichier PDF valide écrit sur le
 * disque `documents` (voir App\Support\FichiersDemo, partagée avec
 * DocumentSeeder::creerDocument()), pour que "Télécharger" ET "Voir" (aperçu
 * navigateur) fonctionnent réellement — pas un simple texte de remplacement
 * sous une extension .pdf.
 */
class GestionFinanciereDemoSeeder extends Seeder
{
    protected const MARQUEUR_DEMO = '(démonstration — généré par GestionFinanciereDemoSeeder, à supprimer avant la mise en production réelle)';

    public function run(): void
    {
        // Garde-fou UNIQUE pour tout ce seeder (comme DocumentSeeder) : un
        // seul marqueur suffit, tous les enregistrements créés ici en
        // portent un (observations/observation/libellé selon le modèle).
        if (Contrat::query()->withTrashed()->where('observations', 'like', '%'.self::MARQUEUR_DEMO.'%')->exists()) {
            $this->command?->warn('GestionFinanciereDemoSeeder a déjà été exécuté : rien à refaire.');

            return;
        }

        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        $anneesAcademiques = AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get();

        if (! $admin || $anneesAcademiques->isEmpty()) {
            $this->command?->warn('GestionFinanciereDemoSeeder : référentiels manquants (jouer RoleSeeder/AnneeAcademiqueSeeder d\'abord) — rien de généré.');

            return;
        }

        $nombreContrats = $this->seederContrats($admin);
        $nombreEtatsFinanciers = $this->seederEtatsFinanciers($admin);
        $nombreRecettesScolarite = $this->seederRecettesScolarite($admin, $anneesAcademiques);
        $nombreRecettesDossier = $this->seederRecettesDossier($admin, $anneesAcademiques);
        $nombreListesFinances = $this->seederListesFinancesEtudiants($admin, $anneesAcademiques);
        $nombreDepensesEnrichies = $this->enrichirDepenses($admin, $anneesAcademiques);

        $this->command?->info("{$nombreContrats} contrat(s), {$nombreEtatsFinanciers} état(s) financier(s), {$nombreRecettesScolarite} dossier(s) Recette Scolarité, {$nombreRecettesDossier} dossier(s) Recette (diplôme/soutenance), {$nombreListesFinances} liste(s) de finances étudiants créé(s), et {$nombreDepensesEnrichies} dépense(s) existante(s) complétée(s) d'un bordereau/excel de démonstration.");
    }

    // --------------------------------------------------------------
    // A) Contrats — deux par catégorie active, statuts variés, deux mis à
    // la corbeille pour illustrer l'écran Corbeille (demande explicite du
    // client, discussion sur la corbeille des contrats/documents).
    // --------------------------------------------------------------
    protected function seederContrats(User $admin): int
    {
        $typeDocumentContrat = TypeDocument::query()->where('code', 'CONTRAT')->first();
        $categories = TypeContrat::query()->where('actif', true)->orderBy('ordre')->get();

        if (! $typeDocumentContrat || $categories->isEmpty()) {
            return 0;
        }

        // Deux partenaires/prestataires plausibles par catégorie — juste de
        // quoi illustrer chaque catégorie, pas une liste exhaustive.
        $partenairesParCode = [
            'PARTENARIAT' => ['Université de Ouagadougou', 'ONG Plan International Burkina Faso'],
            'PRESTATION_SERVICE' => ['SOBUAD Nettoyage & Services', 'Cabinet ComptaPlus SARL'],
            'BAIL_LOCATION' => ['SCI Immobilière du Kadiogo', 'M. OUEDRAOGO Boureima (bailleur)'],
            'CONVENTION_STAGE_FORMATION' => ['Lycée Technique de Ouagadougou', 'Centre de Formation Professionnelle de Kossodo'],
            'CONTRAT_ENSEIGNANT' => ['Dr. SAWADOGO Paul', 'Mme KABORE Aïcha'],
            'CONTRAT_PERSONNEL' => ['M. TRAORE Issa (agent d\'entretien)', 'Mme ZONGO Rita (secrétaire)'],
        ];
        $statuts = array_keys(Contrat::STATUTS);

        $contratsCrees = [];
        $compteur = 0;

        foreach ($categories as $categorie) {
            $partenaires = $partenairesParCode[$categorie->code] ?? ["Partenaire {$categorie->libelle} A", "Partenaire {$categorie->libelle} B"];

            foreach ($partenaires as $index => $partenaire) {
                $statut = $statuts[$compteur % count($statuts)];
                $aUnMontant = $categorie->code !== 'PARTENARIAT'; // un partenariat n'a pas forcément de valeur monétaire (voir form.blade.php)

                // Dates cohérentes avec le statut (demande implicite — des
                // données de démo crédibles) : un contrat "Expiré"/"Résilié"
                // a une échéance déjà passée, "Actif" une échéance future
                // (ou indéterminée), "En négociation" aucune date encore
                // arrêtée.
                $dateFin = match ($statut) {
                    Contrat::STATUT_EN_NEGOCIATION => null,
                    Contrat::STATUT_ACTIF => now()->addMonths(random_int(1, 18))->toDateString(),
                    default => now()->subMonths(random_int(1, 6))->toDateString(),
                };

                $contrat = Contrat::create([
                    'type_contrat_id' => $categorie->id,
                    'partenaire_prestataire' => $partenaire,
                    'objet' => "Objet du contrat — {$categorie->libelle} #".($index + 1).' '.self::MARQUEUR_DEMO,
                    'montant' => $aUnMontant ? random_int(50, 500) * 10000 : null,
                    'date_signature' => $statut === Contrat::STATUT_EN_NEGOCIATION ? null : now()->subMonths(random_int(12, 24))->toDateString(),
                    'date_debut' => $statut === Contrat::STATUT_EN_NEGOCIATION ? null : now()->subMonths(random_int(6, 12))->toDateString(),
                    'date_fin' => $dateFin,
                    'statut' => $statut,
                    'responsable_id' => $admin->id,
                    'contact_nom' => $index === 0 ? 'Contact '.$partenaire : null,
                    'contact_email' => $index === 0 ? Str::slug($partenaire, '.').'@exemple.test' : null,
                    'contact_telephone' => $index === 0 ? '+226 70 00 00 0'.$compteur : null,
                    'observations' => self::MARQUEUR_DEMO,
                    'created_by' => $admin->id,
                ]);
                $contratsCrees[] = $contrat;
                $compteur++;

                // Case fixe "Contrat signé" — toujours déposée.
                $documentSigne = $this->creerDocument($admin, $typeDocumentContrat, "Contrat signé — {$categorie->libelle} — {$partenaire}");
                ContratFichier::create([
                    'contrat_id' => $contrat->id,
                    'role' => ContratFichier::ROLE_CONTRAT_SIGNE,
                    'numero' => null,
                    'libelle' => null,
                    'document_id' => $documentSigne->id,
                ]);

                // Un avenant sur un contrat sur deux, pour illustrer la liste libre.
                if ($index === 0) {
                    $documentAvenant = $this->creerDocument($admin, $typeDocumentContrat, "Avenant n°1 — {$categorie->libelle} — {$partenaire}");
                    ContratFichier::create([
                        'contrat_id' => $contrat->id,
                        'role' => ContratFichier::ROLE_AVENANT,
                        'numero' => 1,
                        'libelle' => 'Avenant n°1 — prolongation',
                        'document_id' => $documentAvenant->id,
                    ]);
                }
            }
        }

        // Deux contrats mis à la corbeille (catégories différentes, pour
        // que "Corbeilles" montre bien plusieurs lignes "Gestion des
        // contrats" avec un compteur chacune).
        collect($contratsCrees)
            ->random(min(2, count($contratsCrees)))
            ->each(fn (Contrat $contrat) => $contrat->delete());

        return count($contratsCrees);
    }

    // --------------------------------------------------------------
    // B) États financiers — un par année civile sur les quatre dernières,
    // document déposé sur trois d'entre eux (le quatrième reste "non
    // rempli", pour montrer les deux états), un mis à la corbeille.
    // --------------------------------------------------------------
    protected function seederEtatsFinanciers(User $admin): int
    {
        $typeDocument = TypeDocument::query()->where('code', 'ETAT_FINANCIER')->first();

        if (! $typeDocument) {
            return 0;
        }

        $anneeCourante = (int) now()->format('Y');
        $etatsCrees = [];

        foreach (range($anneeCourante - 3, $anneeCourante) as $index => $annee) {
            $etat = EtatFinancier::create([
                'annee' => $annee,
                'observation' => "État financier de l'exercice {$annee}. ".self::MARQUEUR_DEMO,
                'created_by' => $admin->id,
            ]);

            // Les trois exercices les plus anciens ont leur état déposé, le
            // plus récent reste volontairement vide (en cours de clôture).
            if ($annee !== $anneeCourante) {
                $document = $this->creerDocument($admin, $typeDocument, "État financier {$annee}");
                $etat->update(['document_id' => $document->id]);
            }

            $etatsCrees[] = $etat;
        }

        collect($etatsCrees)->random(min(1, count($etatsCrees)))->each(fn (EtatFinancier $etat) => $etat->delete());

        return count($etatsCrees);
    }

    // --------------------------------------------------------------
    // C) Recettes — Scolarité : un dossier par année académique (les trois
    // plus récentes — contrainte d'unicité "un dossier par année"), avec
    // les trois cases fixes (Orange/Moov/Telecel Money) partiellement
    // remplies + un bordereau en plus sur le dossier le plus récent, un
    // dossier mis à la corbeille.
    // --------------------------------------------------------------
    protected function seederRecettesScolarite(User $admin, $anneesAcademiques): int
    {
        $typeDocument = TypeDocument::query()->where('code', 'RECETTE')->first();

        if (! $typeDocument) {
            return 0;
        }

        $dossiersCrees = [];

        foreach ($anneesAcademiques->take(3) as $index => $annee) {
            $dossier = RecetteScolarite::create([
                'annee_academique_id' => $annee->id,
                'created_by' => $admin->id,
            ]);
            $dossiersCrees[] = $dossier;

            // Trois cases fixes pré-créées vides, comme RecetteScolariteController::creerCasesFixes().
            $casesParRole = [];
            foreach (RecetteScolariteFichier::ROLES_FIXES as $role) {
                $casesParRole[$role] = RecetteScolariteFichier::create([
                    'recette_scolarite_id' => $dossier->id,
                    'role' => $role,
                    'numero' => null,
                    'libelle' => null,
                    'document_id' => null,
                ]);
            }

            // Orange Money toujours rempli, Moov Money un dossier sur deux,
            // Telecel Money volontairement jamais (case vide à montrer).
            $rolesRemplis = $index % 2 === 0
                ? [RecetteScolariteFichier::ROLE_ORANGE_MONEY, RecetteScolariteFichier::ROLE_MOOV_MONEY]
                : [RecetteScolariteFichier::ROLE_ORANGE_MONEY];

            foreach ($rolesRemplis as $role) {
                $document = $this->creerDocument($admin, $typeDocument, "Recette Scolarité {$annee->libelle} — ".RecetteScolariteFichier::LIBELLES_FIXES[$role]);
                $casesParRole[$role]->update(['document_id' => $document->id]);
            }

            // Un bordereau (liste libre) sur le dossier le plus récent.
            if ($index === 0) {
                $documentBordereau = $this->creerDocument($admin, $typeDocument, "Recette Scolarité {$annee->libelle} — Bordereau 1");
                RecetteScolariteFichier::create([
                    'recette_scolarite_id' => $dossier->id,
                    'role' => RecetteScolariteFichier::ROLE_BORDEREAU,
                    'numero' => 1,
                    'libelle' => null,
                    'document_id' => $documentBordereau->id,
                ]);
            }
        }

        collect($dossiersCrees)->random(min(1, count($dossiersCrees)))->each(fn (RecetteScolarite $dossier) => $dossier->delete());

        return count($dossiersCrees);
    }

    // --------------------------------------------------------------
    // D) Recettes — Dossier : un par type (Retrait diplôme / Frais de
    // soutenance) × les deux années académiques les plus récentes, avec
    // quelques fichiers de liste libre, un dossier mis à la corbeille.
    // --------------------------------------------------------------
    protected function seederRecettesDossier(User $admin, $anneesAcademiques): int
    {
        $typeDocument = TypeDocument::query()->where('code', 'RECETTE')->first();

        if (! $typeDocument) {
            return 0;
        }

        $dossiersCrees = [];

        foreach (RecetteDossier::TYPES as $type => $libelleType) {
            foreach ($anneesAcademiques->take(2) as $annee) {
                $dossier = RecetteDossier::create([
                    'annee_academique_id' => $annee->id,
                    'type' => $type,
                    'created_by' => $admin->id,
                ]);
                $dossiersCrees[] = $dossier;

                foreach (range(1, 2) as $numero) {
                    $libelle = "{$libelleType} {$annee->libelle} — Lot {$numero}";
                    $document = $this->creerDocument($admin, $typeDocument, $libelle);
                    RecetteDossierFichier::create([
                        'recette_dossier_id' => $dossier->id,
                        'numero' => $numero,
                        'libelle' => $libelle,
                        'document_id' => $document->id,
                    ]);
                }
            }
        }

        collect($dossiersCrees)->random(min(1, count($dossiersCrees)))->each(fn (RecetteDossier $dossier) => $dossier->delete());

        return count($dossiersCrees);
    }

    // --------------------------------------------------------------
    // E) Listes des étudiants (finances) — une par année académique (les
    // trois plus récentes), quelques fichiers de liste libre, une liste
    // mise à la corbeille.
    // --------------------------------------------------------------
    protected function seederListesFinancesEtudiants(User $admin, $anneesAcademiques): int
    {
        $typeDocument = TypeDocument::query()->where('code', 'LISTE_ETUDIANTS_FINANCES')->first();

        if (! $typeDocument) {
            return 0;
        }

        $listesCreees = [];

        foreach ($anneesAcademiques->take(3) as $annee) {
            $liste = ListeFinanceEtudiant::create([
                'annee_academique_id' => $annee->id,
                'created_by' => $admin->id,
            ]);
            $listesCreees[] = $liste;

            foreach (range(1, 2) as $numero) {
                $libelle = "Liste des étudiants (finances) {$annee->libelle} — Fichier {$numero}";
                $document = $this->creerDocument($admin, $typeDocument, $libelle);
                ListeFinanceEtudiantFichier::create([
                    'liste_finance_etudiant_id' => $liste->id,
                    'numero' => $numero,
                    'libelle' => $libelle,
                    'document_id' => $document->id,
                ]);
            }
        }

        collect($listesCreees)->random(min(1, count($listesCreees)))->each(fn (ListeFinanceEtudiant $liste) => $liste->delete());

        return count($listesCreees);
    }

    // --------------------------------------------------------------
    // F) Dépenses — AUCUNE nouvelle ligne (voir le docblock de la classe) :
    // juste un bordereau/fichier Excel déposé sur quelques lignes déjà
    // créées automatiquement, pour illustrer les deux états (déposé/vide).
    // --------------------------------------------------------------
    protected function enrichirDepenses(User $admin, $anneesAcademiques): int
    {
        $typeDocument = TypeDocument::query()->where('code', 'DEPENSE')->first();
        $anneeRecente = $anneesAcademiques->first();

        if (! $typeDocument || ! $anneeRecente) {
            return 0;
        }

        $categories = TypeDepense::query()->where('actif', true)->orderBy('ordre')->get();
        $compteur = 0;

        foreach ($categories as $index => $categorie) {
            $depense = Depense::query()
                ->where('annee_academique_id', $anneeRecente->id)
                ->where('type_depense_id', $categorie->id)
                ->first();

            if (! $depense) {
                continue;
            }

            // Bordereau sur toutes, fichier Excel seulement une catégorie
            // sur deux — pour montrer les deux états dans la liste.
            $documentBordereau = $this->creerDocument($admin, $typeDocument, "{$categorie->libelle} {$anneeRecente->libelle} — Bordereau");
            $depense->update(['bordereau_document_id' => $documentBordereau->id]);

            if ($index % 2 === 0) {
                $documentExcel = $this->creerDocument($admin, $typeDocument, "{$categorie->libelle} {$anneeRecente->libelle} — Fichier Excel");
                $depense->update(['excel_document_id' => $documentExcel->id]);
            }

            $compteur++;
        }

        return $compteur;
    }

    /**
     * Crée un document de démo avec un vrai fichier physique sur le disque
     * `documents` — même principe que DocumentSeeder::creerDocument() (voir
     * son docblock), simplifié : pas besoin ici d'antidater `created_at`
     * (ces modules n'ont pas de graphique "par mois" à alimenter). Un VRAI
     * PDF minimal (voir App\Support\FichiersDemo), pas un texte de
     * remplacement sous une extension .pdf — même correction que
     * DocumentSeeder, pour que "Voir" (aperçu navigateur) fonctionne aussi
     * sur ces documents.
     */
    protected function creerDocument(User $admin, TypeDocument $typeDocument, string $libelle): Document
    {
        $nomOriginal = Str::limit(Str::slug($libelle, '-'), 80, '').'.pdf';
        $chemin = 'uploads/'.Str::uuid().'.pdf';

        Storage::disk('documents')->put($chemin, FichiersDemo::pdf($libelle, [self::MARQUEUR_DEMO]));

        return Document::create([
            'description' => $libelle.' — '.self::MARQUEUR_DEMO,
            'type_document_id' => $typeDocument->id,
            'chemin' => $chemin,
            'nom_original' => $nomOriginal,
            'extension' => 'pdf',
            'taille' => Storage::disk('documents')->size($chemin),
            'uploaded_by' => $admin->id,
        ]);
    }
}
