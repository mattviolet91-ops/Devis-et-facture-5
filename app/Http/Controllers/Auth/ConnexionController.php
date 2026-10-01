<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DetecteurAppareil;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public const ESSAIS_MAX = 5;

    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(Request $request, DetecteurAppareil $detecteur): RedirectResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $cle = $this->cleLimite($request);

        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_MAX)) {
            $secondes = RateLimiter::availableIn($cle);

            throw ValidationException::withMessages([
                'email' => "Trop d'essais. Réessayez dans {$secondes} secondes.",
            ])->status(429);
        }

        $connecte = Auth::attempt(
            ['email' => $donnees['email'], 'password' => $donnees['password'], 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $connecte) {
            RateLimiter::hit($cle, 60);

            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        Journal::ecrire('connexion', 'Connexion de '.$user->email);
        $detecteur->apresConnexion($user, $request);

        return redirect()->intended(route('accueil'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = Auth::user()) {
            Journal::ecrire('deconnexion', 'Déconnexion de '.$user->email);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function cleLimite(Request $request): string
    {
        return 'connexion|'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }
}
