<?php

namespace App\Support;

use Illuminate\Support\Str;

class Texte
{
    /**
     * « Hélène  ÉLODIE » → « helene elodie » : pour chercher sans tenir compte des accents.
     */
    public static function pourRecherche(?string $texte): string
    {
        $texte = Str::lower(Str::ascii((string) $texte));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9@.\s-]/', ' ', $texte)) ?? '');
    }

    /**
     * Mots d'une recherche, prêts pour un LIKE (caractères spéciaux neutralisés).
     *
     * @return list<string>
     */
    public static function motsRecherche(?string $saisie): array
    {
        $mots = array_filter(explode(' ', self::pourRecherche($saisie)));

        return array_values(array_map(fn (string $m) => addcslashes($m, '%_\\'), $mots));
    }
}
