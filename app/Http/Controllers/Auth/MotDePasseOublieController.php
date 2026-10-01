<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class MotDePasseOublieController extends Controller
{
    public function create(): View
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $statut = Password::sendResetLink($request->only('email'));

        if ($statut === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => __($statut)]);
        }

        // Même message que l'email existe ou non : on ne révèle pas les comptes.
        return back()->with('statut', 'Si un compte existe avec cet email, un lien vient de vous être envoyé. Pensez à regarder dans les indésirables.');
    }
}
