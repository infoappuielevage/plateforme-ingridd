<?php

namespace App\Support;

use App\Models\AnneeAcademique;
use App\Models\ChampSupplementaire;
use App\Models\Contrat;
use App\Models\Depense;
use App\Models\Document;
use App\Models\EtatFinancier;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\ListeFinanceEtudiant;
use App\Models\Niveau;
use App\Models\PvSoutenance;
use App\Models\RecetteDossier;
use App\Models\RecetteScolarite;
use App\Models\Reunion;
use App\Models\SessionDeliberation;
use App\Models\SessionReleve;
use App\Models\TypeContrat;
use App\Models\TypeDepense;
use App\Models\TypeDocument;
use App\Models\TypePieceEtudiant;

/**
 * Vue d'ensemble de TOUTES les corbeilles de l'application — demande
 * explicite du client après discussion sur les insuffisances de la corbeille
 * ("il n'existe aucune vue d'ensemble... le compteur du tableau de bord ne
 * compte que les documents génériques, exclut tout le reste"). Centralise,
 * pour CHAQUE écran ayant sa propre corbeille (un modèle avec SoftDeletes et
 * un écran de gestion dédié), le nombre d'éléments actuellement à la
 * corbeille et un lien direct vers cet écran en mode corbeille — pour qu'un
 * administrateur n'ait plus besoin de visiter une quinzaine d'écrans un par
 * un pour savoir ce qui traîne, ni se fier à un chiffre de tableau de bord
 * qui n'en comptait qu'une petite partie.
 *
 * Deux familles d'entrées (voir elements()) :
 * - un écran "simple" (une seule corbeille, ex. Réunions, Dossier étudiant,
 *   Recettes/Scolarité) : une ligne fixe, toujours affichée (même à 0, pour
 *   confirmer que c'est bien vérifié plutôt que silencieusement absent) ;
 * - un écran à onglets par catégorie OUVERTE (Dépenses, Contrats — voir
 *   App\Models\TypeDepense/TypeContrat, gérées depuis l'administration) :
 *   une ligne PAR CATÉGORIE ACTIVE, puisqu'une catégorie peut avoir sa
 *   propre corbeille avec son propre lien.
 *
 * Toute entrée est filtrée par App\Support\ResourceAccess::can($ressource,
 * 'delete') — même droit déjà utilisé par chaque écran pour afficher SON
 * propre lien "Corbeille" (voir par exemple ContratController::index()) :
 * cette vue ne montre jamais plus que ce que l'utilisateur pourrait déjà
 * voir en visitant chaque écran un par un, juste rassemblé au même endroit.
 */
class CorbeillesGlobales
{
    /**
     * @return array<int, array{ressource: string, libelle: string, icone: string, compteur: int, url: string}>
     */
    public static function elements(): array
    {
        $elements = array_merge(
            self::ecransSimples(),
            self::categoriesDepenses(),
            self::categoriesContrats(),
        );

        return array_values(array_filter(
            $elements,
            fn (array $element) => ResourceAccess::can($element['ressource'], 'delete')
        ));
    }

    /** Total toutes corbeilles confondues — déjà filtré par les droits de l'utilisateur courant (voir elements()), utilisé par la carte "En corbeille" du tableau de bord. */
    public static function total(): int
    {
        return array_sum(array_column(self::elements(), 'compteur'));
    }

