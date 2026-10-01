<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NouvelAppareil;
use App\Support\Journal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Reconnaît le téléphone ou l'ordinateur grâce à un jeton aléatoire gardé
 * dans un cookie chiffré. Seule son empreinte SHA-256 est en base.
 */
class DetecteurAppareil
{
    public const COOKIE = 'appareil';

    public const DUREE_MINUTES = 60 * 24 * 365 * 5;

    public function apresConnexion(User $user, Request $request): void
    {
        $jeton = $request->cookie(self::COOKIE);

        if (! is_string($jeton) || strlen($jeton) !== 64) {
            $jeton = Str::random(64);
            Cookie::queue(Cookie::make(self::COOKIE, $jeton, self::DUREE_MINUTES, httpOnly: true, sameSite: 'lax'));
        }

        $empreinte = hash('sha256', $jeton);
        $appareil = $user->appareils()->where('empreinte', $empreinte)->first();

        if ($appareil) {
            $appareil->update(['last_seen_at' => now(), 'ip_address' => $request->ip()]);

            return;
        }

        $premierAppareil = ! $user->appareils()->exists();

        $appareil = $user->appareils()->create([
            'empreinte' => $empreinte,
            'libelle' => self::libelle((string) $request->userAgent()),
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
        ]);

        if ($premierAppareil) {
            return;
        }

        Journal::ecrire('appareil.nouveau', 'Connexion depuis un nouvel appareil : '.$appareil->libelle, $appareil, $user->id);

        $destinataires = User::gerantsActifs()->get()->push($user)->unique('id');

        try {
            Notification::send($destinataires, new NouvelAppareil($user, $appareil));
        } catch (\Throwable $e) {
            // L'alerte ne doit jamais empêcher la connexion.
            Log::warning('Alerte nouvel appareil non envoyée : '.$e->getMessage());
        }
    }

    public static function libelle(string $userAgent): string
    {
        $systeme = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Appareil inconnu',
        };

        $navigateur = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'Firefox') || str_contains($userAgent, 'FxiOS') => 'Firefox',
            str_contains($userAgent, 'Chrome') || str_contains($userAgent, 'CriOS') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => null,
        };

        return $navigateur ? $systeme.' – '.$navigateur : $systeme;
    }
}
