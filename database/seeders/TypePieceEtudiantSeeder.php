<?php

namespace Database\Seeders;

use App\Models\TypePieceEtudiant;
use Illuminate\Database\Seeder;

/**
 * Liste de départ des pièces attendues dans un dossier étudiant — demande
 * explicite du client (voir le docblock de la migration
 * 2026_10_09_000001_create_types_pieces_etudiants_table.php). Librement
 * complétée ensuite depuis Référentiels > Types de pièces (dossier étudiant),
 * comme les autres référentiels de démarrage (NiveauSeeder...).
 *
 * Les quatre premières (recurrente = false) sont des pièces d'IDENTITÉ,
 * valables pour tout le parcours de l'étudiant ; les quatre suivantes
 * (recurrente = true) sont liées à une année académique précise — "il
 * faudrait que l'on puisse ranger les fichiers en fonction du niveau
 * d'étude ou de l'année académique".
 *
 * `code` sert de clé stable (voir Niveau/TypeDocument) : `updateOrCreate()`
 * ne duplique donc jamais au rejeu, et c'est sur ce code que
 * App\Http\Controllers\EtudiantController s'appuie pour retrouver un type
 * précis (ex. 'CNIB') sans dépendre du libellé, modifiable depuis
 * l'administration.
 *
 * Appelle ensuite TypePieceEtudiant::synchroniserCasesRetroactives() pour
 * chaque type — exactement comme le ferait un admin en l'ajoutant depuis
 * l'écran Référentiels > Types de pièces (voir
 * App\Http\Controllers\TypePieceEtudiantController::store()) — pour que ce
 * seeder profite lui aussi aux dossiers étudiant RÉELS déjà créés avant
 * cette refonte, et en particulier rattache automatiquement une pièce déjà
 * déposée sous l'ancienne liste libre (ex. la CNIB) à son nouveau type.
 */
class TypePieceEtudiantSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'CNIB', 'libelle' => 'CNIB', 'recurrente' => false, 'ordre' => 1],
            ['code' => 'ACTE_NAISSANCE', 'libelle' => 'Acte de naissance', 'recurrente' => false, 'ordre' => 2],
            ['code' => 'DIPLOME_ANTERIEUR', 'libelle' => 'Diplôme / attestation antérieure', 'recurrente' => false, 'ordre' => 3],
            ['code' => 'PHOTO_IDENTITE', 'libelle' => "Photo d'identité", 'recurrente' => false, 'ordre' => 4],
            ['code' => 'FICHE_INSCRIPTION', 'libelle' => "Fiche d'inscription", 'recurrente' => true, 'ordre' => 5],
            ['code' => 'RELEVES_NOTES', 'libelle' => 'Relevés de notes', 'recurrente' => true, 'ordre' => 6],
            ['code' => 'ATTESTATION_ENGAGEMENT', 'libelle' => "Attestation d'engagement", 'recurrente' => true, 'ordre' => 7],
            ['code' => 'FICHE_REINSCRIPTION', 'libelle' => 'Fiche de réinscription', 'recurrente' => true, 'ordre' => 8],
        ];

        foreach ($types as $type) {
            $modele = TypePieceEtudiant::query()->updateOrCreate(['code' => $type['code']], $type);
            $modele->synchroniserCasesRetroactives();
        }
    }
}
