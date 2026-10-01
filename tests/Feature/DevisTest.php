<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Prestation;
use App\Models\User;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create(['nom' => 'Martin']);
        app(Reglages::class)->set('tva.regime', 'assujetti');
        app(Reglages::class)->set('documents.validite_devis_jours', 30);
    }

    private function brouillon(): Devis
    {
        $this->actingAs($this->user)->post('/devis', ['client_id' => $this->client->id, 'objet' => 'Toiture'])->assertRedirect();

        return Devis::latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function saisie(array $lignes, array $entete = []): array
    {
        return array_merge(['validite_jours' => '30', 'acompte_pourcentage' => '30', 'lignes' => $lignes], $entete);
    }

    private function remplir(Devis $devis): void
    {
        $this->put(route('devis.update', $devis), $this->saisie([
            ['type' => 'section', 'designation' => 'Toiture'],
            ['type' => 'ligne', 'designation' => 'Démoussage', 'quantite' => '120', 'unite' => 'm²', 'prix' => '12', 'taux_tva' => '1000'],
            ['type' => 'ligne', 'designation' => 'Faîtières', 'quantite' => '3', 'unite' => 'u', 'prix' => '150,00', 'taux_tva' => '1000'],
            ['type' => 'ligne', 'designation' => 'Gouttière', 'quantite' => '10', 'unite' => 'ml', 'prix' => '65', 'taux_tva' => '1000', 'option' => '1'],
            ['type' => 'texte', 'designation' => 'Accès par échafaudage.'],
        ]))->assertRedirect(route('devis.show', $devis));
    }

    public function test_liste_vide(): void
    {
        $this->actingAs($this->user)->get('/devis')->assertOk()->assertSee('Créer mon premier devis')->assertSee('Devis express');
    }

    public function test_creer_et_remplir_un_devis(): void
    {
        $this->actingAs($this->user)->get('/devis/nouveau')->assertOk()->assertSee('Pour quel client ?')->assertSee($this->client->nomComplet());
        $this->get('/devis/nouveau?client='.$this->client->id)->assertOk()->assertSee('Créer le devis');

        $devis = $this->brouillon();
        $this->assertSame(Devis::BROUILLON, $devis->statut);
        $this->assertNull($devis->numero);
        $this->assertSame(30, $devis->acompte_pourcentage);

        $this->get(route('devis.edit', $devis))->assertOk()->assertSee('+ Ligne')->assertSee('id="modele-ligne"', false);
        $this->remplir($devis);

        $devis->refresh();
        $this->assertSame(4, $devis->lignes()->where('type', '!=', 'section')->count());
        $this->assertSame(189000, $devis->total_ht);
        $this->assertSame(18900, $devis->total_tva);
        $this->assertSame(207900, $devis->total_ttc);
        $this->assertSame(65000, $devis->total_options_ht);

        $this->get(route('devis.show', $devis))
            ->assertSee('Brouillon n° '.$devis->id)
            ->assertSee('Démoussage')
            ->assertSee('Option')
            ->assertSee('Accès par échafaudage.')
            ->assertSee("2\u{202F}079,00", false);
    }

    public function test_remise_et_erreurs_de_saisie(): void
    {
        $devis = $this->brouillon();

        $this->put(route('devis.update', $devis), $this->saisie([
            ['type' => 'ligne', 'designation' => '', 'quantite' => 'beaucoup', 'prix' => 'cher', 'taux_tva' => '1000'],
        ]))->assertSessionHasErrors([
            'lignes.0.designation' => 'Ligne 1 : indiquez la désignation.',
            'lignes.0.quantite' => 'Ligne 1 : quantité illisible (exemple : 12,5).',
            'lignes.0.prix' => 'Ligne 1 : prix illisible (exemple : 45,00).',
        ]);

        $this->put(route('devis.update', $devis), $this->saisie(
            [['type' => 'ligne', 'designation' => 'Travaux', 'quantite' => '1', 'prix' => '1000', 'taux_tva' => '1000']],
            ['remise_type' => 'pourcentage', 'remise' => '10'],
        ))->assertSessionHasNoErrors();

        $devis->refresh();
        $this->assertSame(10000, $devis->total_remise);
        $this->assertSame(90000, $devis->total_ht);
        $this->assertSame(99000, $devis->total_ttc);
    }

    public function test_envoi_numero_et_devis_fige(): void
    {
        $devis = $this->brouillon();

        // Un devis vide ne s'envoie pas.
        $this->post(route('devis.envoyer', $devis))->assertSessionHasErrors('devis');

        $this->remplir($devis);
        $this->post(route('devis.envoyer', $devis))->assertRedirect(route('devis.show', $devis));

        $devis->refresh();
        $this->assertSame('DEV-'.now()->year.'-0001', $devis->numero);
        $this->assertSame(Devis::ENVOYE, $devis->statut);
        $this->assertNotNull($devis->envoye_at);

        // Plus modifiable.
        $this->get(route('devis.edit', $devis))->assertRedirect(route('devis.show', $devis));
        $this->put(route('devis.update', $devis), $this->saisie([]))->assertRedirect(route('devis.show', $devis));
        $this->assertSame(4, $devis->lignes()->where('type', '!=', 'section')->count());
        $this->delete(route('devis.destroy', $devis))->assertSessionHasErrors('devis');
        $this->assertNotSoftDeleted($devis);
    }

    public function test_ligne_sans_prix_bloque_l_envoi(): void
    {
        $devis = $this->brouillon();
        $this->put(route('devis.update', $devis), $this->saisie([['type' => 'ligne', 'designation' => 'A chiffrer', 'quantite' => '1', 'prix' => '', 'taux_tva' => '1000']]))
            ->assertSessionHasNoErrors();

        $this->post(route('devis.envoyer', $devis))->assertSessionHasErrors(['devis' => 'Une ligne n\'a pas de prix. Complétez-la avant d\'envoyer.']);
    }

    public function test_accepte_refuse(): void
    {
        $devis = $this->brouillon();
        $this->remplir($devis);

        $this->post(route('devis.accepter', $devis))->assertSessionHasErrors('devis');

        $this->post(route('devis.envoyer', $devis));
        $this->post(route('devis.accepter', $devis))->assertRedirect();
        $this->assertSame(Devis::ACCEPTE, $devis->fresh()->statut);

        $autre = $this->brouillon();
        $this->remplir($autre);
        $this->post(route('devis.envoyer', $autre));
        $this->post(route('devis.refuser', $autre), ['motif' => 'Trop cher'])->assertRedirect();
        $this->assertSame(Devis::REFUSE, $autre->fresh()->statut);
        $this->get(route('devis.show', $autre))->assertSee('Trop cher');
    }

    public function test_nouvelle_version_remplace_l_ancienne(): void
    {
        $devis = $this->brouillon();
        $this->remplir($devis);
        $this->post(route('devis.envoyer', $devis));

        $this->post(route('devis.version', $devis))->assertRedirect();
        $v2 = Devis::latest('id')->firstOrFail();
        $this->assertSame(2, $v2->version);
        $this->assertSame(Devis::BROUILLON, $v2->statut);
        $this->assertSame($devis->fresh()->total_ttc, $v2->total_ttc);

        $this->post(route('devis.envoyer', $v2));
        $this->assertSame('DEV-'.now()->year.'-0001-V2', $v2->fresh()->numero);
        $this->assertSame(Devis::REMPLACE, $devis->fresh()->statut);

        // La version 3 part de la version 2.
        $this->post(route('devis.version', $v2));
        $v3 = Devis::latest('id')->firstOrFail();
        $this->post(route('devis.envoyer', $v3));
        $this->assertSame('DEV-'.now()->year.'-0001-V3', $v3->fresh()->numero);
        $this->assertSame(Devis::REMPLACE, $v2->fresh()->statut);

        // Les numéros de nouveaux devis continuent sans trou.
        $autre = $this->brouillon();
        $this->remplir($autre);
        $this->post(route('devis.envoyer', $autre));
        $this->assertSame('DEV-'.now()->year.'-0002', $autre->fresh()->numero);

        $this->get(route('devis.show', $v3))->assertSee('Versions');
    }

    public function test_dupliquer_pour_un_autre_client(): void
    {
        $devis = $this->brouillon();
        $this->remplir($devis);
        $autreClient = Client::factory()->create();

        $this->post(route('devis.dupliquer', $devis), ['client_id' => $autreClient->id])->assertRedirect();
        $copie = Devis::latest('id')->firstOrFail();

        $this->assertSame($autreClient->id, $copie->client_id);
        $this->assertSame(Devis::BROUILLON, $copie->statut);
        $this->assertSame($devis->lignes()->count(), $copie->lignes()->count());
        $this->assertSame($devis->fresh()->total_ttc, $copie->total_ttc);
    }

    public function test_expiration_automatique(): void
    {
        $devis = $this->brouillon();
        $this->remplir($devis);
        $this->post(route('devis.envoyer', $devis));

        $this->travel(29)->days();
        $this->artisan('app:expirer-devis')->assertSuccessful();
        $this->assertSame(Devis::ENVOYE, $devis->fresh()->statut);

        $this->travel(3)->days();
        $this->artisan('app:expirer-devis')->expectsOutput('1 devis expiré(s).');
        $this->assertSame(Devis::EXPIRE, $devis->fresh()->statut);
    }

    public function test_brouillon_a_la_corbeille(): void
    {
        $devis = $this->brouillon();
        $this->delete(route('devis.destroy', $devis))->assertRedirect('/devis');
        $this->assertSoftDeleted($devis);
    }

    public function test_prestation_du_catalogue_comptee(): void
    {
        $prestation = Prestation::factory()->create();
        $devis = $this->brouillon();

        $this->put(route('devis.update', $devis), $this->saisie([
            ['type' => 'ligne', 'designation' => 'X', 'quantite' => '1', 'prix' => '10', 'taux_tva' => '1000', 'prestation_id' => (string) $prestation->id],
            ['type' => 'ligne', 'designation' => 'Y', 'quantite' => '1', 'prix' => '10', 'taux_tva' => '1000', 'prestation_id' => '999999'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, $prestation->fresh()->utilisations);
        $this->assertNull($devis->lignes()->where('designation', 'Y')->value('prestation_id'));
    }

    public function test_franchise_affiche_la_mention(): void
    {
        app(Reglages::class)->set('tva.regime', 'franchise');
        $devis = $this->brouillon();
        $this->remplir($devis);

        $this->assertSame(0, $devis->fresh()->total_tva);
        $this->get(route('devis.show', $devis))->assertSee('TVA non applicable, art. 293 B du CGI');
    }

    public function test_filtres_de_la_liste_et_inline(): void
    {
        $devis = $this->brouillon();
        $this->remplir($devis);

        $this->get('/devis?statut=brouillon')->assertSee($this->client->nomComplet());
        $this->get('/devis?statut=accepte')->assertSee('Aucun devis dans cette liste');

        foreach (['/devis', '/devis/nouveau', route('devis.show', $devis), route('devis.edit', $devis), '/devis/express'] as $page) {
            $contenu = $this->get($page)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu, $page);
        }
    }
}
