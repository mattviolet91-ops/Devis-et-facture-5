<?php

namespace App\Support;

/**
 * Taux de TVA en centièmes de pour cent (2000 = 20 %), jamais en nombre à virgule.
 */
class Tva
{
    public static function estFranchise(): bool
    {
        return reglage('tva.regime') === 'franchise';
    }

    /**
     * « 20 ; 10 ; 5,5 ; 0 » → [2000, 1000, 550, 0]. Renvoie null si un taux est illisible.
     *
     * @return list<int>|null
     */
    public static function lireListe(string $texte): ?array
    {
        $taux = [];

        foreach (preg_split('/[;\n]+/', $texte) as $morceau) {
            $morceau = trim(str_replace(['%', ' '], '', $morceau));
            if ($morceau === '') {
                continue;
            }
            $valeur = self::lire($morceau);
            if ($valeur === null) {
                return null;
            }
            $taux[] = $valeur;
        }

        $taux = array_values(array_unique($taux));
        rsort($taux);

        return $taux ?: null;
    }

    /**
     * « 5,5 » → 550 ; « 20 » → 2000. Null si illisible ou hors 0–100.
     */
    public static function lire(string $texte): ?int
    {
        if (! preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', trim($texte), $m)) {
            return null;
        }

        $valeur = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');

        return $valeur <= 10000 ? $valeur : null;
    }

    /**
     * 550 → « 5,5 % ».
     */
    public static function formater(int $taux, bool $avecSymbole = true): string
    {
        $texte = rtrim(rtrim(number_format($taux / 100, 2, ',', ''), '0'), ',');

        return $avecSymbole ? $texte.' %' : $texte;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $taux = (array) reglage('tva.taux');

        return collect($taux)->mapWithKeys(fn (int $t) => [$t => self::formater($t)])->all();
    }
}
