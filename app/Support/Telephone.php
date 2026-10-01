<?php

namespace App\Support;

class Telephone
{
    /**
     * « +33 6 12 34 56 78 » ou « 06.12.34.56.78 » → « 0612345678 ».
     */
    public static function normaliser(?string $numero): ?string
    {
        if ($numero === null || trim($numero) === '') {
            return null;
        }

        $chiffres = preg_replace('/\D/', '', $numero) ?? '';

        if (str_starts_with($chiffres, '0033')) {
            $chiffres = '0'.substr($chiffres, 4);
        } elseif (str_starts_with($chiffres, '33') && strlen($chiffres) === 11) {
            $chiffres = '0'.substr($chiffres, 2);
        }

        return $chiffres;
    }

    /**
     * « 0612345678 » → « 06 12 34 56 78 ».
     */
    public static function formater(?string $numero): string
    {
        $numero = (string) $numero;

        return strlen($numero) === 10 ? trim(chunk_split($numero, 2, ' ')) : $numero;
    }

    /**
     * Lien d'appel ou de SMS pour le téléphone.
     */
    public static function lien(?string $numero, string $schema = 'tel'): string
    {
        $numero = (string) $numero;
        if (strlen($numero) === 10 && $numero[0] === '0') {
            $numero = '+33'.substr($numero, 1);
        }

        return $schema.':'.$numero;
    }
}
