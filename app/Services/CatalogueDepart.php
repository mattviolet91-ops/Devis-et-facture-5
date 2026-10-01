<?php

namespace App\Services;

use App\Models\Prestation;
use App\Support\Metiers;
use App\Support\Reglages;

/**
 * Charge le catalogue de départ du métier choisi au premier lancement.
 * Les prix restent vides : chaque entreprise saisit ses propres tarifs.
 */
class CatalogueDepart
{
    public function __construct(private Reglages $reglages) {}

    /**
     * @param  array<string, int>  $prix  Prix fictifs (démonstration uniquement), par nom de prestation.
     */
    public function charger(?string $metier = null, array $prix = []): int
    {
        $metier ??= (string) reglage('catalogue.depart_a_charger');
        $definition = Metiers::metier($metier);

        if (! $definition) {
            return 0;
        }

        $crees = 0;
        foreach ($definition['catalogue'] as $ligne) {
            $prestation = Prestation::firstOrCreate(
                ['nom' => $ligne['nom']],
                ['categorie' => $ligne['categorie'], 'unite' => $ligne['unite'], 'prix_ht' => $prix[$ligne['nom']] ?? null],
            );
            $crees += $prestation->wasRecentlyCreated ? 1 : 0;
        }

        $this->reglages->set('catalogue.depart_a_charger', null);
        $this->reglages->set('catalogue.depart_charge', $metier);

        return $crees;
    }

    public function enAttente(): bool
    {
        return (string) reglage('catalogue.depart_a_charger') !== '';
    }
}
