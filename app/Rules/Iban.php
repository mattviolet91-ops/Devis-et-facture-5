<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IBAN : format du pays et clé de contrôle (modulo 97).
 */
class Iban implements ValidationRule
{
    /** Longueurs des IBAN des pays les plus courants (zone SEPA). */
    private const LONGUEURS = [
        'FR' => 27, 'MC' => 27, 'BE' => 16, 'LU' => 20, 'CH' => 21, 'DE' => 22, 'ES' => 24,
        'IT' => 27, 'PT' => 25, 'NL' => 18, 'GB' => 22, 'IE' => 22, 'AT' => 20, 'LT' => 20,
        'BG' => 22, 'PL' => 28, 'RO' => 24, 'GR' => 27, 'DK' => 18, 'SE' => 24, 'FI' => 18,
    ];

    public static function nettoyer(string $valeur): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', $valeur) ?? '');
    }

    public static function formater(string $valeur): string
    {
        return trim(chunk_split(self::nettoyer($valeur), 4, ' '));
    }

    public static function estValide(string $valeur): bool
    {
        $iban = self::nettoyer($valeur);

        if (! preg_match('/^([A-Z]{2})(\d{2})([A-Z0-9]{10,30})$/', $iban, $m)) {
            return false;
        }

        if (isset(self::LONGUEURS[$m[1]]) && strlen($iban) !== self::LONGUEURS[$m[1]]) {
            return false;
        }

        $reorganise = substr($iban, 4).substr($iban, 0, 4);
        $numerique = '';
        foreach (str_split($reorganise) as $c) {
            $numerique .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }

        $reste = 0;
        foreach (str_split($numerique, 7) as $morceau) {
            $reste = (int) ($reste.$morceau) % 97;
        }

        return $reste === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::estValide((string) $value)) {
            $fail('Cet IBAN n\'est pas valable : vérifiez-le sur votre RIB (en France, FR suivi de 25 caractères).');
        }
    }
}
