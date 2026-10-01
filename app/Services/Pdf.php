<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * Fabrique de documents PDF (mPDF, polices embarquées DejaVu, aucun appel extérieur).
 */
class Pdf
{
    public function nouveau(string $titre, ?string $filigrane = null): Mpdf
    {
        $dossier = storage_path('framework/mpdf');
        if (! is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 18,
            'margin_footer' => 6,
            'tempDir' => $dossier,
        ]);

        $pdf->SetTitle($titre);
        $pdf->SetAuthor((string) reglage('identite.nom_commercial'));
        $pdf->SetCreator((string) reglage('identite.nom_commercial'));

        if ($filigrane) {
            $pdf->SetWatermarkText($filigrane, 0.09);
            $pdf->showWatermarkText = true;
        }

        $pied = trim(implode(' · ', array_filter([
            reglage('identite.nom_commercial'),
            reglage('identite.forme_juridique'),
            reglage('identite.siret') ? 'SIRET '.reglage('identite.siret') : null,
            reglage('identite.rcs_rm'),
        ])));
        $pdf->SetHTMLFooter('<div style="font-size:7pt;color:#666;text-align:center;">'.e($pied).' · Page {PAGENO}/{nbpg}</div>');

        return $pdf;
    }

    /**
     * Ajoute les pages d'un PDF existant (attestation d'assurance…).
     */
    public function ajouterPdf(Mpdf $pdf, string $chemin): void
    {
        if (! is_file($chemin)) {
            return;
        }

        try {
            $pages = $pdf->setSourceFile($chemin);
            for ($i = 1; $i <= $pages; $i++) {
                $pdf->AddPage();
                $page = $pdf->importPage($i);
                $pdf->useTemplate($page, 0, 0, 210);
            }
        } catch (\Throwable $e) {
            // PDF protégé ou illisible : on le signale dans le document plutôt que d'échouer.
            $pdf->AddPage();
            $pdf->WriteHTML('<p>L\'attestation d\'assurance jointe n\'a pas pu être ajoutée automatiquement. Elle est disponible sur demande.</p>');
        }
    }
}
