<?php

namespace App\Services;

use App\Models\DemandeModification;
use App\Models\Devis;
use App\Models\Signature;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Support\Alertes;
use App\Support\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Signature d'un devis par le client (en ligne ou sur place), refus, demande de modification.
 * Le gérant est prévenu à chaque fois.
 */
class SignatureDevis
{
    public function __construct(private GestionDevis $gestion, private PdfDevis $pdf) {}

    public function signer(Devis $devis, string $nom, string $image, ?string $ip, ?string $userAgent, bool $surPlace, bool $executionImmediate = false): Signature
    {
        if (! $devis->peutEtreSigne()) {
            throw ValidationException::withMessages(['signature' => 'Ce devis ne peut plus être signé. Contactez l\'entreprise.']);
        }

        $png = $this->lireImage($image);

        $signature = DB::transaction(function () use ($devis, $nom, $png, $ip, $userAgent, $surPlace, $executionImmediate) {
            $chemin = 'signatures/'.$devis->id.'/signature-'.now()->format('YmdHis').'.png';
            Storage::disk('local')->put($chemin, $png);

            $signature = $devis->signature()->create([
                'nom' => $nom,
                'image_chemin' => $chemin,
                'ip_address' => $ip,
                'user_agent' => mb_substr((string) $userAgent, 0, 255),
                'sur_place' => $surPlace,
                'execution_immediate' => $executionImmediate,
                'signe_at' => now(),
            ]);

            $this->gestion->accepter($devis);

            // PDF signé : nouvelle version figée avec la signature, et son empreinte.
            $devis->refresh()->load(['client', 'chantier', 'lignes', 'signature']);
            $contenu = $this->pdf->generer($devis);
            $cheminPdf = 'devis/'.$devis->id.'/'.str_replace(['/', ' '], '-', $devis->reference()).'-signe.pdf';
            Storage::disk('local')->put($cheminPdf, $contenu);
            $signature->update(['pdf_chemin' => $cheminPdf, 'pdf_sha256' => hash('sha256', $contenu)]);

            return $signature;
        });

        Journal::ecrire('devis.signe', 'Devis '.$devis->reference().' signé par '.$nom.($surPlace ? ' (sur place)' : ' (en ligne)'), $devis);
        $this->prevenir($devis, 'Devis signé', 'Le devis '.$devis->reference().' ('.$devis->client->nomComplet().') a été signé par '.$nom.'.');

        return $signature;
    }

    public function refuser(Devis $devis, ?string $motif): void
    {
        if (! $devis->peutEtreSigne()) {
            throw ValidationException::withMessages(['refus' => 'Ce devis n\'attend plus de réponse.']);
        }

        $this->gestion->refuser($devis, $motif);
        $this->prevenir($devis, 'Devis refusé', 'Le client '.$devis->client->nomComplet().' a refusé le devis '.$devis->reference().($motif ? ' : « '.$motif.' »' : '.'));
    }

    public function demanderModification(Devis $devis, string $message, ?string $ip): DemandeModification
    {
        $demande = $devis->demandesModification()->create(['message' => $message, 'ip_address' => $ip]);

        Journal::ecrire('devis.modification_demandee', 'Modification demandée sur le devis '.$devis->reference(), $devis);
        $this->prevenir($devis, 'Modification demandée', $devis->client->nomComplet().' demande une modification du devis '.$devis->reference().' : « '.$message.' »');

        return $demande;
    }

    private function prevenir(Devis $devis, string $titre, string $texte): void
    {
        Alertes::envoyer(User::gerantsActifs()->get(), new AlerteDocument($titre, $texte, route('devis.show', $devis)));
    }

    /**
     * Image de la signature dessinée au doigt (PNG, en « data URL »).
     */
    private function lireImage(string $image): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $image, $m)) {
            throw ValidationException::withMessages(['signature' => 'Signez dans le cadre avec le doigt.']);
        }

        $png = base64_decode($m[1], true);
        $infos = $png !== false && strlen($png) <= 300_000 ? @getimagesizefromstring($png) : false;

        if (! $infos || $infos[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['signature' => 'La signature n\'a pas pu être lue. Recommencez.']);
        }

        return $png;
    }
}
