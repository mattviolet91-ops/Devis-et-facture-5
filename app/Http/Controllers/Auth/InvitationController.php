<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\View\View;

/**
 * Lien d'invitation : la personne choisit son mot de passe puis est connectée.
 */
class InvitationController extends Controller
{
    public function create(string $jeton): View
    {
        $user = $this->trouver($jeton);

        return view('auth.invitation', ['jeton' => $jeton, 'email' => $user?->email, 'valide' => (bool) $user]);
    }

    public function store(Request $request, string $jeton): RedirectResponse
    {
        $user = $this->trouver($jeton);
        abort_unless($user, 404);

        $request->validate(['password' => ['required', 'confirmed', RegleMotDePasse::min(10)]]);

        $user->forceFill([
            'password' => $request->input('password'),
            'invitation_sha256' => null,
            'invitation_expire_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        Journal::ecrire('compte.activation', 'Invitation acceptée : '.$user->email, $user, $user->id);

        return redirect()->route('accueil')->with('statut', 'Bienvenue ! Votre compte est prêt.');
    }

    private function trouver(string $jeton): ?User
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $jeton)) {
            return null;
        }

        return User::where('invitation_sha256', hash('sha256', $jeton))
            ->where('invitation_expire_at', '>', now())
            ->where('is_active', true)
            ->first();
    }
}
