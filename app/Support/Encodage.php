<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Convertit vers de l'UTF-8 valide un texte qui ne l'est pas déjà.
 *
 * Cas typique : un fichier CSV exporté depuis Excel sur Windows, enregistré
 * en Windows-1252 plutôt qu'en UTF-8 — les caractères accentués (é, è, à,
 * ô, ç...) s'affichent normalement dans Excel, mais leurs octets ne sont
 * pas valides une fois relus comme de l'UTF-8. Tant que ce texte reste en
 * base tel quel, tout ce qui doit l'encoder en JSON plante — dont les
 * réponses Livewire, avec l'erreur "Malformed UTF-8 characters, possibly
 * incorrectly encoded" (Illuminate\Http\JsonResponse) dès qu'un composant
 * affichant ce texte est sollicité.
 *
 * ⚠️ Le test de validité utilisé ici est volontairement `json_encode()` —
 * pas seulement `mb_check_encoding()`. Les deux ne sont pas équivalents :
 * `mb_check_encoding()` accepte certaines séquences d'octets techniquement
 * malformées (encodages "surlongs", par exemple) que `json_encode()` refuse
 * ensuite quand même — exactement le problème qu'on cherche à éviter ici.
 * S'appuyer uniquement sur `mb_check_encoding()` peut donc déclarer "valide"
 * un texte qui fera pourtant planter Livewire.
 *
 * self::verSafeUtf8() ne touche jamais un texte déjà valide (au sens
 * json_encode) — uniquement les octets réellement problématiques, en
 * tentant d'abord une ré-interprétation Windows-1252 (le cas de loin le
 * plus fréquent), puis en dernier recours en retirant les octets qui ne
 * correspondent à rien de connu plutôt que de laisser planter l'application.
 *
 * Repris tel quel du projet ingridd-platform : générique, pas spécifique au
 * domaine des archives/documents.
 */
class Encodage
{
    public static function verSafeUtf8(mixed $valeur): mixed
    {
        if (is_array($valeur)) {
            return array_map(self::verSafeUtf8(...), $valeur);
        }

        if (! is_string($valeur) || $valeur === '') {
            return $valeur;
        }

        if (self::estUtf8Valide($valeur)) {
            return $valeur;
        }

        $depuisWindows1252 = @mb_convert_encoding($valeur, 'UTF-8', 'Windows-1252');

        if ($depuisWindows1252 !== false && self::estUtf8Valide($depuisWindows1252)) {
            return $depuisWindows1252;
        }

        // Dernier recours : retire les octets réellement illisibles plutôt
        // que de laisser planter l'application — perte minime, seulement
        // sur les octets qui ne correspondent à rien de connu.
        $substitutionPrecedente = mb_substitute_character();
        mb_substitute_character('none');
        $nettoye = mb_convert_encoding($valeur, 'UTF-8', 'UTF-8');
        mb_substitute_character($substitutionPrecedente);

        if (self::estUtf8Valide($nettoye)) {
            return $nettoye;
        }

        // Filet de sécurité ultime (ne devrait normalement jamais être
        // atteint) : retire tout octet non-ASCII plutôt que de risquer de
        // laisser passer un texte qui ferait encore planter l'application.
        return preg_replace('/[\x80-\xFF]/', '', $valeur);
    }

    /**
     * true seulement si $valeur peut réellement être encodée en JSON —
     * voir la note en tête de classe sur pourquoi ce n'est pas la même
     * chose que mb_check_encoding($valeur, 'UTF-8').
     */
    public static function estUtf8Valide(string $valeur): bool
    {
        return mb_check_encoding($valeur, 'UTF-8') && json_encode($valeur) !== false;
    }

    /**
     * Filet de sécurité générique pour une structure de résultats : corrige,
     * **en mémoire seulement** (rien n'est enregistré en base ici), tout
     * texte mal encodé dans les modèles Eloquent contenus — y compris ceux
     * qu'on n'aurait pas pensé à vérifier explicitement (une relation
     * chargée en cours de route, par exemple).
     *
     * Descend récursivement dans les tableaux, les Collection, et les
     * relations déjà chargées de chaque modèle (`Model::getRelations()`) —
     * jamais dans une relation non chargée (pas de requête supplémentaire
     * déclenchée). Une correction appliquée ici est aussi consignée dans
     * les journaux (storage/logs/laravel.log).
     */
    public static function purifierResultats(mixed $valeur): mixed
    {
        if ($valeur instanceof Model) {
            self::purifierModele($valeur);

            return $valeur;
        }

        if ($valeur instanceof Collection) {
            $valeur->each(fn ($item) => self::purifierResultats($item));

            return $valeur;
        }

        if (is_array($valeur)) {
            foreach ($valeur as &$item) {
                $item = self::purifierResultats($item);
            }

            return $valeur;
        }

        return $valeur;
    }

    private static function purifierModele(Model $modele): void
    {
        $attributs = $modele->getAttributes();
        $corriges = [];

        foreach ($attributs as $colonne => $valeur) {
            if (! is_string($valeur) || $valeur === '' || self::estUtf8Valide($valeur)) {
                continue;
            }

            $corrige = self::verSafeUtf8($valeur);
            $corriges[$colonne] = $corrige;

            Log::warning('Encodage corrigé en mémoire (données déjà en base)', [
                'modele' => get_class($modele),
                'id' => $modele->getKey(),
                'colonne' => $colonne,
            ]);
        }

        if (! empty($corriges)) {
            $modele->setRawAttributes(array_merge($attributs, $corriges), true);
        }

        foreach ($modele->getRelations() as $relation) {
            self::purifierResultats($relation);
        }
    }
}
