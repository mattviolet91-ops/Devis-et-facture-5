<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\ControlesTest;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $commercial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commercial = User::factory()->create();
    }

    /**
     * @return array<string, string>
     */
    private function particulier(array $modifs = []): array
    {
        return array_merge([
            'type' => 'particulier', 'civilite' => 'Mme', 'nom' => 'Martin', 'prenom' => 'Sophie',
            'telephone' => '06 00 00 00 01', 'email' => 'Sophie.Martin@Exemple.test',
            'adresse' => '12 rue des Lilas', 'code_postal' => '00100', 'ville' => 'Ville-Test', 'provenance' => 'Recherche Google',
        ], $modifs);
    }

    public function test_liste_vide_avec_bouton_pour_creer_le_premier_client(): void
    {
        $this->actingAs($this->commercial)->get('/clients')
            ->assertOk()
            ->assertSee('Aucun client pour le moment')
            ->assertSee('Créer mon premier client');
    }

    public function test_creer_un_particulier(): void
    {
        $this->actingAs($this->commercial)->get('/clients/nouveau')->assertOk()->assertSee('Comment nous a-t-il connus ?');

        $reponse = $this->post('/clients', $this->particulier());

        $client = Client::firstOrFail();
        $reponse->assertRedirect(route('clients.show', $client));
        $this->assertSame('0600000001', $client->telephone);
        $this->assertSame('sophie.martin@exemple.test', $client->email);
        $this->assertSame('Mme Sophie Martin', $client->nomComplet());
        $this->assertSame($this->commercial->id, $client->created_by);
        $this->assertDatabaseHas('activites', ['action' => 'client.creation']);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Mme Sophie Martin')
            ->assertSee('href="tel:+33600000001"', false)
            ->assertSee('href="sms:+33600000001"', false)
            ->assertSee('06 00 00 00 01')
            ->assertSee('Recherche Google');
    }

    public function test_creer_un_professionnel(): void
    {
        $siret = ControlesTest::siretFictif();

        $this->actingAs($this->commercial)->post('/clients', [
            'type' => 'professionnel', 'raison_sociale' => 'Société Fictive', 'siret' => $siret,
            'nom' => 'Moreau', 'prenom' => 'Paul', 'civilite' => 'M.', 'email' => 'contact@fictive.test',
        ])->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertTrue($client->estProfessionnel());
        $this->assertSame('Société Fictive', $client->nomComplet());
        $this->assertSame('M. Paul Moreau', $client->contact());
    }

    public function test_champs_obligatoires_expliques(): void
    {
        $this->actingAs($this->commercial)->post('/clients', ['type' => 'particulier'])
            ->assertSessionHasErrors([
                'nom' => 'Indiquez le nom du client.',
                'telephone' => 'Indiquez au moins un téléphone ou un email.',
            ]);

        $this->post('/clients', ['type' => 'professionnel', 'telephone' => '0600000009'])
            ->assertSessionHasErrors(['raison_sociale' => 'Indiquez le nom de la société.']);

        $this->post('/clients', $this->particulier(['code_postal' => '123', 'email' => 'pas-un-email', 'siret' => '']))
            ->assertSessionHasErrors(['code_postal' => 'Le code postal fait 5 chiffres.', 'email']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_alerte_de_doublon_meme_telephone_ou_email(): void
    {
        $existant = Client::factory()->create(['telephone' => '0600000001', 'nom' => 'Martin']);
        $this->actingAs($this->commercial);

        // Même numéro écrit autrement (+33…).
        $this->from('/clients/nouveau')->post('/clients', $this->particulier(['telephone' => '+33 6 00 00 00 01', 'email' => '']))
            ->assertRedirect('/clients/nouveau')
            ->assertSessionHas('doublons', fn ($d) => $d[0]['id'] === $existant->id);
        $this->assertDatabaseCount('clients', 1);

        $this->get('/clients/nouveau')
            ->assertSee('ce client existe peut-être déjà')
            ->assertSee('enregistrer quand même');

        // Le gérant confirme que c'est une autre personne.
        $this->post('/clients', $this->particulier(['telephone' => '+33 6 00 00 00 01', 'email' => '', 'confirmer_doublon' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 2);

        // Même email, majuscules différentes.
        Client::factory()->create(['email' => 'unique@exemple.test', 'telephone' => '0600000099']);
        $this->post('/clients', $this->particulier(['telephone' => '', 'email' => 'UNIQUE@exemple.test']))
            ->assertSessionHas('doublons');
    }

    public function test_modifier_sans_se_signaler_soi_meme_comme_doublon(): void
    {
        $client = Client::factory()->create(['telephone' => '0600000001', 'email' => 'a@exemple.test']);

        $this->actingAs($this->commercial)->get(route('clients.edit', $client))->assertOk();
        $this->put(route('clients.update', $client), $this->particulier(['telephone' => '0600000001', 'email' => 'a@exemple.test', 'ville' => 'Autre-Ville']))
            ->assertRedirect(route('clients.show', $client))
            ->assertSessionMissing('doublons');

        $this->assertSame('Autre-Ville', $client->fresh()->ville);
    }

    public function test_recherche_sans_tenir_compte_des_accents(): void
    {
        Client::factory()->create(['nom' => 'Bérard', 'prenom' => 'Hélène', 'ville' => 'Saint-Étienne', 'telephone' => '0600000011']);
        Client::factory()->create(['nom' => 'Durand', 'prenom' => 'Paul', 'ville' => 'Lyon', 'telephone' => '0600000012']);
        Client::factory()->professionnel()->create(['raison_sociale' => 'Élagage Œuvre & Fils', 'telephone' => '0600000013']);

        $this->actingAs($this->commercial);
        $cherche = fn (string $q) => $this->get('/clients?q='.urlencode($q))->assertOk();

        $cherche('helene')->assertSee('Hélène Bérard')->assertDontSee('Durand');
        $cherche('BERARD')->assertSee('Hélène Bérard');
        $cherche('saint etienne')->assertSee('Hélène Bérard');
        $cherche('Elagage')->assertSee('Élagage Œuvre &amp; Fils', false);
        $cherche('06 00 00 00 12')->assertSee('Paul Durand')->assertDontSee('Hélène');
        $cherche('paul lyon')->assertSee('Paul Durand');
        $cherche('introuvable')->assertSee('Aucun client ne correspond');
        $cherche('%')->assertOk();
    }

    public function test_corbeille_et_restauration(): void
    {
        $gerant = User::factory()->gerant()->create();
        $client = Client::factory()->create(['nom' => 'Corbeille']);

        $this->actingAs($this->commercial)->delete(route('clients.destroy', $client))->assertRedirect('/clients');
        $this->assertSoftDeleted($client);
        $this->get(route('clients.show', $client))->assertNotFound();

        $this->actingAs($gerant)->get('/corbeille')->assertSee('Camille Corbeille');
        $this->post("/corbeille/client/{$client->id}/restaurer")->assertRedirect('/corbeille');
        $this->assertNotSoftDeleted($client);
    }

    public function test_les_commerciaux_ont_acces_aux_clients(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->commercial)->get('/clients')->assertOk()->assertSee($client->nomComplet());
        $this->get('/nouveau')->assertSee('href="'.route('clients.create').'"', false);
    }

    public function test_pagination_et_filtre_par_type(): void
    {
        Client::factory()->count(35)->create();
        Client::factory()->professionnel()->create(['raison_sociale' => 'Société Seule']);

        $this->actingAs($this->commercial)->get('/clients')->assertSee('36 client(s)')->assertSee('Suivants ›');
        $this->get('/clients?type=professionnel')->assertSee('Société Seule')->assertSee('1 client(s)');
    }

    public function test_aucun_script_ni_style_inline(): void
    {
        $client = Client::factory()->create();
        $client->chantiers()->create(['adresse' => '1 rue', 'ville' => 'Ville']);
        $this->actingAs($this->commercial);

        foreach (['/clients', '/clients/nouveau', route('clients.show', $client), route('clients.edit', $client), route('chantiers.create', $client), '/clients/import'] as $page) {
            $contenu = $this->get($page)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu, $page);
        }
    }
}
