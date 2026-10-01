<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Contrôle de sécurité de l'installation (à lancer après chaque installation ou mise à jour).
 */
class ControleSecurite extends Command
{
    protected $signature = 'app:security-check {--http : Vérifier aussi en ligne que le fichier .env est bien protégé}';

    protected $description = 'Vérifie la sécurité de l\'installation (production, https, cookies, .env, sauvegarde, gérant)';

    public function handle(Sauvegardes $sauvegardes): int
    {
        $sauvegarde = $sauvegardes->derniere('base');
        $env = app()->environmentFilePath();
        $points = [
            ['Mode production (APP_ENV=production)', app()->isProduction()],
            ['Mode débogage coupé (APP_DEBUG=false)', ! config('app.debug')],
            ['Adresse en https (APP_URL)', str_starts_with((string) config('app.url'), 'https://')],
            ['Cookies sécurisés (SESSION_SECURE_COOKIE=true)', (bool) config('session.secure')],
            ['Cookies inaccessibles au JavaScript', (bool) config('session.http_only')],
            ['Clé de chiffrement présente (APP_KEY)', (string) config('app.key') !== ''],
            ['Fichier .env hors du dossier public', ! file_exists(public_path('.env')) && dirname($env) !== public_path()],
            ['Fichier .env non lisible par les autres comptes', ! file_exists($env) || (fileperms($env) & 0o004) === 0],
            ['Sauvegarde de moins de 2 jours', $sauvegarde !== null && $sauvegarde->gt(now()->subDays(2))],
            ['Au moins un compte gérant actif', User::gerantsActifs()->exists()],
        ];

        if ($this->option('http')) {
            try {
                $statut = Http::timeout(10)->withoutRedirecting()->get(rtrim((string) config('app.url'), '/').'/.env')->status();
                $points[] = ['Fichier .env introuvable depuis Internet', $statut >= 400 && $statut !== 500];
            } catch (\Throwable) {
                $points[] = ['Fichier .env introuvable depuis Internet (site injoignable)', false];
            }
        }

        $problemes = 0;
        foreach ($points as [$libelle, $ok]) {
            $this->line(($ok ? '<info>✓</info> ' : '<error>✗</error> ').$libelle);
            $problemes += $ok ? 0 : 1;
        }

        if ($problemes) {
            $this->newLine();
            $this->error("{$problemes} point(s) à corriger.");

            return self::FAILURE;
        }

        $this->info('Tout est en ordre.');

        return self::SUCCESS;
    }
}
