<?php

namespace App\Rules;

class Luhn
{
    public static function estValide(string $chiffres): bool
    {
        $somme = 0;
        $longueur = strlen($chiffres);

        for ($i = 0; $i < $longueur; $i++) {
            $chiffre = (int) $chiffres[$longueur - 1 - $i];
            if ($i % 2 === 1) {
                $chiffre *= 2;
                if ($chiffre > 9) {
                    $chiffre -= 9;
                }
            }
            $somme += $chiffre;
        }

        return $somme % 10 === 0;
    }
}
