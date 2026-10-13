<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Tri cliquable sur les en-têtes de colonne des tableaux de listes —
 * demande explicite du client ("ajoute la fonctionnalité de trier sur les
 * tableaux"), étendue à TOUS les tableaux de listes de l'application (pas
 * seulement PV de soutenance, qui a servi d'écran de référence) sur son
 * choix explicite entre "seulement cet écran" et "tous les tableaux de
 * l'application".
 *
 * Chaque contrôleur liste définit ses colonnes triables via une méthode
 * colonnesTriables() (ou équivalent) renvoyant un tableau [clé publique
 * utilisée dans l'URL => nom de colonne réel, OU Closure(Builder $query,
 * string $direction): void pour un tri plus complexe — relation via
 * sous-requête corrélée, compteur withCount, etc.].
 *
 * Deux méthodes séparées plutôt qu'une seule, pour pouvoir réutiliser le
 * [clé, direction] résolu SANS requête (même principe déjà en place dans ce
 * projet pour l'onglet "niveau" de PvSoutenanceController :: ongletNiveauValide(),
 * appelé à la fois dans requeteListe() et dans index()) :
 * - resoudreTri() valide `tri`/`direction` depuis la requête contre une
 *   LISTE BLANCHE de colonnes (jamais de colonne arbitraire injectée depuis
 *   l'URL — protection contre un tri sur une colonne sensible/inexistante
 *   qui ferait planter la requête SQL ou fuiter une colonne non prévue),
 *   retombe silencieusement sur un tri par défaut sinon (lien externe/favori
 *   obsolète) ; peut être appelée dans index() pour fournir triActuel/
 *   directionActuelle à la vue (voir resources/views/components/th-triable.blade.php)
 *   sans toucher à la base de données.
 * - appliquerTri() applique un [clé, direction] déjà résolu à une requête.
 *
 * Volontairement SANS dépendance à un trait "scope" Eloquent global
 * (`Model::query()->triable(...)`) : chaque écran a des colonnes et des
 * filtres différents, et le garder au niveau du contrôleur (comme
 * requeteListe() existant dans la plupart d'entre eux) évite d'étaler de la
 * logique de tri dans les modèles.
 */
trait ListeTriable
{
    /**
     * @param  array<string, string|Closure>  $colonnesTriables  clé publique => colonne réelle, OU Closure(Builder $query, string $direction): void
     * @param  string  $colonneParDefaut  clé retenue si absente/invalide dans l'URL
     * @param  string  $directionParDefaut  'asc' ou 'desc', utilisée si absente/invalide dans l'URL
     * @return array{0: string, 1: string} [clé de tri retenue, direction retenue]
     */
    protected function resoudreTri(
        Request $request,
        array $colonnesTriables,
        string $colonneParDefaut,
        string $directionParDefaut = 'asc'
    ): array {
        $cle = $request->string('tri')->toString();
        if ($cle === '' || ! array_key_exists($cle, $colonnesTriables)) {
            $cle = $colonneParDefaut;
        }

        $direction = $request->string('direction')->toString();
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $directionParDefaut;
        }

        return [$cle, $direction];
    }

    /**
     * @param  Builder  $query
     * @param  array<string, string|Closure>  $colonnesTriables  même tableau que celui passé à resoudreTri()
     * @param  array{0: string, 1: string}  $triResolu  résultat de resoudreTri()
     */
    protected function appliquerTri(Builder $query, array $colonnesTriables, array $triResolu): void
    {
        [$cle, $direction] = $triResolu;
        $colonne = $colonnesTriables[$cle];

        if ($colonne instanceof Closure) {
            $colonne($query, $direction);
        } else {
            $query->orderBy($colonne, $direction);
        }
    }

    /**
     * Variante d'appliquerTri() pour une Collection DÉJÀ chargée en mémoire
     * (`->get()->sortByDesc(...)`) plutôt qu'un Builder — demande explicite
     * du client ("n'oublie pas d'intégrer le tri sur tous les autres
     * tableaux nouvellement créés"), pour les écrans "un enregistrement par
     * année académique" (Dépenses, Recettes > Retrait diplôme/Frais de
     * soutenance/Scolarité, Liste des étudiants (finances)) : chacun a un
     * nombre d'enregistrements borné par celui des années académiques, d'où
     * une Collection déjà entièrement chargée plutôt qu'une pagination SQL —
     * voir leurs index() respectifs.
     *
     * MÊME CONTRAT que resoudreTri()/appliquerTri() ci-dessus, à une
     * différence près : $colonnesTriables associe ici une clé publique à une
     * Closure(mixed $item): mixed qui EXTRAIT la valeur de tri de chaque
     * élément (plutôt qu'un nom de colonne SQL ou une Closure(Builder,
     * direction), qui n'ont pas de sens pour une Collection déjà en mémoire).
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection
     * @param  array<string, Closure(mixed): mixed>  $colonnesTriables
     * @param  array{0: string, 1: string}  $triResolu
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function trierCollection(\Illuminate\Support\Collection $collection, array $colonnesTriables, array $triResolu): \Illuminate\Support\Collection
    {
        [$cle, $direction] = $triResolu;
        $extracteur = $colonnesTriables[$cle];

        return $direction === 'desc'
            ? $collection->sortByDesc($extracteur)->values()
            : $collection->sortBy($extracteur)->values();
    }
}
