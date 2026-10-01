<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que la base indiquée dans .env est 100 % vide avant une installation.
 * Une nouvelle entreprise ne part JAMAIS d'une base existante.
 */
class BaseNeuve extends Command
{
    protected $signature = 'app:base-neuve';

    protected $description = 'Vérifie que la base de données est vide (installation d\'une nouvelle entreprise)';

    public function handle(): int
    {
        try {
            $tables = Schema::getTables();
        } catch (\Throwable $e) {
            $this->error('Base injoignable : vérifiez DB_DATABASE, DB_USERNAME et DB_PASSWORD dans .env.');

            return self::FAILURE;
        }

        if (count($tables) > 0) {
            $this->error('Refusé : la base contient déjà '.count($tables).' table(s). Une nouvelle entreprise doit partir d\'une base neuve et vide.');

            return self::FAILURE;
        }

        $this->info('Base vide : installation possible.');

        return self::SUCCESS;
    }
}
