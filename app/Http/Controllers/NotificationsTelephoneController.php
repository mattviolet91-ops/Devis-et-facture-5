<?php

namespace App\Http\Controllers;

use App\Models\AbonnementPush;
use App\Services\DetecteurAppareil;
use App\Services\NotificationsTelephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationsTelephoneController extends Controller
{
    public function abonner(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        AbonnementPush::updateOrCreate(['endpoint' => $donnees['endpoint']], [
            'user_id' => $request->user()->id,
            'cle_p256dh' => $donnees['keys']['p256dh'],
            'cle_auth' => $donnees['keys']['auth'],
            'appareil' => DetecteurAppareil::libelle((string) $request->userAgent()),
        ]);

        return response()->json(['ok' => true]);
    }

    public function desabonner(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        AbonnementPush::where('user_id', $request->user()->id)->where('endpoint', $request->input('endpoint'))->delete();

        return response()->json(['ok' => true]);
    }

    public function essai(Request $request, NotificationsTelephone $telephone): RedirectResponse
    {
        $envoyees = $telephone->envoyer($request->user(), 'Essai réussi', 'Les notifications arrivent bien sur ce téléphone.', route('plus'));

        return back()->with($envoyees ? 'statut' : 'erreur', $envoyees ? 'Notification d\'essai envoyée.' : 'Aucun téléphone n\'a reçu la notification. Activez-les d\'abord sur ce téléphone.');
    }
}
