<?php

namespace App\Models\Concerns;

use App\Services\CalculDevis;
use App\Support\Tva;
use Illuminate\Database\Eloquent\Model;

/**
 * Totaux d'un document à lignes (devis, facture, avoir), calculés en centimes.
 */
trait AvecLignes
{
    public function recalculer(): void
    {
        $lignes = $this->lignes()->get();
        $calcul = $this->calcul($lignes->all());

        foreach ($lignes->values() as $i => $ligne) {
            $ligne->updateQuietly(['total_ht' => $calcul['lignes'][$i] ?? 0]);
        }

        $this->forceFill([
            'total_ht' => $calcul['total_ht'],
            'total_remise' => $calcul['remise'],
            'total_tva' => $calcul['total_tva'],
            'total_ttc' => $calcul['total_ttc'],
            'total_options_ht' => $calcul['total_options_ht'],
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function detailTotaux(): array
    {
        return $this->calcul($this->lignes->all());
    }

    /**
     * @param  list<Model>  $lignes
     * @return array<string, mixed>
     */
    private function calcul(array $lignes): array
    {
        return app(CalculDevis::class)->calculer(
            array_map(fn (Model $l) => $l->only(['type', 'quantite', 'prix_unitaire_ht', 'taux_tva', 'option']), array_values($lignes)),
            $this->remise_type,
            (int) $this->remise_valeur,
            Tva::estFranchise(),
        );
    }
}
