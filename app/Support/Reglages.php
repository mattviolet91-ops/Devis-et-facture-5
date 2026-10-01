<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de l'entreprise : table « settings » (clé → valeur JSON),
 * valeurs par défaut dans config/entreprise.php, le tout mis en cache.
 */
class Reglages
{
    public const CLE_CACHE = 'reglages.tous';

    /** @var array<string, mixed>|null */
    private ?array $valeurs = null;

    public function get(string $cle, mixed $defaut = null): mixed
    {
        $valeurs = $this->tous();

        if (array_key_exists($cle, $valeurs)) {
            return $valeurs[$cle];
        }

        return config('entreprise.'.$cle, $defaut);
    }

    public function set(string $cle, mixed $valeur): void
    {
        Setting::updateOrCreate(['key' => $cle], ['value' => $valeur]);
        $this->vider();
    }

    public function oublier(string $cle): void
    {
        Setting::where('key', $cle)->delete();
        $this->vider();
    }

    public function vider(): void
    {
        Cache::forget(self::CLE_CACHE);
        $this->valeurs = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function tous(): array
    {
        if ($this->valeurs !== null) {
            return $this->valeurs;
        }

        if (! Schema::hasTable('settings')) {
            return [];
        }

        return $this->valeurs = Cache::rememberForever(
            self::CLE_CACHE,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
