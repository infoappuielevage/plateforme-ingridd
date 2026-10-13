<?php

namespace Tests\Concerns;

use App\Support\FichiersExcel;
use Illuminate\Http\UploadedFile;

/**
 * Construit un vrai fichier .xlsx (contenu réel, pas aléatoire comme
 * UploadedFile::fake()->create()) à envoyer dans les tests d'import
 * (Étudiants, Référentiels) — réutilise App\Support\FichiersExcel::exporter()
 * elle-même pour fabriquer les octets, donc toujours dans un format que
 * FichiersExcel::lireLignes() (côté import()) sait relire.
 */
trait ConstruitFichierExcelUploade
{
    /**
     * @param  array<int, string>  $entetes
     * @param  array<int, array<int, mixed>>  $lignes
     */
    protected function fichierExcelUploade(array $entetes, array $lignes, string $nom = 'import.xlsx'): UploadedFile
    {
        $reponse = FichiersExcel::exporter($nom, $entetes, $lignes);

        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'test-import-').'.xlsx';

        ob_start();
        $reponse->sendContent();
        file_put_contents($cheminTemporaire, ob_get_clean());

        // `$test = true` : seul moyen de faire accepter un fichier qui n'est
        // pas réellement passé par un POST HTTP multipart (is_uploaded_file()
        // renverrait sinon false) — c'est le mécanisme standard de Laravel
        // pour ce genre de test, voir Illuminate\Http\UploadedFile::fake().
        return new UploadedFile($cheminTemporaire, $nom, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
