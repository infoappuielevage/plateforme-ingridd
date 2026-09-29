<?php

namespace Database\Seeders;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use Illuminate\Database\Seeder;

/**
 * Sous-types pour les types de documents qui en ont (voir
 * App\Models\SousTypeDocument et TypeDocumentSeeder), recensés par le
 * client. Certaines listes sont explicitement ouvertes (ex. "Bordereau",
 * dont la liste d'organismes s'allonge au fil du temps, "etc." dans la
 * demande du client) : celles ci-dessous sont un point de départ,
 * complétable depuis Administration > Référentiels > Types de documents >
 * (ouvrir un type) > Sous-types, sans toucher au code.
 *
 * `updateOrCreate(['type_document_id' => ..., 'libelle' => ...], [...])` —
 * jamais de `delete()` — idempotent comme le reste des seeders du projet.
 * Si un type listé ci-dessous n'existe pas (référentiel pas encore seedé,
 * ou renommé/supprimé), ses sous-types sont simplement ignorés (même
 * dégradation silencieuse qu'ailleurs dans le projet — voir par exemple
 * App\Support\TypesDiplome).
 */
class SousTypeDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $sousTypesParType = [
            'ATTESTATION' => [
                'Succès', 'Vacation', 'Inscription', 'Niveau',
                'Recommandation', 'Suspension de scolarité', 'Travail', 'Correction',
            ],
            'CONGE' => ['Jouissance', 'Intérim'],
            'BILAN_COURS_LIGNE' => ['En ligne', 'En présentiel'],
            'BORDEREAU' => ['BNSP', 'CNSS', 'Coris Banque', 'FONER', 'Ministère', 'UNB'],
            'PV_DELIBERATION' => ['Licence', 'Master'],
        ];

        foreach ($sousTypesParType as $codeType => $libelles) {
            $type = TypeDocument::query()->where('code', $codeType)->first();

            if (! $type) {
                continue;
            }

            foreach ($libelles as $index => $libelle) {
                SousTypeDocument::query()->updateOrCreate(
                    ['type_document_id' => $type->id, 'libelle' => $libelle],
                    ['ordre' => $index + 1, 'actif' => true]
                );
            }
        }
    }
}
