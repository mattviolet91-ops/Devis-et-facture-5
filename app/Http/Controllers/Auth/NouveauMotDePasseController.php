<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\View\View;

class NouveauMotDePasseController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.nouveau-mot-de-passe', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::min(10)],
        ]);

        $statut = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $motDePasse) {
                $user->forceFill([
                    'password' => $motDePasse,
                    'remember_token' => Str::random(60),
                ])->save();

                Journal::ecrire('mot_de_passe.change', 'Nouveau mot de passe choisi pour '.$user->email, null, $user->id);
                event(new PasswordReset($user));
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($statut)]);
        }

        return redirect()->route('login')->with('statut', 'Votre mot de passe a été changé. Vous pouvez vous connecter.');
    }
}
