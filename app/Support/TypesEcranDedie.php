<?php

namespace App\Support;

use App\Models\TypeDocument;

/**
 * Types de document désormais gérés par un écran dédié plutôt que par le
 * formulaire général "Document" — PV de soutenance, PV de délibération,
 * Relevés de notes (voir PvSoutenanceController/SessionDeliberationController/
 * SessionReleveController). Chaque fichier reste un vrai App\Models\Document
 * "en coulisse" (même table, voir le docblock de chaque contrôleur dédié :
 * `type_document_id` y est toujours renseigné), mais la SAISIE ne doit plus
 * passer par ailleurs, sous peine de créer un document orphelin — sans
 * étudiant/session associé, invisible depuis l'écran dédié bien que toujours
 * présent dans "Tous les documents" (voir DocumentController::valider()).
 *
 * Liste FIXE de codes, contrairement à App\Support\TypesDiplome/
 * TypesPvSoutenance (qui lisent une colonne configurable de TypeDocument) :
 * "avoir un écran dédié" est un fait du CODE (ces trois contrôleurs
 * existent réellement), pas un comportement qu'un administrateur peut
 * activer sur un type quelconque depuis l'administration — voir le docblock
 * de database/seeders/TypeDocumentSeeder.php pour le contexte complet du
 * nettoyage ("plus de doublons ailleurs que l'écran dédié").
 */
class TypesEcranDedie
{
    public const CODES = ['PV_SOUTENANCE', 'PV_DELIBERATION', 'RELEVE_NOTES'];

    /**
     * Les id des types concernés — peut être un tableau vide sur une base
     * qui n'a pas encore ces types (référentiel pas encore seedé), les
     * appelants doivent s'en accommoder plutôt que supposer un nombre
     * précis d'entrées (même principe défensif que TypesDiplome::ids()).
     */
    public static function ids(): array
    {
        return TypeDocument::query()->whereIn('code', self::CODES)->pluck('id')->all();
    }
}
