<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SIRET : 14 chiffres et clé de contrôle (algorithme de Luhn).
 * Exception officielle : les établissements de La Poste (SIREN 356000000).
 */
class Siret implements ValidationRule
{
    public static function nettoyer(string $valeur): string
    {
        return preg_replace('/[\s.\-]/', '', $valeur) ?? '';
    }

    public static function estValide(string $valeur): bool
    {
        $siret = self::nettoyer($valeur);

        if (! preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        if (str_starts_with($siret, '356000000')) {
            return array_sum(str_split($siret)) % 5 === 0;
        }

        return Luhn::estValide($siret);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $siret = self::nettoyer((string) $value);

        if (! preg_match('/^\d{14}$/', $siret)) {
            $fail('Le SIRET doit faire 14 chiffres (vous en avez saisi '.strlen(preg_replace('/\D/', '', $siret)).').');

            return;
        }

        if (! self::estValide($siret)) {
            $fail('Ce SIRET n\'existe pas : un chiffre est sans doute mal tapé. Vérifiez-le sur votre extrait Kbis ou avis de situation INSEE.');
        }
    }
}
