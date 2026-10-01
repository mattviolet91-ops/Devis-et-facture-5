<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Services\LectureDemandes;
use App\Services\Photos;
use App\Support\Configuration;
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
        $this->verifierOuvert();

        return view('demande.formulaire', ['horodatage' => Crypt::encryptString((string) now()->timestamp)]);
    }

    public function store(Request $request, Photos $photos): RedirectResponse
    {
        $this->verifierOuvert();

        // Champ piège : invisible pour une personne, rempli par les robots.
        if ($request->filled('site_web') || ! $this->delaiRespecte((string) $request->input('horodatage'))) {
            Log::info('Demande de devis ignorée (robot probable).');

            return redirect()->route('demande.merci');
        }

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'telephone' => ['required_without:email', 'nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'email' => ['required_without:telephone', 'nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:200'],
            'code_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'ville' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp'],
            'accord' => ['accepted'],
        ], [
            'telephone.required_without' => 'Indiquez un téléphone ou un email pour qu\'on vous recontacte.',
            'email.required_without' => 'Indiquez un téléphone ou un email pour qu\'on vous recontacte.',
            'accord.accepted' => 'Cochez la case pour qu\'on puisse vous recontacter.',
            'photos.max' => '3 photos au plus.',
            'photos.*.mimetypes' => 'Les photos doivent être en JPEG, PNG ou WebP.',
            'photos.*.max' => 'Une photo ne doit pas dépasser 15 Mo.',
            'code_postal.regex' => 'Le code postal fait 5 chiffres.',
        ], ['message' => 'description des travaux']);

        $rangees = [];
        foreach ((array) $request->file('photos', []) as $fichier) {
            try {
                $rangees[] = $photos->ranger($fichier, 'demandes/'.now()->format('Y-m'));
            } catch (\RuntimeException) {
                // Photo illisible : la demande passe quand même.
            }
        }

        Demande::create([
            'source' => 'formulaire',
            'nom' => $donnees['nom'],
            'telephone' => Telephone::normaliser($donnees['telephone'] ?? null),
            'email' => isset($donnees['email']) ? mb_strtolower($donnees['email']) : null,
            'adresse' => $donnees['adresse'] ?? null,
            'code_postal' => $donnees['code_postal'] ?? null,
            'ville' => $donnees['ville'] ?? null,
            'message' => $donnees['message'],
            'photos' => $rangees ?: null,
            'recue_at' => now(),
        ]);
        LectureDemandes::alerter(1);

        return redirect()->route('demande.merci');
    }

    public function merci(): View
    {
        return view('demande.merci');
    }

    /**
     * Formulaire activé, et configuration de l'entreprise terminée (comme les liens clients).
     */
    private function verifierOuvert(): void
    {
        abort_unless(reglage('suivi.formulaire_actif'), 404);
        if (! Configuration::accesClientsActif()) {
            abort(response()->view('client.indisponible', [], 503));
        }
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
