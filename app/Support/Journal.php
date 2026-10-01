<?php

namespace App\Support;

use App\Models\Activite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Journal d'activité : qui a fait quoi, et quand.
 * Ne jamais y écrire de mot de passe, de jeton ni de clé.
 */
class Journal
{
    public static function ecrire(string $action, string $description, ?Model $sujet = null, ?int $userId = null): Activite
    {
        return Activite::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => Str::limit($description, 490),
            'sujet_type' => $sujet?->getMorphClass(),
            'sujet_id' => $sujet?->getKey(),
            'ip_address' => request()?->ip(),
        ]);
    }
}
