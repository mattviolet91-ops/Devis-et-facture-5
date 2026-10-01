<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\Frais;
use App\Services\Photos;
use App\Support\Journal;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Frais d'un chantier sur la facture (gérant seulement).
 */
class FraisController extends Controller
{
    public function store(Request $request, Facture $facture, Photos $photos): RedirectResponse
    {
        abort_if($facture->estAvoir(), 404);
        $request->merge(['montant_frais' => Montant::lire((string) $request->input('montant_frais'))]);
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'categorie' => ['required', Rule::in(array_keys(Frais::CATEGORIES))],
            'montant_frais' => ['required', 'integer', 'min:1', 'max:100000000'],
            'date_frais' => ['required', 'date_format:Y-m-d'],
            'ticket' => ['nullable', 'file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ], [
            'montant_frais.required' => 'Indiquez le montant TTC (par exemple 125,40).',
            'montant_frais.integer' => 'Indiquez le montant TTC (par exemple 125,40).',
            'ticket.mimetypes' => 'Le ticket doit être une photo ou un PDF.',
        ], ['libelle' => 'libellé', 'date_frais' => 'date', 'montant_frais' => 'montant']);

        $justificatif = null;
        if ($fichier = $request->file('ticket')) {
            $justificatif = $fichier->getMimeType() === 'application/pdf'
                ? $fichier->store('frais/'.$facture->id, 'local')
                : $photos->ranger($fichier, 'frais/'.$facture->id)['chemin'];
        }

        $frais = $facture->frais()->create([
            'libelle' => $donnees['libelle'],
            'categorie' => $donnees['categorie'],
            'montant_ttc' => $donnees['montant_frais'],
            'date_frais' => $donnees['date_frais'],
            'justificatif' => $justificatif,
            'user_id' => $request->user()->id,
        ]);
        Journal::ecrire('frais.ajout', 'Frais ajouté sur '.$facture->reference().' : '.$frais->libelle.' ('.Montant::formater($frais->montant_ttc).')', $facture);

        return redirect()->to(route('factures.show', $facture).'#frais')->with('statut', 'Frais ajouté.');
    }

    public function ticket(Frais $frais): StreamedResponse
    {
        abort_unless($frais->justificatif && Storage::disk('local')->exists($frais->justificatif), 404);
        $pdf = str_ends_with($frais->justificatif, '.pdf');

        return Storage::disk('local')->response($frais->justificatif, 'ticket-'.$frais->id.($pdf ? '.pdf' : '.jpg'), [
            'Content-Type' => $pdf ? 'application/pdf' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function destroy(Frais $frais): RedirectResponse
    {
        $facture = $frais->facture;
        $frais->delete();

        return redirect()->to(route('factures.show', $facture).'#frais')->with('statut', 'Frais supprimé.');
    }
}
