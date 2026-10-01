<?php

namespace App\Services;

use App\Models\Rapport;
use App\Support\FichiersReglages;

/**
 * PDF du rapport d'intervention, avec ses photos.
 */
class PdfRapport
{
    public function __construct(private Pdf $pdf) {}

    public function generer(Rapport $rapport): string
    {
        $rapport->loadMissing(['client', 'chantier']);
        $logo = (string) reglage('apparence.logo');

        $mpdf = $this->pdf->nouveau('Rapport d\'intervention');
        $mpdf->WriteHTML(view('pdf.rapport', [
            'rapport' => $rapport,
            'logo' => $logo !== '' && FichiersReglages::disque()->exists($logo) ? FichiersReglages::disque()->path($logo) : null,
            'couleur' => (string) reglage('apparence.couleur_principale'),
        ])->render());

        foreach (PhotosAnnexe::pages($rapport->lesPhotos(), 'Photos de l\'intervention') as $page) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($page);
        }

        return $mpdf->Output('', 'S');
    }
}
