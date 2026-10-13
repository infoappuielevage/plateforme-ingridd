<?php

namespace Tests\Concerns;

use App\Support\FichiersExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Petit utilitaire de test partagé par les tests d'export Excel (.xlsx) de
 * chaque contrôleur (Étudiants, Documents, Référentiels, écrans dédiés) —
 * évite de répéter la mécanique de capture d'une StreamedResponse (son
 * contenu n'existe qu'au moment de l'envoi HTTP, pas avant, voir
 * App\Support\FichiersExcel) dans chaque classe de test.
 */
trait CaptureExportExcel
{
    /** @return array<int, array<int, mixed>> les lignes de DONNÉES (sans l'en-tête), voir FichiersExcel::lireLignes(). */
    protected function lignesExportees(StreamedResponse $reponse): array
    {
        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'test-export-');

        ob_start();
        $reponse->sendContent();
        file_put_contents($cheminTemporaire, ob_get_clean());

        $lignes = FichiersExcel::lireLignes($cheminTemporaire);

        unlink($cheminTemporaire);

        return $lignes;
    }
}
