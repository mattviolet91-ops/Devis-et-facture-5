<?php

namespace App\Http\Controllers;

use App\Models\Devis;
use App\Models\LienClient;
use App\Services\PdfDevis;
use App\Services\SignatureDevis;
use App\Support\Configuration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pages destinées aux clients, ouvertes par un lien personnel (sans compte).
 * Coupées tant que le menu de configuration n'est pas terminé.
 */
class EspaceClientController extends Controller
{
    public function __construct(private SignatureDevis $signatures) {}

    public function show(string $jeton): View|Response
    {
        [$lien, $document] = $this->ouvrir($jeton);

        return match (true) {
            $document instanceof Devis => view('client.devis', [
                'lien' => $lien,
                'devis' => $document->load(['client', 'chantier', 'lignes', 'signature']),
                'detail' => $document->detailTotaux(),
            ]),
            default => abort(404),
        };
    }

    public function pdf(string $jeton, PdfDevis $pdf): Response
    {
        [, $document] = $this->ouvrir($jeton);
        abort_unless($document instanceof Devis, 404);

        return response($pdf->contenu($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devis-'.Str::slug($document->reference()).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function signer(Request $request, string $jeton): RedirectResponse
    {
        [, $devis] = $this->ouvrirDevis($jeton);
        $donnees = $this->validerSignature($request);

        $this->signatures->signer($devis, $donnees['nom'], $donnees['signature'], $request->ip(), $request->userAgent(), false, $request->boolean('execution_immediate'));

        return redirect()->route('client.document', $jeton)->with('statut', 'Merci ! Votre devis est signé. Nous vous recontactons rapidement pour planifier les travaux.');
    }

    public function refuser(Request $request, string $jeton): RedirectResponse
    {
        [, $devis] = $this->ouvrirDevis($jeton);
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:1000']])['motif'] ?? null;

        $this->signatures->refuser($devis, $motif);

        return redirect()->route('client.document', $jeton)->with('statut', 'Votre réponse a bien été transmise. Merci de nous avoir consultés.');
    }

    public function modification(Request $request, string $jeton): RedirectResponse
    {
        [, $devis] = $this->ouvrirDevis($jeton);
        $message = $request->validate(['message' => ['required', 'string', 'max:2000']], ['message.required' => 'Écrivez ce que vous souhaitez modifier.'])['message'];

        $this->signatures->demanderModification($devis, $message, $request->ip());

        return redirect()->route('client.document', $jeton)->with('statut', 'Votre demande a bien été transmise. Nous revenons vers vous avec un devis modifié.');
    }

    /**
     * @return array{nom: string, signature: string}
     */
    public static function validerSignature(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'max:400000'],
            'accord' => ['accepted'],
        ], [
            'nom.required' => 'Indiquez votre nom.',
            'signature.required' => 'Signez dans le cadre avec le doigt.',
            'accord.accepted' => 'Cochez la case « Bon pour accord » pour signer.',
        ]);
    }

    /**
     * @return array{LienClient, Model}
     */
    private function ouvrir(string $jeton): array
    {
        if (! Configuration::accesClientsActif()) {
            abort(response()->view('client.indisponible', [], 503));
        }

        $lien = LienClient::trouver($jeton);
        abort_unless($lien && $lien->document, 404);

        $lien->forceFill(['dernier_acces_at' => now(), 'acces' => $lien->acces + 1])->save();

        return [$lien, $lien->document];
    }

    /**
     * @return array{LienClient, Devis}
     */
    private function ouvrirDevis(string $jeton): array
    {
        [$lien, $document] = $this->ouvrir($jeton);
        abort_unless($document instanceof Devis, 404);

        return [$lien, $document];
    }
}
