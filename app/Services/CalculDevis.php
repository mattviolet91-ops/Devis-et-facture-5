<?php

namespace App\Services;

use App\Support\Montant;
use App\Support\Quantite;

/**
 * Calcul exact des totaux d'un devis ou d'une facture, en centimes.
 *
 * - Les lignes « option » ne comptent pas dans le total (affichées à part).
 * - La remise globale s'applique sur le HT, répartie entre les taux de TVA
 *   au prorata (le reste d'arrondi va sur le dernier taux).
 * - La TVA est calculée par taux sur la base remisée, arrondie au centime.
 */
class CalculDevis
{
    /**
     * @param  list<array{type?: string, quantite?: int, prix_unitaire_ht?: int, taux_tva?: int, option?: bool}>  $lignes
     * @return array{lignes: list<int>, sections: array<int, int>, total_brut_ht: int, remise: int, total_ht: int, tva: array<int, array{base: int, montant: int}>, total_tva: int, total_ttc: int, total_options_ht: int}
     */
    public function calculer(array $lignes, ?string $remiseType = null, int $remiseValeur = 0, bool $franchise = false): array
    {
        $totauxLignes = [];
        $sections = [];
        $sectionCourante = null;
        $parTaux = [];
        $brut = 0;
        $options = 0;

        foreach ($lignes as $i => $ligne) {
            $type = $ligne['type'] ?? 'ligne';

            if ($type === 'section') {
                $sectionCourante = $i;
                $sections[$i] = 0;
                $totauxLignes[$i] = 0;

                continue;
            }
            if ($type !== 'ligne') {
                $totauxLignes[$i] = 0;

                continue;
            }

            $total = Quantite::total((int) ($ligne['quantite'] ?? 0), (int) ($ligne['prix_unitaire_ht'] ?? 0));
            $totauxLignes[$i] = $total;

            if (! empty($ligne['option'])) {
                $options += $total;

                continue;
            }

            $taux = $franchise ? 0 : (int) ($ligne['taux_tva'] ?? 0);
            $parTaux[$taux] = ($parTaux[$taux] ?? 0) + $total;
            $brut += $total;
            if ($sectionCourante !== null) {
                $sections[$sectionCourante] += $total;
            }
        }

        $remise = $this->remise($brut, $remiseType, $remiseValeur);

        // Répartition de la remise par taux.
        ksort($parTaux);
        $tva = [];
        $resteRemise = $remise;
        $taux = array_keys($parTaux);
        foreach ($taux as $index => $t) {
            $part = $index === count($taux) - 1 ? $resteRemise : ($brut > 0 ? intdiv($parTaux[$t] * $remise + intdiv($brut, 2), $brut) : 0);
            $resteRemise -= $part;
            $base = $parTaux[$t] - $part;
            $tva[$t] = ['base' => $base, 'montant' => $franchise ? 0 : Montant::tva($base, $t)];
        }

        $totalHt = $brut - $remise;
        $totalTva = array_sum(array_column($tva, 'montant'));

        return [
            'lignes' => $totauxLignes,
            'sections' => $sections,
            'total_brut_ht' => $brut,
            'remise' => $remise,
            'total_ht' => $totalHt,
            'tva' => $tva,
            'total_tva' => $totalTva,
            'total_ttc' => $totalHt + $totalTva,
            'total_options_ht' => $options,
        ];
    }

    private function remise(int $brut, ?string $type, int $valeur): int
    {
        if ($brut <= 0 || $valeur <= 0) {
            return 0;
        }

        $remise = match ($type) {
            'pourcentage' => intdiv($brut * min($valeur, 10000) + 5000, 10000),
            'montant' => $valeur,
            default => 0,
        };

        return min($remise, $brut);
    }
}
