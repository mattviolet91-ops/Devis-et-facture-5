<?php

namespace App\Services;

use App\Support\FichiersReglages;
use App\Support\Metiers;
use App\Support\Montant;
use App\Support\Tva;
use Mpdf\Mpdf;

/**
 * Devis d'EXEMPLE (client et quantités fictifs) pour vérifier la mise en page
 * à la fin du menu de configuration. La vraie mise en page des devis arrive
 * avec l'étape PDF.
 */
class PdfExemple
{
    public function generer(): string
    {
        $html = $this->html();

        if (! is_dir(storage_path('framework/mpdf'))) {
            mkdir(storage_path('framework/mpdf'), 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 20,
            'tempDir' => storage_path('framework/mpdf'),
        ]);
        $pdf->SetTitle('Devis d\'exemple');
        $pdf->SetWatermarkText('EXEMPLE', 0.08);
        $pdf->showWatermarkText = true;
        $pdf->SetHTMLFooter('<div class="pied">'.e((string) reglage('identite.nom_commercial')).' · SIRET '.e((string) reglage('identite.siret')).' · Page {PAGENO}/{nbpg}</div>');
        $pdf->WriteHTML($html);

        return $pdf->Output('', 'S');
    }

    /**
     * HTML du devis d'exemple (avant conversion en PDF).
     */
    public function html(): string
    {
        $franchise = Tva::estFranchise();
        $taux = $franchise ? 0 : (int) reglage('tva.taux_defaut');
        $catalogue = Metiers::metier((string) reglage('entreprise.metier'))['catalogue'] ?? [];

        // Lignes fictives : quantités et prix d'illustration uniquement.
        $exemples = [[100, 1500], [3, 4500], [1, 15000]];
        $lignes = [];
        $totalHt = 0;
        foreach (array_slice($catalogue, 0, 3) ?: [['nom' => 'Prestation', 'unite' => 'u']] as $i => $prestation) {
            [$quantite, $prix] = $exemples[$i] ?? [1, 10000];
            $total = $quantite * $prix;
            $totalHt += $total;
            $lignes[] = [
                'designation' => $prestation['nom'],
                'quantite' => $quantite,
                'unite' => $prestation['unite'],
                'prix' => Montant::formater($prix),
                'total' => Montant::formater($total),
            ];
        }

        $tva = Montant::tva($totalHt, $taux);
        $logo = (string) reglage('apparence.logo');

        return view('pdf.exemple', [
            'lignes' => $lignes,
            'franchise' => $franchise,
            'taux' => Tva::formater($taux),
            'totalHt' => Montant::formater($totalHt),
            'totalTva' => Montant::formater($tva),
            'totalTtc' => Montant::formater($totalHt + $tva),
            'acompte' => Montant::formater(intdiv(($totalHt + $tva) * (int) reglage('documents.acompte_pourcentage'), 100)),
            'logo' => $logo !== '' && FichiersReglages::disque()->exists($logo) ? FichiersReglages::disque()->path($logo) : null,
            'numero' => app(Numerotation::class)->prochain('devis'),
        ])->render();

    }
}
