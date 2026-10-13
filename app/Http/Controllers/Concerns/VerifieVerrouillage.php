<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Support\Verrouillage;
use Illuminate\Http\RedirectResponse;

/**
 * Bloque le remplacement/suppression d'un document déjà verrouillé — voir
 * App\Support\Verrouillage pour les règles. Posé sur chaque contrôleur qui
 * dépose/retire un fichier (DepenseController, EtatFinancierController,
 * RecetteDossierController, ListeFinanceEtudiantController,
 * PvSoutenanceController, ReunionController, SessionDeliberationController,
 * SessionReleveController, RecetteScolariteController, EtudiantController,
 * DocumentController).
 *
 * refuserSiVerrouille() s'appelle en tout début de chaque action
 * uploaderXxx()/supprimerXxx() (ou équivalent), AVANT tout traitement
 * (sauvegarde du nouveau fichier, suppression de l'ancien...) — jamais après
 * avoir déjà accepté un dépôt qu'on refuserait ensuite. Elle ne fait rien si
 * $document est null : un PREMIER dépôt (aucun fichier existant à ce jour
 * dans cette case) n'est jamais bloqué, seul le REMPLACEMENT ou la
 * SUPPRESSION d'un document déjà enregistré peut l'être.
 *
 * Retourne une RedirectResponse|null plutôt que d'aborter directement (pas
 * d'abort(403) ici) : un document verrouillé n'est pas une faute de droits
 * (ResourceAccess) mais un cas métier normal, qui doit s'afficher comme les
 * autres erreurs de validation de l'application (back()->withErrors(...),
 * déjà affiché par le bloc @if ($errors->any()) de chaque vue) plutôt que
 * comme une page d'erreur 403 brute.
 */
trait VerifieVerrouillage
{
    protected function refuserSiVerrouille(?Document $document): ?RedirectResponse
    {
        if (! $document || ! Verrouillage::estVerrouille($document)) {
            return null;
        }

        return back()->withErrors([
            'verrouillage' => Verrouillage::raison($document).' Il doit d\'abord être déverrouillé (droit "Verrouillage des documents") avant de pouvoir être remplacé ou supprimé.',
        ]);
    }
}
