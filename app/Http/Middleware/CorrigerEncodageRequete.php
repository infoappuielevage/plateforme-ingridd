<?php

namespace App\Http\Middleware;

use App\Support\Encodage;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Corrige, dès l'entrée, tout texte reçu du navigateur qui ne serait pas de
 * l'UTF-8 valide — voir App\Support\Encodage pour le cas typique (Windows-
 * 1252). Repris tel quel du projet ingridd-platform : ce correctif est
 * générique (protocole Livewire/upload de fichiers), pas spécifique au
 * domaine des soutenances.
 *
 * Sans cette correction, un nom de fichier mal encodé (ex. choisi dans la
 * fenêtre "Ajouter un document") fait planter l'application avec l'erreur
 * "Malformed UTF-8 characters, possibly incorrectly encoded" avant même
 * d'avoir pu enregistrer quoi que ce soit.
 *
 * S'applique à toutes les requêtes web, y compris les appels Livewire
 * (/livewire/update) — voir bootstrap/app.php. Ne modifie jamais un texte
 * déjà valide en UTF-8 (voir Encodage::verSafeUtf8).
 */
class CorrigerEncodageRequete extends TransformsRequest
{
    protected function transform($key, $value)
    {
        return is_string($value) ? Encodage::verSafeUtf8($value) : $value;
    }
}
