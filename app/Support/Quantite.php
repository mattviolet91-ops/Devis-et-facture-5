<?php

namespace App\Support;

/**
 * Quantités en millièmes (1,5 → 1500) : calculs exacts, sans nombre à virgule.
 */
class Quantite
{
    public static function lire(string $texte): ?int
    {
        $texte = str_replace(["\u{202F}", "\u{00A0}", ' '], '', trim($texte));

        if (! preg_match('/^(\d+)(?:[.,](\d{1,3}))?$/', $texte, $m)) {
            return null;
        }

        return (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '0', 3, '0');
    }

    public static function formater(int $milliemes): string
    {
        $entier = intdiv($milliemes, 1000);
        $reste = $milliemes % 1000;
        $texte = number_format($entier, 0, ',', "\u{202F}");

        return $reste === 0 ? $texte : $texte.','.rtrim(str_pad((string) $reste, 3, '0', STR_PAD_LEFT), '0');
    }

    /**
     * Total d'une ligne en centimes : quantité (millièmes) × prix unitaire (centimes), arrondi au centime.
     */
    public static function total(int $milliemes, int $prixCentimes): int
    {
        $produit = $milliemes * $prixCentimes;

        return intdiv($produit + ($produit >= 0 ? 500 : -500), 1000);
    }
}
