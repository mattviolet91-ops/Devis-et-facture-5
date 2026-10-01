<?php

namespace Tests\Feature;

use App\Console\Commands\Demo;
use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_demo_installe_des_donnees_fictives(): void
    {
        $this->artisan('app:demo')->expectsOutputToContain('Données de démonstration installées')->assertSuccessful();

        $this->assertSame('Couverture Démo', reglage('identite.nom_commercial'));
        $this->assertTrue(User::where('email', 'demo@exemple.test')->first()->estGerant());
        $this->assertFalse(User::where('email', 'commercial@exemple.test')->first()->estGerant());

        // Aucun nom de personne, coordonnées sur un domaine réservé aux tests.
        $this->assertSame(0, User::whereNotNull('name')->count());
        $this->assertStringEndsWith('.test', reglage('identite.email'));

        // Clients d'exemple : coordonnées fictives (domaine .test, numéros 06 00 00…).
        $this->assertGreaterThanOrEqual(8, Client::count());
        $this->assertSame(0, Client::whereNotNull('email')->where('email', 'not like', '%.test')->count());
        $this->assertSame(0, Client::where('telephone', 'not like', '060000%')->count());
        $this->assertGreaterThan(0, Chantier::count());
        $this->assertSame(5, Devis::count());
        $this->assertSame(1, Devis::where('statut', 'accepte')->count());
    }

    public function test_les_mots_de_passe_sont_aleatoires(): void
    {
        $this->artisan('app:demo')->assertSuccessful();
        $premier = User::where('email', 'demo@exemple.test')->first()->password;

        $this->artisan('app:demo')->expectsConfirmation('Des comptes existent déjà. Ajouter quand même les données de démonstration ?', 'yes')->assertSuccessful();

        $this->assertNotSame($premier, User::where('email', 'demo@exemple.test')->first()->password);
    }

    public function test_bandeau_demonstration_sur_les_pages(): void
    {
        $this->get('/connexion')->assertDontSee('Démonstration');

        $this->artisan('app:demo')->assertSuccessful();

        $this->get('/connexion')->assertSee('Démonstration : toutes les données sont fictives.');
        $this->actingAs(User::first())->get('/accueil')->assertSee('Démonstration')->assertSee('Nouvel appareil');
    }

    public function test_refusee_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('app:demo')->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertSame('', reglage('identite.nom_commercial'));
    }

    public function test_entreprise_fictive_sans_nom_de_dirigeant(): void
    {
        foreach (Demo::ENTREPRISE as $cle => $valeur) {
            $this->assertStringNotContainsStringIgnoringCase('dirigeant', $cle);
        }
    }
}
