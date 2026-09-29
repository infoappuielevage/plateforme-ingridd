<?php

namespace App\Support;

use App\Models\TypeDocument;

/**
 * Types de document qui reçoivent les champs complémentaires "PV de
 * soutenance" (thème du mémoire, date de soutenance, membres du jury, ainsi
 * que `nom_titulaire`/`mention`, réutilisés depuis App\Support\TypesDiplome)
 * — numérisation du registre papier des soutenances de mémoire (sur demande
 * du client).
 *
 * Historiquement une liste de codes figée dans le code (`PV_SOUTENANCE`) —
 * désormais un simple filtre sur la colonne `champs_soutenance_actifs` de
 * App\Models\TypeDocument (voir la migration
 * 2026_09_29_000021_..., même principe que App\Support\TypesDiplome) : un
 * administrateur peut activer ce comportement sur n'importe quel type
 * depuis l'administration, sans toucher au code.
 */
class TypesPvSoutenance
{
    /**
     * Même logique défensive que TypesDiplome::ids() : un tableau vide si
     * aucun type n'est configuré ainsi, plutôt qu'une erreur.
     */
    public static function ids(): array
    {
        return TypeDocument::query()
            ->where('champs_soutenance_actifs', true)
            ->pluck('id')
            ->all();
    }
}
