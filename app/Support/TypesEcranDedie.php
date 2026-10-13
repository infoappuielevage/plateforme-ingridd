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
 * "avoir un écran dédié" est un fait du CODE (ces cinq contrôleurs
 * existent réellement), pas un comportement qu'un administrateur peut
 * activer sur un type quelconque depuis l'administration — voir le docblock
 * de database/seeders/TypeDocumentSeeder.php pour le contexte complet du
 * nettoyage ("plus de doublons ailleurs que l'écran dédié").
 *
 * PV_REUNION a rejoint cette liste avec App\Http\Controllers\
 * ReunionController ("avançons sur le PV des réunions") — mêmes raisons que
 * les trois premiers.
 *
 * DOSSIER_ETUDIANT a rejoint cette liste à son tour avec App\Http\Controllers\
 * EtudiantController ("travaillons sur le dossier étudiant") — même raison :
 * les pièces d'un dossier étudiant se déposent uniquement depuis son écran
 * dédié, jamais depuis le formulaire général "Document".
 *
 * ETAT_FINANCIER (App\Http\Controllers\EtatFinancierController) et RECETTE
 * (partagé par App\Http\Controllers\RecetteScolariteController et
 * App\Http\Controllers\RecetteDossierController — demande explicite du
 * client, "intègre la gestion des États financières... PUIS intègre la
 * Gestion des Recette") ont rejoint la liste à leur tour, même raison. Un
 * seul code RECETTE pour les trois volets de Gestion des Recettes
 * (Scolarité/Retrait diplôme/Frais de soutenance) : ils partagent déjà la
 * même clé ResourceAccess ci-dessous, un code par volet n'apporterait rien.
 *
 * DEPENSE (App\Http\Controllers\DepenseController, un seul code partagé par
 * TOUTES les catégories — App\Models\TypeDepense — même raisonnement que
 * RECETTE ci-dessus) et LISTE_ETUDIANTS_FINANCES (App\Http\Controllers\
 * ListeFinanceEtudiantController) ont rejoint la liste à leur tour — demande
 * explicite du client, "intègre la GESTION des DEPENSES... intègre la
 * GESTION de la listes des etudiant".
 *
 * CONTRAT (App\Http\Controllers\ContratController, un seul code partagé par
 * TOUTES les catégories — App\Models\TypeContrat — même raisonnement que
 * DEPENSE/RECETTE ci-dessus) a rejoint la liste à son tour — demande
 * explicite du client, "intègre à présent la gestion des contrat".
 */
class TypesEcranDedie
{
    public const CODES = [
        'PV_SOUTENANCE', 'PV_DELIBERATION', 'RELEVE_NOTES', 'PV_REUNION', 'DOSSIER_ETUDIANT',
        'ETAT_FINANCIER', 'RECETTE', 'DEPENSE', 'LISTE_ETUDIANTS_FINANCES', 'CONTRAT',
    ];

    /**
     * Code de type de document => clé App\Support\ResourceAccess du module
     * propriétaire. Faille de sécurité corrigée ici (demande explicite du
     * client, "revoir la sécurité pour éviter l'accessibilité à des pages
     * confidentielles sans autorisation") : `documents.telecharger`/
     * `documents.voir` (voir routes/web.php) restent le mécanisme de
     * téléchargement PARTAGÉ par tous les écrans, dédiés ou non — un
     * utilisateur ayant uniquement le droit "documents" -> "Voir" (ex. le
     * rôle "Gestionnaire" du référentiel de départ, voir
     * database/seeders/RoleSeeder.php, qui n'a PAS le droit "etudiants") ne
     * doit pas pouvoir télécharger une pièce d'un dossier étudiant, un PV de
     * soutenance, etc. simplement parce qu'il a le droit générique
     * "documents" : il lui faut AUSSI le droit "Voir" du module propriétaire
     * — voir ressourcePourCode() ci-dessous, utilisée par ces deux routes.
     */
    public const RESSOURCE_PAR_CODE = [
        'PV_SOUTENANCE' => 'pv_soutenances',
        'PV_DELIBERATION' => 'sessions_deliberation',
        'RELEVE_NOTES' => 'sessions_releves',
        'PV_REUNION' => 'reunions',
        'DOSSIER_ETUDIANT' => 'etudiants',
        'ETAT_FINANCIER' => 'etats_financiers',
        'RECETTE' => 'recettes',
        'DEPENSE' => 'depenses',
        'LISTE_ETUDIANTS_FINANCES' => 'listes_finances_etudiants',
        'CONTRAT' => 'contrats',
    ];

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

    /** La clé ResourceAccess du module propriétaire pour ce code de type, ou null si ce type n'a pas d'écran dédié (document "libre", formulaire général). */
    public static function ressourcePourCode(?string $code): ?string
    {
        return $code ? (self::RESSOURCE_PAR_CODE[$code] ?? null) : null;
    }
}
