<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Petit point de passage unique pour l'export/import Excel (.xlsx), demandé
 * par le client pour les Étudiants, Documents, Référentiels et écrans
 * dédiés — s'appuie sur phpoffice/phpspreadsheet, déjà une dépendance du
 * projet (voir composer.json), pour ne jamais répéter sa mécanique dans
 * chaque contrôleur qui exporte ou importe un écran.
 *
 * Volontairement minimal : une seule feuille par fichier, pas de mise en
 * forme avancée (couleurs, formules, figer les volets...) au-delà de l'en-
 * tête en gras et des colonnes ajustées à leur contenu — ces fichiers
 * servent à retravailler des données dans Excel, pas à produire un document
 * présentable.
 */
class FichiersExcel
{
    /**
     * Construit et renvoie en téléchargement un fichier .xlsx à partir d'une
     * simple liste d'en-têtes (ligne 1, en gras) et de lignes de données.
     *
     * @param  array<int, string>  $entetes
     * @param  iterable<int, array<int, mixed>>  $lignes
     */
    public static function exporter(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        $feuille = new Spreadsheet;
        $sheet = $feuille->getActiveSheet();

        $sheet->fromArray($entetes, null, 'A1');

        $ligneCourante = 2;
        foreach ($lignes as $ligne) {
            $sheet->fromArray($ligne, null, 'A'.$ligneCourante);
            $ligneCourante++;
        }

        $derniereColonne = $sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$derniereColonne.'1')->getFont()->setBold(true);

        foreach (range('A', $derniereColonne) as $colonne) {
            $sheet->getColumnDimension($colonne)->setAutoSize(true);
        }

        $writer = new Xlsx($feuille);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lit un fichier .xlsx déjà uploadé (voir chaque import()) et renvoie
     * son contenu ligne par ligne, SANS la ligne d'en-tête (toujours la
     * première, voir exporter() ci-dessus) — chaque ligne est un tableau
     * indexé par position de colonne (0, 1, 2...), pas par nom d'en-tête :
     * c'est à l'appelant de savoir dans quel ordre son propre modèle range
     * ses colonnes (voir importTemplate() de chaque contrôleur).
     *
     * Les lignes entièrement vides (ex. une ligne blanche laissée en bas du
     * fichier par Excel) sont ignorées — sans ça, chaque import signalerait
     * à tort une erreur de validation sur une ligne qui ne contient rien.
     *
     * @return array<int, array<int, mixed>> indexé à partir de 1 = la
     *         première ligne de DONNÉES (juste après l'en-tête), pour que
     *         les numéros de ligne signalés dans les erreurs d'import
     *         correspondent à ce que l'utilisateur voit dans Excel (ligne 1 =
     *         en-tête, ligne 2 = première donnée...).
     */
    public static function lireLignes(string $cheminFichier): array
    {
        $feuille = IOFactory::load($cheminFichier);
        $sheet = $feuille->getActiveSheet();
        $lignes = $sheet->toArray(null, true, true, false);

        // Retire la ligne d'en-tête (position 0 dans le tableau PHP, mais
        // "ligne 1" dans Excel).
        array_shift($lignes);

        $resultat = [];
        $numeroLigne = 2;
        foreach ($lignes as $ligne) {
            $vide = ! array_filter($ligne, fn ($valeur) => $valeur !== null && $valeur !== '');

            if (! $vide) {
                $resultat[$numeroLigne] = $ligne;
            }

            $numeroLigne++;
        }

        return $resultat;
    }
}
