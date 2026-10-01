<?php

namespace Database\Factories;

use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestation>
 */
class PrestationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'categorie' => 'Entretien',
            'nom' => 'Prestation '.fake()->unique()->numberBetween(1, 99999),
            'unite' => 'm²',
            'prix_ht' => 1200,
            'taux_tva' => null,
        ];
    }
}
