<?php

namespace Database\Seeders;

use App\Models\ChampSupplementaire;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\SessionDeliberation;
use App\Models\SessionDeliberationFichier;
use App\Models\SessionReleve;
use App\Models\SessionReleveFichier;
use Illuminate\Database\Seeder;

/**
 * Champs supplémentaires de démonstration — pour que l'écran Administration
 * > Champs supplémentaires ne soit pas vide à l'installation, et pour
 * illustrer concrètement la case qu'un champ supplémentaire ajoute sur les
 * PV/Relevés/Soutenances déjà existants.
 *
 * VOLONTAIREMENT PAS appelé depuis DatabaseSeeder, comme DocumentSeeder (voir
 * son docblock) : ce sont des exemples à ne pas retrouver automatiquement en
 * production. À lancer à la demande, de préférence APRÈS DocumentSeeder (sinon
 * il n'y a tout simplement aucune session/PV existant à rétro-synchroniser —
 * sans erreur pour autant, juste rien à rattraper) :
 *
 *     php artisan db:seed --class=DocumentSeeder
 *     php artisan db:seed --class=ChampSupplementaireSeeder
 *
 * Identifiables par leur libellé (préfixe "Démo —") pour les retrouver et les
 * supprimer facilement avant une mise en production réelle.
 *
 * updateOrCreate() par libellé (idempotent, comme RoleSeeder) : rejouer ce
 * seeder ne crée jamais de doublon, et resynchronise les sessions/PV créés
 * depuis la dernière exécution.
 *
 * La synchronisation rétroactive reproduit ICI, à l'identique, la logique de
 * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()
 * — on ne peut pas appeler cette méthode protégée directement depuis un
 * seeder (pas de requête HTTP ni d'utilisateur admin authentifié dans ce
 * contexte, voir ResourceAccess::can() dans store()), donc on refait les
 * mêmes firstOrCreate() ici plutôt que de dupliquer tout le contrôleur.
 */
class ChampSupplementaireSeeder extends Seeder
{
    public function run(): void
    {
        $fichesJury = ChampSupplementaire::query()->updateOrCreate(
            ['libelle' => 'Démo — Fiche de notation du jury'],
            [
                'pour_pv' => false,
                'pour_releve' => false,
                'pour_soutenance' => true,
                'cycles' => [PvSoutenance::NIVEAU_LICENCE, PvSoutenance::NIVEAU_MASTER],
                'ordre' => 100,
                'actif' => true,
            ]
        );

        $attestationBourse = ChampSupplementaire::query()->updateOrCreate(
            ['libelle' => 'Démo — Attestation de bourse'],
            [
                'pour_pv' => true,
                'pour_releve' => true,
                'pour_soutenance' => false,
                'cycles' => [SessionDeliberation::CYCLE_LICENCE, SessionDeliberation::CYCLE_MASTER],
                'ordre' => 101,
                'actif' => true,
            ]
        );

        $this->synchroniserCasesRetroactives($fichesJury);
        $this->synchroniserCasesRetroactives($attestationBourse);

        $this->command?->info('2 champ(s) supplémentaire(s) de démonstration créé(s)/synchronisé(s).');
    }

    /** Même logique que ChampSupplementaireController::synchroniserCasesRetroactives() — voir le docblock de la classe. */
    protected function synchroniserCasesRetroactives(ChampSupplementaire $champ): void
    {
        if (! $champ->actif) {
            return;
        }

        if ($champ->pour_pv) {
            SessionDeliberation::query()
                ->whereIn('cycle', $champ->cycles ?? [])
                ->each(function (SessionDeliberation $session) use ($champ) {
                    SessionDeliberationFichier::firstOrCreate([
                        'session_deliberation_id' => $session->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE,
                        'numero' => null,
                        'document_id' => null,
                    ]);
                });
        }

        if ($champ->pour_releve) {
            SessionReleve::query()
                ->whereIn('cycle', $champ->cycles ?? [])
                ->each(function (SessionReleve $session) use ($champ) {
                    SessionReleveFichier::firstOrCreate([
                        'session_releve_id' => $session->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => SessionReleveFichier::ROLE_SUPPLEMENTAIRE,
                        'numero' => null,
                        'document_id' => null,
                    ]);
                });
        }

        if ($champ->pour_soutenance) {
            PvSoutenance::query()
                ->whereIn('niveau', $champ->cycles ?? [])
                ->each(function (PvSoutenance $pvSoutenance) use ($champ) {
                    PvSoutenanceFichier::firstOrCreate([
                        'pv_soutenance_id' => $pvSoutenance->id,
                        'champ_supplementaire_id' => $champ->id,
                    ], [
                        'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
                        'document_id' => null,
                    ]);
                });
        }
    }
}
