<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Visionneuse PDF intégrée (pdf.js, sans appel extérieur).
 * Elle n'ouvre que des documents de l'application ; chaque document reste
 * protégé par ses propres droits d'accès (le compte doit pouvoir l'ouvrir).
 */
class VisionneuseController extends Controller
{
    /** Adresses autorisées (chemins relatifs de l'application). */
    public const AUTORISES = [
        '#^/devis/\d+/pdf$#',
        '#^/factures/\d+/pdf$#',
        '#^/avoirs/\d+/pdf$#',
        '#^/rapports/\d+/pdf$#',
        '#^/fichiers/\d+$#',
        '#^/reglages/assurance/attestation$#',
        '#^/configuration/devis-exemple\.pdf$#',
    ];

    public static function autorise(string $chemin): bool
    {
        foreach (self::AUTORISES as $motif) {
            if (preg_match($motif, $chemin)) {
                return true;
            }
        }

        return false;
    }

    public function show(Request $request): View
    {
        $chemin = (string) $request->query('f');
        abort_unless(self::autorise($chemin), 404);

        return view('visionneuse', [
            'document' => $chemin,
            'titre' => (string) $request->query('titre', 'Document'),
            'retour' => url()->previous() !== url()->current() ? url()->previous() : route('accueil'),
        ]);
    }
}
