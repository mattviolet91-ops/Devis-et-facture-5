<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

        return config('entreprise.'.$cle, config('reglages.'.$cle, $defaut));
    }

    /**
     * Enregistre un secret (mot de passe email, clé de paiement…) chiffré avec APP_KEY.
     * Une valeur vide efface le secret.
     */
    public function setSecret(string $cle, ?string $valeur): void
    {
        if ($valeur === null || $valeur === '') {
            $this->oublier($cle);

            return;
        }

        $this->set($cle, ['chiffre' => Crypt::encryptString($valeur)]);
    }

    /**
     * Lit un secret en clair, pour l'utiliser (jamais pour l'afficher).
     */
    public function getSecret(string $cle): ?string
    {
        $valeur = $this->tous()[$cle] ?? null;

        if (! is_array($valeur) || ! isset($valeur['chiffre'])) {
            return null;
        }

        try {
            return Crypt::decryptString($valeur['chiffre']);
        } catch (DecryptException) {
            return null;
        }
    }

    public function aSecret(string $cle): bool
    {
        return $this->getSecret($cle) !== null;
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

        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return $this->valeurs = Cache::rememberForever(
                self::CLE_CACHE,
                fn () => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (\Throwable) {
            // Base pas encore créée (installation en cours) : valeurs par défaut.
            return [];
        }
    }
}
