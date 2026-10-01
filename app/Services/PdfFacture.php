<?php

namespace App\Services;

use App\Models\Facture;
use App\Support\FichiersReglages;
use App\Support\Tva;
use Illuminate\Support\Facades\Storage;

/**
 * PDF des factures et avoirs, avec les mentions obligatoires.
 */
class PdfFacture
{
    public function __construct(private Pdf $pdf) {}

    public function generer(Facture $facture): string
    {
        $mpdf = $this->pdf->nouveau($facture->libelleType().' '.$facture->reference(), $facture->statut === Facture::BROUILLON ? 'BROUILLON' : null);
        $mpdf->WriteHTML($this->html($facture));

        foreach (PhotosAnnexe::pour($facture) as $page) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($page);
        }

        return $mpdf->Output('', 'S');
    }

    public function html(Facture $facture): string
    {
        $facture->loadMissing(['client', 'chantier', 'lignes', 'origine', 'devis']);
        $logo = (string) reglage('apparence.logo');

        return view('pdf.facture', [
            'facture' => $facture,
            'detail' => $facture->detailTotaux(),
            'franchise' => Tva::estFranchise(),
            'logo' => $logo !== '' && FichiersReglages::disque()->exists($logo) ? FichiersReglages::disque()->path($logo) : null,
            'couleur' => (string) reglage('apparence.couleur_principale'),
        ])->render();
    }

    public function figer(Facture $facture): void
    {
        $contenu = $this->generer($facture);
        $chemin = 'factures/'.$facture->id.'/'.str_replace(['/', ' '], '-', $facture->reference()).'.pdf';
        Storage::disk('local')->put($chemin, $contenu);

        $facture->forceFill(['pdf_chemin' => $chemin, 'pdf_sha256' => hash('sha256', $contenu)])->save();
    }

    public function contenu(Facture $facture): string
    {
        if ($facture->pdf_chemin && Storage::disk('local')->exists($facture->pdf_chemin)) {
            return Storage::disk('local')->get($facture->pdf_chemin);
        }

        return $this->generer($facture);
    }
}
