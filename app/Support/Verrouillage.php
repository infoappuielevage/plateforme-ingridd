<?php

namespace App\Support;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;

/**
 * Verrouillage des documents déjà enregistrés — demande explicite du client
 * ("verrouiller les documents déjà enregistrés... depuis 2025, sans
 * autorisation de l'administration"). Deux seuils INDÉPENDANTS, réglables
 * depuis Administration > Paramètres > Verrouillage (voir App\Models\Parametre
 * et les migrations 2026_10_21_000001/000002) :
 *
 * - `verrouillage_annee_academique_id` : verrouille tout document rattaché à
 *   CETTE année académique OU UNE ANNÉE ANTÉRIEURE. La comparaison se fait
 *   sur le libellé ("AAAA-AAAA", voir App\Models\AnneeAcademique) — ce
 *   format de longueur fixe se compare correctement comme une simple chaîne
 *   de caractères, inutile d'ajouter un champ de date dédié au référentiel
 *   rien que pour ce besoin.
 * - `verrouillage_date` : verrouille tout document DÉPOSÉ (created_at) avant
 *   cette date, quelle que soit son année académique — y compris les
 *   documents sans année académique renseignée (PV, documents
 *   administratifs...), que la première règle ne peut pas voir.
 *
 * Un document verrouillé par L'UNE OU L'AUTRE règle active reste verrouillé
 * tant qu'il n'a pas été explicitement déverrouillé (voir
 * Document::deverrouille_le/estVerrouille() et
 * DocumentController::deverrouiller()) — l'administration peut n'activer
 * qu'un seul des deux seuils, ou les deux à la fois.
 *
 * En plus de ces deux seuils AUTOMATIQUES, un document peut aussi être
 * verrouillé MANUELLEMENT, à la demande (`verrouille_le`/`verrouille_par_id`,
 * voir DocumentController::verrouiller()) — utile pour un document qui ne
 * tombe sous aucun des deux seuils mais doit tout de même être figé. Les
 * deux paires de colonnes (`verrouille_*`/`deverrouille_*`) sont
 * mutuellement exclusives : verrouiller efface toujours la paire
 * `deverrouille_*`, et déverrouiller efface toujours la paire
 * `verrouille_*` — au plus une des deux est renseignée à un instant donné.
 *
 * Portée volontairement limitée au FICHIER lui-même (remplacer/supprimer un
 * App\Models\Document) — pas à la fiche/au dossier qui le porte (une
 * App\Models\Depense, un App\Models\EtatFinancier...), dont les autres
 * champs restent librement modifiables. Voir App\Http\Controllers\Concerns\
 * VerifieVerrouillage, posé sur chaque contrôleur qui dépose/retire un
 * document, pour l'application concrète de ce blocage.
 */
class Verrouillage
{
    public static function estVerrouille(Document $document): bool
    {
        if ($document->deverrouille_le) {
            return false;
        }

        if ($document->verrouille_le) {
            return true;
        }

        return self::bloqueParAnnee($document) || self::bloqueParDate($document);
    }

    /** Explication lisible affichée à l'utilisateur (message d'erreur, badge) — jamais vide si estVerrouille() est vrai. */
    public static function raison(Document $document): string
    {
        if ($document->verrouille_le) {
            return 'Verrouillé manuellement'.($document->verrouillePar?->name ? ' par '.$document->verrouillePar->name : '').' le '.$document->verrouille_le->format('d/m/Y').'.';
        }

        $raisons = [];

        if (self::bloqueParAnnee($document)) {
            $raisons[] = "rattaché à l'année académique {$document->anneeAcademique?->libelle}, antérieure ou égale au seuil fixé par l'administration";
        }

        if (self::bloqueParDate($document)) {
            $raisons[] = 'déposé le '.$document->created_at->format('d/m/Y').', avant la date limite fixée par l\'administration';
        }

        return $raisons ? ucfirst(implode(' ; ', $raisons)).'.' : 'Ce document est verrouillé.';
    }

    protected static function bloqueParAnnee(Document $document): bool
    {
        $seuilId = Parametre::actuel()->verrouillage_annee_academique_id;

        if (! $seuilId || ! $document->annee_academique_id) {
            return false;
        }

        $document->loadMissing('anneeAcademique');

        if (! $document->anneeAcademique) {
            return false;
        }

        $seuil = AnneeAcademique::find($seuilId);

        return $seuil && $document->anneeAcademique->libelle <= $seuil->libelle;
    }

    protected static function bloqueParDate(Document $document): bool
    {
        $seuil = Parametre::actuel()->verrouillage_date;

        return $seuil && $document->created_at && $document->created_at->lt($seuil);
    }
}
