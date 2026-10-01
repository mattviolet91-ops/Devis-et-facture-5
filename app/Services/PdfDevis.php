<?php

namespace App\Services;

use App\Models\Devis;
use App\Support\FichiersReglages;
use App\Support\Tva;
use Illuminate\Support\Facades\Storage;

/**
 * PDF d'un devis : en-tête aux couleurs de l'entreprise, client, adresse des travaux,
 * lignes, totaux, mentions obligatoires, bloc « Bon pour accord », annexes.
 */
class PdfDevis
{
    public function __construct(private Pdf $pdf) {}

    public function generer(Devis $devis): string
    {
        $devis->loadMissing(['client', 'chantier', 'lignes']);
        $brouillon = $devis->statut === Devis::BROUILLON;

        $mpdf = $this->pdf->nouveau('Devis '.$devis->reference(), $brouillon ? 'BROUILLON' : null);

        if (reglage('documents.page_couverture')) {
            $mpdf->WriteHTML(view('pdf.couverture', $this->donnees($devis))->render());
            $mpdf->AddPage();
        }

        $mpdf->WriteHTML(view('pdf.devis', $this->donnees($devis))->render());

        // Annexes.
        if ($this->formulaireRetractation($devis)) {
            $mpdf->AddPage();
            $mpdf->WriteHTML(view('pdf.retractation', $this->donnees($devis))->render());
        }
        if (trim((string) reglage('documents.cgv')) !== '') {
            $mpdf->AddPage();
            $mpdf->WriteHTML(view('pdf.cgv', $this->donnees($devis))->render());
        }
        foreach (PhotosAnnexe::pour($devis) as $pagePhotos) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($pagePhotos);
        }
        $attestation = (string) reglage('assurance.attestation');
        if ($attestation !== '' && FichiersReglages::disque()->exists($attestation)) {
            $this->pdf->ajouterPdf($mpdf, FichiersReglages::disque()->path($attestation));
        }

        return $mpdf->Output('', 'S');
    }

    /**
     * À l'envoi : le PDF est figé (fichier enregistré) et son empreinte SHA-256 conservée.
     */
    public function figer(Devis $devis, string $suffixe = ''): void
    {
        $contenu = $this->generer($devis);
        $chemin = 'devis/'.$devis->id.'/'.str_replace(['/', ' '], '-', $devis->reference()).$suffixe.'.pdf';
        Storage::disk('local')->put($chemin, $contenu);

        $devis->forceFill([
            'pdf_chemin' => $chemin,
            'pdf_sha256' => hash('sha256', $contenu),
            'pdf_fige_at' => now(),
        ])->save();
    }

    /**
     * Contenu à servir : le PDF figé s'il existe, sinon un PDF généré à la volée.
     */
    public function contenu(Devis $devis): string
    {
        $signature = $devis->signature;
        if ($signature?->pdf_chemin && Storage::disk('local')->exists($signature->pdf_chemin)) {
            return Storage::disk('local')->get($signature->pdf_chemin);
        }

        if ($devis->pdf_chemin && Storage::disk('local')->exists($devis->pdf_chemin)) {
            return Storage::disk('local')->get($devis->pdf_chemin);
        }

        return $this->generer($devis);
    }

    /**
     * HTML de la page principale (sert aussi aux tests).
     */
    public function html(Devis $devis): string
    {
        $devis->loadMissing(['client', 'chantier', 'lignes']);

        return view('pdf.devis', $this->donnees($devis))->render();
    }

    /**
     * @return array{nom: string, date: string, image: string, ip: string}|null
     */
    private function signature(Devis $devis): ?array
    {
        $signature = $devis->statut === Devis::ACCEPTE ? $devis->signature : null;
        if (! $signature || ! Storage::disk('local')->exists($signature->image_chemin)) {
            return null;
        }

        return [
            'nom' => $signature->nom,
            'date' => $signature->signe_at->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
            'image' => Storage::disk('local')->path($signature->image_chemin),
            'ip' => (string) $signature->ip_address,
        ];
    }

    public function formulaireRetractation(Devis $devis): bool
    {
        return $devis->hors_etablissement && ! $devis->client?->estProfessionnel();
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Devis $devis): array
    {
        $logo = (string) reglage('apparence.logo');

        return [
            'devis' => $devis,
            'detail' => $devis->detailTotaux(),
            'franchise' => Tva::estFranchise(),
            'logo' => $logo !== '' && FichiersReglages::disque()->exists($logo) ? FichiersReglages::disque()->path($logo) : null,
            'couleur' => (string) reglage('apparence.couleur_principale'),
            'retractation' => $this->formulaireRetractation($devis),
            'signature' => $this->signature($devis),
        ];
    }
}
