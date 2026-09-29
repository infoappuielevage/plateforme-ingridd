<?php

namespace App\Support;

use App\Models\TypeDocument;

/**
 * Types de document qui reçoivent les champs complémentaires "Diplôme"
 * (titulaire, numéro, date de délivrance, mention, statut de retrait) sur
 * DocumentController::form()/table() et les widgets de tableau de bord.
 *
 * Historiquement une liste de codes figée dans le code (`DIPLOME`,
 * `ATTESTATION`) — désormais un simple filtre sur la colonne
 * `champs_diplome_actifs` de App\Models\TypeDocument (voir la migration
 * 2026_09_29_000021_...) : sur demande explicite du client ("tout doit
 * pouvoir être flexible via l'administrateur, pour éviter de toucher le
 * code après un déploiement"), un administrateur peut désormais activer ce
 * comportement sur N'IMPORTE QUEL type de document depuis Administration >
 * Référentiels > Types de documents, sans redéploiement. `ATTESTATION` et
 * `DIPLOME` (ce dernier désactivé/retiré, voir TypeDocumentSeeder) restent
 * les deux seuls types concernés par défaut — voir TypeDocumentSeeder pour
 * les valeurs initiales.
 */
class TypesDiplome
{
    /**
     * Les id des types de document ayant `champs_diplome_actifs = true`.
     * Peut être un tableau vide (aucun type configuré ainsi) — les
     * appelants doivent s'en accommoder plutôt que supposer un nombre
     * précis d'entrées.
     */
    public static function ids(): array
    {
        return TypeDocument::query()
            ->where('champs_diplome_actifs', true)
            ->pluck('id')
            ->all();
    }
}
