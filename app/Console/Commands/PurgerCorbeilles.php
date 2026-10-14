<?php

namespace App\Console\Commands;

use App\Http\Controllers\ChampSupplementaireController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EtatFinancierController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\ListeFinanceEtudiantController;
use App\Http\Controllers\PvSoutenanceController;
use App\Http\Controllers\RecetteDossierController;
use App\Http\Controllers\RecetteScolariteController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\ReunionController;
use App\Http\Controllers\SessionDeliberationController;
use App\Http\Controllers\SessionReleveController;
use App\Http\Controllers\TypeContratController;
use App\Http\Controllers\TypeDepenseController;
use App\Http\Controllers\TypeDocumentController;
use App\Http\Controllers\TypePieceEtudiantController;
use App\Models\Parametre;
use App\Models\User;
use App\Notifications\PurgeCorbeillesNotification;
use Illuminate\Console\Command;

/**
 * Purge automatique de la corbeille — demande explicite du client, réglable
 * (nombre de jours, désactivée par défaut) depuis Administration >
 * Paramètres > Corbeilles (voir App\Models\Parametre::$purge_corbeille_jours
 * et sa migration). Planifiée quotidiennement (voir routes/console.php).
 *
 * VOLONTAIREMENT une commande dédiée plutôt que le trait natif Prunable de
 * Laravel (déjà utilisé par App\Models\AuditLog, voir sa méthode
 * prunable()) : Prunable se contente d'un forceDelete() automatique par
 * modèle, ce qui laisserait orphelins les documents attachés (relation
 * `fichiers`) de la plupart des ressources de ce tableau — exactement le
 * même problème de "cascade incohérente" déjà signalé par le client pour la
 * mise à la corbeille, qu'il ne faut pas réintroduire ici. Chaque ressource
 * ci-dessous délègue donc à sa propre méthode purgerAnciennes($jours),
 * ajoutée À CÔTÉ de la méthode viderCorbeille() déjà existante dans son
 * contrôleur (voir leurs docblocks) : MÊME logique de cascade/garde-fou,
 * simplement filtrée par ancienneté (`deleted_at < now()->subDays($jours)`)
 * au lieu de "tout ce qui est à la corbeille" — pour que "Vider la
 * corbeille" (manuel) et la purge automatique restent strictement
 * cohérents entre eux, un seul endroit à modifier si cette logique change
 * un jour pour une ressource donnée.
 *
 * Couvre l'intégralité des écrans à corbeille de la plateforme : Contrats et
 * Dossiers (Recettes) sont purgés TOUTES CATÉGORIES confondues, pas scopés
 * comme le sont leurs boutons "Vider la corbeille" respectifs (voir le
 * docblock de ContratController::purgerAnciennes() et
 * RecetteDossierController::purgerAnciennes()).
 *
 * Aucun utilisateur authentifié dans ce contexte (tâche planifiée) : les
 * méthodes purgerAnciennes() de chaque contrôleur n'exécutent donc jamais de
 * vérification ResourceAccess ni de redirection, contrairement à
 * viderCorbeille() — cette commande elle-même est le seul point de contrôle
 * (réservée à l'admin via le réglage qui l'active).
 */
class PurgerCorbeilles extends Command
{
    protected $signature = 'ingridd:purger-corbeilles';

    protected $description = 'Supprime définitivement les éléments de la corbeille plus anciens que le réglage "Purge automatique" (Administration > Paramètres > Corbeilles)';

