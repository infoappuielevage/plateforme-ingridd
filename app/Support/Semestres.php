<?php

namespace App\Support;

/**
 * Numérotation des semestres du système LMD, en fonction du niveau — voir
 * App\Filament\Resources\DocumentResource::form() (champ "Semestre", visible
 * uniquement pour un relevé de notes) et database/seeders/NiveauSeeder.php.
 *
 * Licence 1 = S1/S2, Licence 2 = S3/S4, Licence 3 = S5/S6, Master 1 = S7/S8,
 * Master 2 = S9/S10 — numérotation continue sur les 5 années, pas
 * remise à 1/2 à chaque niveau (précision donnée explicitement par le
 * client, contrairement au choix initial de ce projet qui proposait
 * uniquement "Semestre 1"/"Semestre 2" quel que soit le niveau).
 *
 * Basé sur les codes `Niveau::code` (L1, L2, L3, M1, M2 — voir
 * NiveauSeeder). Un niveau renommé/recodé, ou un niveau ajouté hors de ce
 * schéma LMD standard (ex. un doctorat), n'a simplement aucun semestre
 * associé ici : le champ Semestre reste alors désactivé pour ce niveau
 * plutôt que de planter — voir pourCodeNiveau().
 *
 * Volontairement PAS un réglage d'Administration > Paramètres (contrairement
 * aux familles de fichiers acceptées, par exemple) : c'est une numérotation
 * académique standard, pas un choix propre à l'établissement.
 */
class Semestres
{
    public const PAR_CODE_NIVEAU = [
        'L1' => [1, 2],
        'L2' => [3, 4],
        'L3' => [5, 6],
        'M1' => [7, 8],
        'M2' => [9, 10],
    ];

    /** @return array<int, int> */
    public static function pourCodeNiveau(?string $code): array
    {
        return self::PAR_CODE_NIVEAU[$code] ?? [];
    }

    /** @return array<int, string> pour un Select Filament (valeur => libellé), limité au niveau donné. */
    public static function optionsPourCodeNiveau(?string $code): array
    {
        return collect(self::pourCodeNiveau($code))
            ->mapWithKeys(fn (int $numero) => [$numero => "Semestre {$numero}"])
            ->all();
    }

    /** @return array<int, string> les 10 semestres, tous niveaux confondus — pour un filtre qui ne dépend pas d'un niveau précis. */
    public static function toutesLesOptions(): array
    {
        return collect(range(1, 10))
            ->mapWithKeys(fn (int $numero) => [$numero => "Semestre {$numero}"])
            ->all();
    }
}
