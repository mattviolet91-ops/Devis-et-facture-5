<?php

namespace App\Console\Commands;

use App\Services\GestionDevis;
use Illuminate\Console\Command;

class ExpirerDevis extends Command
{
    protected $signature = 'app:expirer-devis';

    protected $description = 'Passe en « expiré » les devis envoyés dont la validité est dépassée';

    public function handle(GestionDevis $gestion): int
    {
        $this->info($gestion->expirerLesDevisEchus().' devis expiré(s).');

        return self::SUCCESS;
    }
}
