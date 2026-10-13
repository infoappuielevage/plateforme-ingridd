<?php

namespace Database\Seeders;

use App\Models\ChampSupplementaire;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use Illuminate\Database\Seeder;

/**
 * Documents RÉELS systématiquement requis sur un PV de soutenance, en plus
 * de l'image du registre et du diplôme (déjà fixes par défaut) — demande
 * explicite du client : "en plus des fichier image du registre et du
 * diplome par defaut, il ya les documents tel que l'autorisation de
 * soutenance, fiche de note, fiche de deliberation; fiche de notation de
 * stage", puis "qu'elle soit intégré dans seeders ... de tels sorte que
 * quand on rafraichi la base de données on ai pas a encore aller dans
 * l'administration pour les recreer". "Attestation de soutenance" ajoutée
 * ensuite ("ajoute l'attestation de soutenance en plus des quatre
 * documents"), distincte de "Autorisation de soutenance" (l'une autorise
 * la soutenance à se tenir, l'autre l'atteste après coup).
 *
 * Contrairement à ChampSupplementaireSeeder (données de DÉMONSTRATION,
 * préfixées "Démo —", volontairement NON appelé automatiquement — voir son
 * docblock), CE seeder-ci EST appelé depuis DatabaseSeeder : ce sont de
 * vrais champs attendus par le client dès l'installation et à chaque
 * `php artisan migrate:fresh --seed`, pas des exemples à retirer avant la
 * mise en production.
 *
 * updateOrCreate() par libellé (idempotent, comme RoleSeeder/
 * ChampSupplementaireSeeder) : rejouer ce seeder ne crée jamais de doublon
 * et resynchronise les PV de soutenance créés depuis la dernière exécution.
 *
 * Les 5 champs s'appliquent par défaut à TOUS les niveaux de PV de
 * soutenance (BTS, DTS, Licence, Master, Doctorat) : plus simple à
 * l'installation, sans empêcher l'administrateur de restreindre ensuite les
 * niveaux concernés (ex. "Fiche de notation de stage" uniquement pour le
 * BTS/DTS) depuis
 * Administration > Champs supplémentaires — une case déjà déposée n'est
 * jamais supprimée automatiquement, voir le docblock de `actif` sur
 * App\Models\ChampSupplementaire.
 *
 * La synchronisation rétroactive reproduit ICI, à l'identique, la logique de
 * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()
 * pour le volet "PV de soutenance" — pas de requête HTTP ni d'utilisateur
 * admin authentifié dans un seeder, donc impossible d'appeler cette méthode
 * protégée directement (voir le même choix dans ChampSupplementaireSeeder).
 */
class ChampSupplementaireSoutenanceSeeder extends Seeder
{
    /** @var array<int, string> */
    protected const LIBELLES = [
        'Autorisation de soutenance',
        'Attestation de soutenance',
        'Fiche de note',
        'Fiche de délibération',
        'Fiche de notation de stage',
    ];

    public function run(): void
    {
        $niveaux = array_keys(PvSoutenance::NIVEAUX);

        foreach (self::LIBELLES as $index => $libelle) {
            $champ = ChampSupplementaire::query()->updateOrCreate(
                ['libelle' => $libelle],
                [
                    'pour_pv' => false,
                    'pour_releve' => false,
                    'pour_soutenance' => true,
                    'cycles' => $niveaux,
                    'ordre' => 10 + $index,
                    'actif' => true,
                ]
            );

            $this->synchroniserCasesRetroactives($champ);
        }

        $this->command?->info(count(self::LIBELLES).' champ(s) supplémentaire(s) de soutenance créé(s)/synchronisé(s).');
    }

    /** Même logique que ChampSupplementaireController::synchroniserCasesRetroactives() (volet soutenance) — voir le docblock de la classe. */
    protected function synchroniserCasesRetroactives(ChampSupplementaire $champ): void
    {
        if (! $champ->actif || ! $champ->pour_soutenance) {
            return;
        }

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