    public function handle(): int
    {
        $jours = Parametre::actuel()->purge_corbeille_jours;

        // Désactivée (réglage vide) : voir le docblock de la migration qui
        // ajoute cette colonne — c'est le réglage par défaut, aucune purge
        // ne doit jamais se déclencher tant que l'administrateur ne l'a pas
        // explicitement activée. Le planificateur (routes/console.php)
        // évite déjà ce cas via ->when(), mais cette commande reste prudente
        // même lancée manuellement (`php artisan ingridd:purger-corbeilles`).
        if ($jours === null) {
            $this->info('Purge automatique désactivée : aucun nombre de jours n\'est renseigné dans Administration > Paramètres > Corbeilles.');

            return self::SUCCESS;
        }

        try {
            return $this->purger($jours);
        } catch (\Throwable $exception) {
            // Même filet de sécurité que App\Console\Commands\CreerSauvegarde :
            // une exception échappée d'une tâche planifiée se termine
            // silencieusement dans les journaux du planificateur, invisible
            // pour l'administrateur — on journalise explicitement et on
            // retourne un échec propre.
            report($exception);
            $this->error('Erreur inattendue pendant la purge de la corbeille : '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function purger(int $jours): int
    {
        /** @var array<string, callable(): array{supprimes: int, ignores: int}> $appels */
        $appels = [
            'Documents' => fn () => app(DocumentController::class)->purgerAnciennes($jours),
            'Réunions' => fn () => app(ReunionController::class)->purgerAnciennes($jours),
            'PV de soutenance' => fn () => app(PvSoutenanceController::class)->purgerAnciennes($jours),
            'Dossiers étudiants' => fn () => app(EtudiantController::class)->purgerAnciennes($jours),
            'États financiers' => fn () => app(EtatFinancierController::class)->purgerAnciennes($jours),
            'Recettes — Scolarité' => fn () => app(RecetteScolariteController::class)->purgerAnciennes($jours),
            'Recettes — Dossiers' => fn () => app(RecetteDossierController::class)->purgerAnciennes($jours),
            'Listes de finances étudiants' => fn () => app(ListeFinanceEtudiantController::class)->purgerAnciennes($jours),
            'Sessions de délibération' => fn () => app(SessionDeliberationController::class)->purgerAnciennes($jours),
            'Sessions de relevés' => fn () => app(SessionReleveController::class)->purgerAnciennes($jours),
            'Contrats' => fn () => app(ContratController::class)->purgerAnciennes($jours),
            'Types de pièce étudiant' => fn () => app(TypePieceEtudiantController::class)->purgerAnciennes($jours),
            'Catégories de dépense' => fn () => app(TypeDepenseController::class)->purgerAnciennes($jours),
            'Catégories de contrat' => fn () => app(TypeContratController::class)->purgerAnciennes($jours),
            'Types de document' => fn () => app(TypeDocumentController::class)->purgerAnciennes($jours),
            'Champs supplémentaires' => fn () => app(ChampSupplementaireController::class)->purgerAnciennes($jours),
            'Filières' => fn () => app(ReferentielController::class)->purgerAnciennes('filieres', $jours),
            'Niveaux' => fn () => app(ReferentielController::class)->purgerAnciennes('niveaux', $jours),
            'Années académiques' => fn () => app(ReferentielController::class)->purgerAnciennes('annees-academiques', $jours),
        ];

        $supprimesTotal = 0;
        $ignoresTotal = 0;

        foreach ($appels as $libelle => $appel) {
            $resultat = $appel();

            if ($resultat['supprimes'] > 0 || $resultat['ignores'] > 0) {
                $this->info("{$libelle} : {$resultat['supprimes']} supprimé(s), {$resultat['ignores']} ignoré(s).");
            }

            $supprimesTotal += $resultat['supprimes'];
            $ignoresTotal += $resultat['ignores'];
        }

        if ($supprimesTotal === 0) {
            $this->info("Rien à purger (aucun élément de la corbeille n'a plus de {$jours} jour(s)).");

            return self::SUCCESS;
        }

        $this->info("Purge terminée : {$supprimesTotal} élément(s) supprimé(s) définitivement, {$ignoresTotal} ignoré(s).");

        $this->notifierAdmins($supprimesTotal, $ignoresTotal, $jours);

        return self::SUCCESS;
    }

    /**
     * Prévient chaque administrateur — SANS garde-fou de déduplication
     * (voir le docblock de App\Notifications\PurgeCorbeillesNotification) :
     * contrairement à une alerte persistante, chaque purge est un événement
     * distinct qui mérite sa propre notification.
     */
    protected function notifierAdmins(int $supprimesTotal, int $ignoresTotal, int $jours): void
    {
        $description = "{$supprimesTotal} élément(s) de la corbeille, âgé(s) de plus de {$jours} jour(s), ont été supprimés définitivement.";

        if ($ignoresTotal > 0) {
            $description .= " {$ignoresTotal} élément(s) ignoré(s) car encore utilisé(s) ailleurs dans l'application.";
        }

        User::query()->where('role', User::ROLE_ADMIN)->get()->each(
            fn (User $admin) => $admin->notify(new PurgeCorbeillesNotification($description))
        );
    }
}
