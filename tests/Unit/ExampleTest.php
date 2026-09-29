<?php

namespace Tests\Unit;

use App\Models\Document;
use Tests\TestCase;

/**
 * Le dossier tests/Unit n'existait sur aucun disque tant qu'il ne contenait
 * aucun fichier (un dossier vide n'est jamais livré) — d'où l'erreur
 * "Test directory ... not found". Ce test, minimal mais réel, garantit que
 * le dossier existe et vérifie au passage une méthode utilitaire pure
 * (aucun accès base de données, donc un vrai test "unitaire").
 */
class ExampleTest extends TestCase
{
    public function test_la_taille_dun_document_est_formatee_lisiblement(): void
    {
        $document = new Document(['taille' => 2 * 1024 * 1024]);

        // PHP affiche "2" et non "2.0" pour un float rond converti en chaîne
        // (round(2.0, 1) reste un float 2.0, mais (string) 2.0 === "2").
        $this->assertSame('2 Mo', $document->tailleFormatee());
    }
}
