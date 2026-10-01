<?php

namespace App\Support;

/**
 * Montants en centimes (entiers) : jamais de nombre à virgule.
 */
class Montant
{
    /**
     * 123456 → « 1 234,56 € » (espace insécable fine comme séparateur de milliers).
     */
    public static function formater(int $centimes, bool $symbole = true): string
    {
        $negatif = $centimes < 0;
        $centimes = abs($centimes);
        $euros = intdiv($centimes, 100);
        $reste = $centimes % 100;

        $texte = number_format($euros, 0, ',', "\u{202F}").','.str_pad((string) $reste, 2, '0', STR_PAD_LEFT);

        return ($negatif ? '-' : '').$texte.($symbole ? "\u{00A0}€" : '');
    }

    /**
     * « 1 234,56 » ou « 1234.5 » ou « 12 € » → centimes. Null si illisible.
     */
    public static function lire(string $texte): ?int
    {
        $texte = str_replace(["\u{202F}", "\u{00A0}", ' ', '€'], '', trim($texte));

        if (! preg_match('/^(-?)(\d+)(?:[.,](\d{1,2}))?$/', $texte, $m)) {
            return null;
        }

        $centimes = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$centimes : $centimes;
    }

    /**
     * Part de TVA d'un montant HT, arrondie au centime (taux en centièmes de %).
     */
    public static function tva(int $htCentimes, int $taux): int
    {
        return intdiv($htCentimes * $taux + ($htCentimes >= 0 ? 5000 : -5000), 10000);
    }
}
