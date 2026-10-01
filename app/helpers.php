<?php

use App\Support\Reglages;

if (! function_exists('reglage')) {
    /**
     * Lit un réglage de l'entreprise (table settings, sinon config/entreprise.php).
     */
    function reglage(string $cle, mixed $defaut = null): mixed
    {
        return app(Reglages::class)->get($cle, $defaut);
    }
}
