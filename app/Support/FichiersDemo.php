<?php

namespace App\Support;

use ZipArchive;

/**
 * Génère de VRAIS fichiers PDF et XLSX minimalistes (binaire conforme aux
 * formats, pas de bibliothèque tierce — PHP pur, zip natif) pour les
 * documents de démonstration (voir database/seeders/DocumentSeeder.php et
 * database/seeders/GestionFinanciereDemoSeeder.php).
 *
 * AVANT cette classe, les deux seeders écrivaient un simple texte de
 * remplacement sous une extension .pdf/.xlsx — "Télécharger" fonctionnait
 * (le fichier existe bien sur le disque), mais "Voir" (aperçu dans le
 * navigateur, voir routes/web.php `documents.voir` et
 * resources/views/gestion/documents/apercu.blade.php) échouait : un lecteur
 * PDF/un parseur Excel (SheetJS) reçoit un fichier qui n'est, en réalité, ni
 * l'un ni l'autre — demande explicite du client après avoir remarqué que
 * l'aperçu ne fonctionnait pas sur les données de démonstration.
 *
 * Volontairement MINIMAL (pas de styles.xml, pas de polices embarquées,
 * texte brut) : l'objectif est seulement qu'un lecteur PDF/un parseur xlsx
 * (SheetJS côté navigateur, ou Word/Excel eux-mêmes) les reconnaisse et les
 * affiche correctement, pas de produire un document "joli" — ces fichiers
 * restent des données de démonstration, jamais de vrais documents.
 */
class FichiersDemo
{
    // --------------------------------------------------------------
    // PDF — structure minimale valide à la main (1 page, texte seul, police
    // standard Helvetica) : pas besoin d'une bibliothèque PDF pour ça, et ça
    // évite une dépendance Composer supplémentaire rien que pour des
    // données de démonstration.
    // --------------------------------------------------------------

    /**
     * @param  array<int, string>  $lignes  Lignes de texte affichées sous le titre.
     */
    public static function pdf(string $titre, array $lignes = []): string
    {
        $contenuFlux = self::ligneTexte($titre, 780, 16);
        $y = 750;

        foreach ($lignes as $ligne) {
            $contenuFlux .= self::ligneTexte($ligne, $y, 11);
            $y -= 20;
        }

        // Objets PDF minimaux : 1 catalogue, 2 pages (racine), 3 une page,
        // 4 une police standard (WinAnsiEncoding — gère les accents
        // français), 5 le flux de contenu texte ci-dessus.
        $objets = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 595 842] /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => "<< /Length ".strlen($contenuFlux)." >>\nstream\n{$contenuFlux}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $decalages = [];

        foreach ($objets as $numero => $corps) {
            $decalages[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$corps}\nendobj\n";
        }

        $decalageXref = strlen($pdf);
        $total = count($objets) + 1; // +1 pour l'entrée libre "0" exigée par le format.

        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        foreach ($objets as $numero => $corps) {
            $pdf .= sprintf("%010d 00000 n \n", $decalages[$numero]);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$decalageXref}\n%%EOF";

        return $pdf;
    }

    protected static function ligneTexte(string $texte, int $y, int $taille): string
    {
        return "BT /F1 {$taille} Tf 50 {$y} Td (".self::echapperTextePdf($texte).") Tj ET\n";
    }

    /** Échappe les caractères spéciaux PDF ( ) \ et convertit en Windows-1252 (encodage attendu par /WinAnsiEncoding, gère les accents français). */
    protected static function echapperTextePdf(string $texte): string
    {
        $texte = str_replace(["\r", "\n"], ' ', $texte);
        $texte = @mb_convert_encoding($texte, 'Windows-1252', 'UTF-8') ?: $texte;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texte);
    }

    // --------------------------------------------------------------
    // XLSX — classeur minimal valide (une feuille, cellules en "inline
    // string" pour éviter d'avoir à générer xl/sharedStrings.xml, pas de
    // styles.xml) construit avec ZipArchive (extension PHP native, déjà
    // activée par défaut sous XAMPP — un .xlsx N'EST qu'une archive ZIP de
    // fichiers XML).
    // --------------------------------------------------------------

    /**
     * @param  array<int, array<int, string>>  $lignes  Chaque ligne = un tableau de valeurs de colonnes (texte).
     */
    public static function xlsx(string $titreFeuille, array $lignes): string
    {
        $titreFeuille = self::nettoyerNomFeuille($titreFeuille);

        $lignesXml = '';
        foreach ($lignes as $indexLigne => $colonnes) {
            $numeroLigne = $indexLigne + 1;
            $cellulesXml = '';

            foreach (array_values($colonnes) as $indexColonne => $valeur) {
                $reference = self::referenceCellule($indexColonne, $numeroLigne);
                $texteEchappe = htmlspecialchars((string) $valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $cellulesXml .= "<c r=\"{$reference}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$texteEchappe}</t></is></c>";
            }

            $lignesXml .= "<row r=\"{$numeroLigne}\">{$cellulesXml}</row>";
        }

        $feuilleXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<sheetData>{$lignesXml}</sheetData>"
            .'</worksheet>';

        $typesContenuXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';

        $relationsRacineXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $classeurXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($titreFeuille, ENT_XML1 | ENT_COMPAT, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';

        $relationsClasseurXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';

        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'xlsx_demo_');

        $zip = new ZipArchive();
        $zip->open($cheminTemporaire, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $typesContenuXml);
        $zip->addFromString('_rels/.rels', $relationsRacineXml);
        $zip->addFromString('xl/workbook.xml', $classeurXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relationsClasseurXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $feuilleXml);
        $zip->close();

        $contenu = file_get_contents($cheminTemporaire);
        unlink($cheminTemporaire);

        return $contenu;
    }

    /** Index de colonne (0 = A, 1 = B... 26 = AA...) + numéro de ligne -> référence de cellule ("A1", "B1"...). */
    protected static function referenceCellule(int $indexColonne, int $numeroLigne): string
    {
        $lettres = '';
        $n = $indexColonne + 1;

        while ($n > 0) {
            $reste = ($n - 1) % 26;
            $lettres = chr(65 + $reste).$lettres;
            $n = intdiv($n - 1, 26);
        }

        return $lettres.$numeroLigne;
    }

    /** Un nom de feuille Excel ne peut pas contenir : \ / ? * [ ] et est limité à 31 caractères. */
    protected static function nettoyerNomFeuille(string $nom): string
    {
        $nom = str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $nom);

        return mb_substr($nom, 0, 31);
    }
}
