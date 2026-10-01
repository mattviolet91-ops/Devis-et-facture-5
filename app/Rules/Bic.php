<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Bic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(trim((string) $value)))) {
            $fail('Le BIC fait 8 ou 11 caractères (exemple : AGRIFRPP ou AGRIFRPP882). Il figure sur votre RIB.');
        }
    }
}
