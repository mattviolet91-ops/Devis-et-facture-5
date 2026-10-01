<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Clients FICTIFS pour les tests (domaine .test, numéros factices).
 *
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => Client::PARTICULIER,
            'civilite' => 'Mme',
            'nom' => 'Fictif'.fake()->unique()->numberBetween(1, 99999),
            'prenom' => 'Camille',
            'telephone' => '06'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->userName().'@exemple.test',
            'adresse' => '1 rue de l\'Exemple',
            'code_postal' => '00000',
            'ville' => 'Ville-Test',
        ];
    }

    public function professionnel(): static
    {
        return $this->state(fn () => ['type' => Client::PROFESSIONNEL, 'raison_sociale' => 'Société Fictive '.fake()->unique()->numberBetween(1, 9999)]);
    }
}
