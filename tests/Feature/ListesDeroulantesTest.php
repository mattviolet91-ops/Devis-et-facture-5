<?php

namespace Tests\Feature;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les listes déroulantes envoient l'identifiant (client, chantier, personne, taux), pas le libellé.
 */
class ListesDeroulantesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_listes_envoient_les_identifiants(): void
    {
        $gerant = User::factory()->gerant()->create();
        $client = Client::factory()->create(['nom' => 'Lambert']);
        $chantier = Chantier::create(['client_id' => $client->id, 'adresse' => '1 rue', 'ville' => 'Ville-Test', 'libelle' => 'Grange']);
        $devis = Devis::create(['client_id' => $client->id, 'chantier_id' => $chantier->id, 'objet' => 'Toit', 'created_by' => $gerant->id]);
        $devis->forceFill(['statut' => Devis::ACCEPTE, 'accepte_at' => now()])->save();
        $this->actingAs($gerant);

        // Planning depuis un devis : client et chantier sélectionnés par leur identifiant.
        $page = $this->get(route('planning.create', ['devis' => $devis->id]))->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$client->id.'"\s+selected/', $page);
        $this->assertMatchesRegularExpression('/<option value="'.$chantier->id.'"\s+selected/', $page);
        $this->assertStringContainsString('<option value="'.$gerant->id.'"', $page);
        $this->assertStringContainsString('<option value="1"', $page); // rappel la veille

        // Le formulaire, envoyé tel quel, garde le client et le chantier.
        $this->post(route('planning.store'), [
            'type' => 'chantier', 'titre' => 'Toit', 'date_debut' => now()->addDay()->toDateString(), 'devis_id' => $devis->id,
            'client_id' => (string) $client->id, 'chantier_id' => (string) $chantier->id, 'user_id' => (string) $gerant->id, 'rappel_client_jours' => '1',
        ])->assertSessionHasNoErrors();
        $rdv = RendezVous::firstOrFail();
        $this->assertSame($client->id, $rdv->client_id);
        $this->assertSame($chantier->id, $rdv->chantier_id);

        // Devis : liste des adresses de chantier.
        $this->assertStringContainsString('<option value="'.$chantier->id.'"', $this->get(route('photos.index', $client))->getContent());
        $this->assertStringContainsString('<option value="'.$chantier->id.'"', $this->get(route('rapports.create', ['client' => $client->id]))->getContent());

        // Catalogue : le taux de TVA est envoyé en centièmes de pour cent.
        $this->assertStringContainsString('<option value="1000"', $this->get(route('catalogue.create'))->getContent());

        // Listes simples : le texte est envoyé (civilité, provenance).
        $page = $this->get(route('clients.create'))->getContent();
        $this->assertStringContainsString('<option value="Mme"', $page);
        $this->assertStringContainsString('<option value="Bouche-à-oreille"', $page);
    }

    public function test_fiche_client_montre_devis_factures_et_rendez_vous(): void
    {
        $gerant = User::factory()->gerant()->create();
        $client = Client::factory()->create();
        Devis::create(['client_id' => $client->id, 'objet' => 'Gouttières', 'created_by' => $gerant->id]);
        RendezVous::create(['titre' => 'Visite', 'debut' => now()->addDay(), 'fin' => now()->addDay()->addHour(), 'client_id' => $client->id]);

        $this->actingAs($gerant)->get(route('clients.show', $client))->assertOk()
            ->assertSee('Gouttières')->assertSee('Visite')
            ->assertSee(route('devis.create', ['client' => $client->id]), false)
            ->assertSee(route('planning.create', ['client' => $client->id]), false);

        $this->actingAs(User::factory()->create())->get(route('clients.show', $client))->assertOk()->assertDontSee('factures et rendez-vous');
    }
}
