<?php

namespace Tests\Feature;

use App\Models\CleApi;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiClaudeTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private string $cle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
        [, $this->cle] = CleApi::creer('Téléphone', $this->gerant);
        Client::factory()->create(['civilite' => 'Mme', 'prenom' => 'Sophie', 'nom' => 'Martin', 'ville' => 'Ville-Test']);
        Prestation::factory()->create(['nom' => 'Démoussage', 'unite' => 'm²', 'prix_ht' => 1200]);
    }

    private function api(string $methode, string $adresse, array $donnees = [], ?string $cle = null)
    {
        return $this->json($methode, '/api/v1'.$adresse, $donnees, ['Authorization' => 'Bearer '.($cle ?? $this->cle)]);
    }

    public function test_creer_une_cle_affichee_une_seule_fois(): void
    {
        $this->actingAs($this->gerant)->post(route('reglages.claude.creer'), ['nom' => 'Claude'])->assertRedirect(route('reglages.claude'));
        $page = $this->get(route('reglages.claude'))->assertOk()->assertSee('MC_API_TOKEN')->getContent();
        preg_match('/mc_[A-Za-z0-9]{48}/', $page, $m);
        $this->assertNotEmpty($m);
        $this->assertDatabaseHas('cles_api', ['nom' => 'Claude', 'sha256' => hash('sha256', $m[0])]);
        $this->assertDatabaseMissing('cles_api', ['sha256' => $m[0]]);
        $this->get(route('reglages.claude'))->assertDontSee($m[0]);
    }

    public function test_lecture_et_brouillon(): void
    {
        $this->api('GET', '/clients?q=martin')->assertOk()->assertJsonPath('clients.0.nom', 'Mme Sophie Martin');
        $this->api('GET', '/prestations?q=demou')->assertOk()->assertJsonPath('prestations.0.prix_ht_centimes', 1200);

        $this->api('POST', '/devis/apercu', ['phrase' => 'Mme Martin, démoussage 100 m² à 12 €'])
            ->assertOk()->assertJsonPath('client.nom', 'Mme Sophie Martin')->assertJsonPath('erreurs', []);
        $this->assertSame(0, Devis::count());

        $this->api('POST', '/devis', ['phrase' => 'Mme Martin, démoussage 100 m² à 12 €', 'objet' => 'Toiture'])
            ->assertCreated()->assertJsonPath('statut', 'brouillon');
        $devis = Devis::firstOrFail();
        $this->assertSame('brouillon', $devis->statut);
        $this->assertSame(120000, $devis->total_ht);
        $this->assertSame($this->gerant->id, $devis->created_by);

        $client = Client::first();
        $this->api('POST', '/devis', ['client_id' => $client->id, 'lignes' => [['designation' => 'Gouttière', 'quantite' => 2.5, 'unite' => 'ml', 'prix_ht' => 65.5]]])->assertCreated();
        $this->assertSame(16375, Devis::latest('id')->first()->total_ht);

        $this->api('POST', '/devis', ['phrase' => 'Inconnu, rien'])->assertStatus(422);
        $this->api('POST', '/devis', ['client_id' => 9999])->assertStatus(422);
        $this->assertNotNull(CleApi::first()->derniere_utilisation_at);
    }

    public function test_refus(): void
    {
        $this->json('GET', '/api/v1/clients')->assertUnauthorized();
        $this->api('GET', '/clients', [], 'mc_'.str_repeat('a', 48))->assertUnauthorized();

        // Clé révoquée.
        CleApi::query()->update(['revoquee_at' => now()]);
        $this->api('GET', '/clients')->assertUnauthorized();
        CleApi::query()->update(['revoquee_at' => null]);

        // Compte devenu commercial ou désactivé.
        $this->gerant->forceFill(['role' => 'commercial'])->save();
        $this->api('GET', '/clients')->assertUnauthorized();
        $this->gerant->forceFill(['role' => 'gerant', 'is_active' => false])->save();
        $this->api('GET', '/clients')->assertUnauthorized();
    }

    public function test_limite_de_30_appels_par_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->api('GET', '/clients')->assertOk();
        }
        $this->api('GET', '/clients')->assertStatus(429)->assertJsonPath('erreur', 'Trop d\'appels : 30 par minute au plus. Réessayez dans une minute.');
    }

    public function test_refusee_sur_l_adresse_client(): void
    {
        config(['app.client_url' => 'https://devis.exemple.test']);
        $this->json('GET', 'https://devis.exemple.test/api/v1/clients', [], ['Authorization' => 'Bearer '.$this->cle])->assertNotFound();
    }
}