    /**
     * Écrans à une seule corbeille — un modèle, une route "index" fixe.
     * Reprend App\Support\ResourceAccess::RESSOURCES/ICONES pour le libellé
     * et l'icône plutôt que de les redéfinir ici : toujours synchronisé avec
     * ce qu'affiche déjà le menu/l'écran Rôles & permissions pour la même
     * ressource.
     *
     * @return array<int, array{ressource: string, libelle: string, icone: string, compteur: int, url: string}>
     */
    protected static function ecransSimples(): array
    {
        $lignes = [
            // Exclut les mêmes types à écran dédié que DocumentController::
            // index()/DashboardController — sinon un document "Contrat" ou
            // "PV de réunion" encore non migré compterait deux fois (ici ET
            // dans sa propre ligne ci-dessous).
            ['ressource' => 'documents', 'compteur' => Document::onlyTrashed()->whereNotIn('type_document_id', TypesEcranDedie::ids())->count(), 'url' => route('gestion.documents.index', ['corbeille' => 1])],
            ['ressource' => 'sessions_deliberation', 'compteur' => SessionDeliberation::onlyTrashed()->count(), 'url' => route('gestion.sessions-deliberation.index', ['corbeille' => 1])],
            ['ressource' => 'sessions_releves', 'compteur' => SessionReleve::onlyTrashed()->count(), 'url' => route('gestion.sessions-releves.index', ['corbeille' => 1])],
            ['ressource' => 'pv_soutenances', 'compteur' => PvSoutenance::onlyTrashed()->count(), 'url' => route('gestion.pv-soutenances.index', ['corbeille' => 1])],
            ['ressource' => 'reunions', 'compteur' => Reunion::onlyTrashed()->count(), 'url' => route('gestion.reunions.index', ['corbeille' => 1])],
            ['ressource' => 'etudiants', 'compteur' => Etudiant::onlyTrashed()->count(), 'url' => route('gestion.etudiants.index', ['corbeille' => 1])],
            ['ressource' => 'etats_financiers', 'compteur' => EtatFinancier::onlyTrashed()->count(), 'url' => route('gestion.etats-financiers.index', ['corbeille' => 1])],
            // Gestion des recettes : trois écrans distincts partageant le
            // même droit "recettes" (voir resources/views/gestion/recettes/
            // _onglets.blade.php) — une ligne par écran, avec un libellé
            // précisé entre parenthèses puisque les trois partagent la même
            // ressource/le même libellé générique "Gestion des recettes".
            ['ressource' => 'recettes', 'libelle_suffixe' => ' — Scolarité', 'compteur' => RecetteScolarite::onlyTrashed()->count(), 'url' => route('gestion.recettes.scolarite.index', ['corbeille' => 1])],
            ['ressource' => 'recettes', 'libelle_suffixe' => ' — Retrait diplôme', 'compteur' => RecetteDossier::onlyTrashed()->where('type', RecetteDossier::TYPE_RETRAIT_DIPLOME)->count(), 'url' => route('gestion.recettes.dossiers.index', ['type' => 'retrait-diplome', 'corbeille' => 1])],
            ['ressource' => 'recettes', 'libelle_suffixe' => ' — Frais de soutenance', 'compteur' => RecetteDossier::onlyTrashed()->where('type', RecetteDossier::TYPE_FRAIS_SOUTENANCE)->count(), 'url' => route('gestion.recettes.dossiers.index', ['type' => 'frais-soutenance', 'corbeille' => 1])],
            ['ressource' => 'listes_finances_etudiants', 'compteur' => ListeFinanceEtudiant::onlyTrashed()->count(), 'url' => route('gestion.listes-finances-etudiants.index', ['corbeille' => 1])],
            // Référentiels (Administration > Référentiels).
            ['ressource' => 'types_documents', 'compteur' => TypeDocument::onlyTrashed()->count(), 'url' => route('gestion.types-documents.index', ['corbeille' => 1])],
            ['ressource' => 'filieres', 'compteur' => Filiere::onlyTrashed()->count(), 'url' => route('gestion.referentiels.index', ['referentiel' => 'filieres', 'corbeille' => 1])],
            ['ressource' => 'niveaux', 'compteur' => Niveau::onlyTrashed()->count(), 'url' => route('gestion.referentiels.index', ['referentiel' => 'niveaux', 'corbeille' => 1])],
            ['ressource' => 'annees_academiques', 'compteur' => AnneeAcademique::onlyTrashed()->count(), 'url' => route('gestion.referentiels.index', ['referentiel' => 'annees-academiques', 'corbeille' => 1])],
            ['ressource' => 'champs_supplementaires', 'compteur' => ChampSupplementaire::onlyTrashed()->count(), 'url' => route('gestion.champs-supplementaires.index', ['corbeille' => 1])],
            ['ressource' => 'types_pieces_etudiants', 'compteur' => TypePieceEtudiant::onlyTrashed()->count(), 'url' => route('gestion.types-pieces-etudiants.index', ['corbeille' => 1])],
            ['ressource' => 'types_depenses', 'compteur' => TypeDepense::onlyTrashed()->count(), 'url' => route('gestion.types-depenses.index', ['corbeille' => 1])],
            ['ressource' => 'types_contrats', 'compteur' => TypeContrat::onlyTrashed()->count(), 'url' => route('gestion.types-contrats.index', ['corbeille' => 1])],
        ];

        return array_map(fn (array $ligne) => self::completer($ligne), $lignes);
    }

    /**
     * Une ligne par catégorie ACTIVE de App\Models\TypeDepense — référentiel
     * OUVERT (l'administration peut en créer d'autres), donc impossible
     * d'énumérer les catégories ici comme pour les écrans simples ci-dessus :
     * on les relit à chaque appel.
     *
     * @return array<int, array{ressource: string, libelle: string, icone: string, compteur: int, url: string}>
     */
    protected static function categoriesDepenses(): array
    {
        return TypeDepense::query()->where('actif', true)->orderBy('ordre')->get()
            ->map(fn (TypeDepense $categorie) => self::completer([
                'ressource' => 'depenses',
                'libelle_suffixe' => ' — '.$categorie->libelle,
                'compteur' => Depense::onlyTrashed()->where('type_depense_id', $categorie->id)->count(),
                'url' => route('gestion.depenses.index', ['typeDepense' => $categorie->code, 'corbeille' => 1]),
            ]))
            ->all();
    }

    /** Pendant de categoriesDepenses() ci-dessus pour App\Models\TypeContrat. */
    protected static function categoriesContrats(): array
    {
        return TypeContrat::query()->where('actif', true)->orderBy('ordre')->get()
            ->map(fn (TypeContrat $categorie) => self::completer([
                'ressource' => 'contrats',
                'libelle_suffixe' => ' — '.$categorie->libelle,
                'compteur' => Contrat::onlyTrashed()->where('type_contrat_id', $categorie->id)->count(),
                'url' => route('gestion.contrats.index', ['typeContrat' => $categorie->code, 'corbeille' => 1]),
            ]))
            ->all();
    }

    /**
     * Complète une ligne partielle (ressource/compteur/url, et éventuellement
     * libelle_suffixe) avec le libellé et l'icône tirés de
     * App\Support\ResourceAccess — un seul endroit à modifier si un libellé
     * change un jour, jamais dupliqué ici.
     *
     * @param  array{ressource: string, compteur: int, url: string, libelle_suffixe?: string}  $ligne
     * @return array{ressource: string, libelle: string, icone: string, compteur: int, url: string}
     */
    protected static function completer(array $ligne): array
    {
        return [
            'ressource' => $ligne['ressource'],
            'libelle' => (ResourceAccess::RESSOURCES[$ligne['ressource']] ?? $ligne['ressource']).($ligne['libelle_suffixe'] ?? ''),
            'icone' => ResourceAccess::ICONES[$ligne['ressource']] ?? 'bi-trash3',
            'compteur' => $ligne['compteur'],
            'url' => $ligne['url'],
        ];
    }
}
