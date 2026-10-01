<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CleApi extends Model
{
    public const PREFIXE = 'mc_';

    protected $table = 'cles_api';

    protected $fillable = ['nom', 'sha256', 'debut', 'user_id'];

    protected function casts(): array
    {
        return ['derniere_utilisation_at' => 'datetime', 'revoquee_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crée une clé ; la clé en clair est renvoyée une seule fois.
     *
     * @return array{0: CleApi, 1: string}
     */
    public static function creer(string $nom, User $user): array
    {
        $cle = self::PREFIXE.Str::random(48);

        return [self::create(['nom' => $nom, 'sha256' => hash('sha256', $cle), 'debut' => substr($cle, 0, 7), 'user_id' => $user->id]), $cle];
    }

    public static function trouver(string $cle): ?self
    {
        if (! preg_match('/^'.self::PREFIXE.'[A-Za-z0-9]{48}$/', $cle)) {
            return null;
        }

        return self::with('user')->where('sha256', hash('sha256', $cle))->whereNull('revoquee_at')->first();
    }
}
