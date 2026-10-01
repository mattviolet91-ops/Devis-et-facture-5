<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de TVA intracommunautaire français : FR + clé (2 chiffres) + SIREN (9 chiffres).
 * Clé = (12 + 3 × (SIREN mod 97)) mod 97.
 */
class TvaIntracom implements ValidationRule
{
    public function __construct(private ?string $siret = null) {}

    public static function nettoyer(string $valeur): string
    {
        return strtoupper(preg_replace('/[\s.\-]/', '', $valeur) ?? '');
    }

    public static function cle(string $siren): string
    {
        return str_pad((string) ((12 + 3 * ((int) $siren % 97)) % 97), 2, '0', STR_PAD_LEFT);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tva = self::nettoyer((string) $value);

        if (! preg_match('/^FR(\d{2})(\d{9})$/', $tva, $m)) {
            $fail('Le numéro de TVA s\'écrit FR suivi de 11 chiffres (exemple : FR 12 345678901).');

            return;
        }

        if ($m[1] !== self::cle($m[2])) {
            $fail('Ce numéro de TVA n\'est pas valable : vérifiez les chiffres.');

            return;
        }

        $siret = Siret::nettoyer((string) $this->siret);
        if (strlen($siret) === 14 && substr($siret, 0, 9) !== $m[2]) {
            $fail('Le numéro de TVA ne correspond pas au SIRET (les 9 derniers chiffres doivent être le début du SIRET).');
        }
    }
}
