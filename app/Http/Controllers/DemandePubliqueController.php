<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Services\LectureDemandes;
use App\Support\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Formulaire public « Demander un devis », à mettre en lien sur le site de l'entreprise.
 * Protections : champ piège invisible, délai minimum de saisie, nombre d'envois limité.
 */
class DemandePubliqueController extends Controller
{
    /** Un humain met au moins quelques secondes à remplir le formulaire. */
    public const DELAI_MINIMUM = 3;

    public function create(): View
    {
        abort_unless(reglage('suivi.formulaire_actif'), 404);

        return view('demande.formulaire', ['horodatage' => Crypt::encryptString((string) now()->timestamp)]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(reglage('suivi.formulaire_actif'), 404);

        // Champ piège : invisible pour une personne, rempli par les robots.
        if ($request->filled('site_web') || ! $this->delaiRespecte((string) $request->input('horodatage'))) {
            Log::info('Demande de devis ignorée (robot probable).');

            return redirect()->route('demande.merci');
        }

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'telephone' => ['required_without:email', 'nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'email' => ['required_without:telephone', 'nullable', 'email', 'max:150'],
            'ville' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
            'accord' => ['accepted'],
        ], [
            'telephone.required_without' => 'Indiquez un téléphone ou un email pour qu\'on vous recontacte.',
            'email.required_without' => 'Indiquez un téléphone ou un email pour qu\'on vous recontacte.',
            'accord.accepted' => 'Cochez la case pour qu\'on puisse vous recontacter.',
        ], ['message' => 'description des travaux']);

        Demande::create([
            'source' => 'formulaire',
            'nom' => $donnees['nom'],
            'telephone' => Telephone::normaliser($donnees['telephone'] ?? null),
            'email' => isset($donnees['email']) ? mb_strtolower($donnees['email']) : null,
            'ville' => $donnees['ville'] ?? null,
            'message' => $donnees['message'],
            'recue_at' => now(),
        ]);
        LectureDemandes::alerter(1);

        return redirect()->route('demande.merci');
    }

    public function merci(): View
    {
        return view('demande.merci');
    }

    private function delaiRespecte(string $horodatage): bool
    {
        try {
            $debut = (int) Crypt::decryptString($horodatage);
        } catch (\Throwable) {
            return false;
        }

        return now()->timestamp - $debut >= self::DELAI_MINIMUM && now()->timestamp - $debut < 86400;
    }
}
