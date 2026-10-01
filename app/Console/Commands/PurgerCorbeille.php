<?php

namespace App\Console\Commands;

use App\Support\Corbeille;
use App\Support\Journal;
use Illuminate\Console\Command;

class PurgerCorbeille extends Command
{
    protected $signature = 'app:purger-corbeille';

    protected $description = 'Supprime définitivement ce qui est dans la corbeille depuis plus de 30 jours';

    public function handle(): int
    {
        $total = Corbeille::purger();

        if ($total > 0) {
            Journal::ecrire('corbeille.purge', "{$total} élément(s) supprimé(s) définitivement de la corbeille (plus de 30 jours).");
        }

        $this->info("{$total} élément(s) supprimé(s) définitivement.");

        return self::SUCCESS;
    }
}
