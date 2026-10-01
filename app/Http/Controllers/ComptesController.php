<?php

namespace App\Http\Controllers;

use App\Mail\Invitation;
use App\Models\User;
use App\Services\ConfigurationEmail;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Comptes (gérant) : inviter, désactiver, changer le rôle.
 */
class ComptesController extends Controller
{
    public const VALIDITE_JOURS = 7;

    public function index(): View
    {
        return view('comptes.index', ['comptes' => User::orderByDesc('is_active')->orderBy('role')->orderBy('email')->get()]);
    }

    public function inviter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([User::ROLE_GERANT, User::ROLE_COMMERCIAL])],
        ], ['email.unique' => 'Un compte existe déjà avec cet email.']);

        $user = User::create([
            'email' => mb_strtolower($donnees['email']),
            'password' => Str::password(40),
            'role' => $donnees['role'],
            'is_active' => true,
        ]);
        Journal::ecrire('compte.invitation', 'Invitation envoyée à '.$user->email.' ('.$user->libelleRole().')', $user);

        return $this->envoyer($user);
    }

    public function renvoyer(User $compte): RedirectResponse
    {
        abort_unless($compte->invitationEnAttente(), 404);

        return $this->envoyer($compte);
    }

    public function desactiver(Request $request, User $compte): RedirectResponse
    {
        if ($compte->is($request->user())) {
            return back()->with('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        // Effet immédiat : les sessions ouvertes sont coupées à la page suivante.
        $compte->forceFill(['is_active' => false, 'desactive_at' => now(), 'remember_token' => Str::random(60), 'invitation_sha256' => null])->save();
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table'))->where('user_id', $compte->id)->delete();
        }
        Journal::ecrire('compte.desactivation', 'Compte désactivé : '.$compte->email, $compte);

        return back()->with('statut', 'Compte désactivé : '.$compte->email.'. Il est déconnecté tout de suite.');
    }

    public function reactiver(User $compte): RedirectResponse
    {
        $compte->forceFill(['is_active' => true, 'desactive_at' => null])->save();
        Journal::ecrire('compte.reactivation', 'Compte réactivé : '.$compte->email, $compte);

        return back()->with('statut', 'Compte réactivé.');
    }

    public function role(Request $request, User $compte): RedirectResponse
    {
        $donnees = $request->validate(['role' => ['required', Rule::in([User::ROLE_GERANT, User::ROLE_COMMERCIAL])]]);
        if ($compte->is($request->user())) {
            return back()->with('erreur', 'Vous ne pouvez pas changer votre propre rôle.');
        }

        $compte->forceFill(['role' => $donnees['role']])->save();
        Journal::ecrire('compte.role', 'Rôle de '.$compte->email.' : '.$compte->libelleRole(), $compte);

        return back()->with('statut', 'Rôle modifié.');
    }

    private function envoyer(User $user): RedirectResponse
    {
        $jeton = Str::random(48);
        $user->forceFill(['invitation_sha256' => hash('sha256', $jeton), 'invitation_expire_at' => now()->addDays(self::VALIDITE_JOURS)])->save();
        $lien = route('invitation.create', $jeton);

        try {
            app(ConfigurationEmail::class)->appliquer();
            Mail::to($user->email)->send(new Invitation($lien, $user->libelleRole()));
            $envoye = true;
        } catch (\Throwable $e) {
            Log::warning('Invitation non envoyée : '.class_basename($e));
            $envoye = false;
        }

        return redirect()->route('comptes')
            ->with('statut', $envoye ? 'Invitation envoyée à '.$user->email.'.' : 'L\'email n\'est pas parti (Réglages → Emails). Transmettez ce lien vous-même, il n\'est affiché qu\'une fois.')
            ->with('lien_invitation', $envoye ? null : $lien);
    }
}
