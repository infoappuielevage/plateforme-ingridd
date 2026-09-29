<?php

namespace App\Support;

/**
 * Petites fonctions de mise en forme partagées entre plusieurs écrans
 * (fiche document, écran Sauvegardes, widgets du tableau de bord) — évite de
 * dupliquer la même conversion octets → Ko/Mo/Go à plusieurs endroits.
 */
class Formatage
{
    public static function octets(int $octets): string
    {
        return match (true) {
            $octets >= 1024 ** 3 => round($octets / 1024 ** 3, 2).' Go',
            $octets >= 1024 ** 2 => round($octets / 1024 ** 2, 1).' Mo',
            $octets >= 1024 => round($octets / 1024, 1).' Ko',
            default => $octets.' o',
        };
    }
}
