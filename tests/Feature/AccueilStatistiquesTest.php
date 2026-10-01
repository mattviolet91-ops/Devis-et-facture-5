<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Demande;
use App\Models\Facture;
use App\Models\Frais;
use App\Models\RendezVous;
use App\Models\Setting;
use App\Models\User;
use App\Models\VisiteSite;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Support\Montant;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccueilStatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
        $this->client = Client::factory()->create(['telephone' => '0600000000', 'provenance' => 'Bouche-à-oreille']);
    }

    private function factureEmise(int $prixHt = 100000): Facture
    {
        $gestion = app(GestionFactures::class);
        $facture = $gestion->creerVide($this->client, $this->gerant->id);
        $gestion->remplacerLignes($facture, [['type' => 'ligne', 'designation' => 'Travaux', 'quantite' => 1000, 'unite' => 'forfait', 'prix_unitaire_ht' => $prixHt]]);
        $gestion->emettre($facture);

        return $facture->fresh();
    }

    public function test_accueil_aujourdhui_et_appels(): void
    {
        RendezVous::create(['titre' => 'Visite du jour', 'debut' => today()->setTime(14, 0), 'fin' => today()->setTime(15, 0), 'client_id' => $this->client->id]);
        Demande::create(['source' => 'formulaire', 'nom' => 'Jean Appel', 'telephone' => '0600000099', 'message' => 'Fuite', 'recue_at' => now()]);

        $this->actingAs($this->gerant)->get(route('accueil'))->assertOk()
            ->assertSee('Visite du jour')
            ->assertSee('Itinéraire')
            ->assertSee('Appels à passer')
            ->assertSee('Jean Appel')
            ->assertSee('Tâches à faire')
            ->assertSee('Nouvelles demandes de devis')
            ->assertSee('Chiffres clés')
            ->assertSee('Facturé ce mois (HT)');

        $this->factureEmise(100000);
        $this->get(route('accueil'))->assertSee(Montant::formater(100000));
    }

    public function test_le_commercial_ne_voit_pas_le_chiffre_d_affaires(): void
    {
        $this->factureEmise();
        $commercial = User::factory()->create();
        $this->actingAs($commercial)->get(route('accueil'))->assertOk()
            ->assertSee('Devis signés ce mois')
            ->assertDontSee('Facturé ce mois')
            ->assertDontSee('Reste à encaisser')
            ->assertDontSee('Factures en retard');
        $this->get(route('statistiques'))->assertForbidden();
        $this->get(route('statistiques.site'))->assertForbidden();
    }

    public function test_personnaliser_l_accueil_et_la_barre_du_bas(): void
    {
        $this->actingAs($this->gerant)->get(route('accueil.personnaliser'))->assertOk();

        $this->post(route('accueil.enregistrer'), [
            'blocs' => ['activite', 'taches'], 'ordre' => ['activite' => 1, 'taches' => 2],
            'bouton_1' => 'planning', 'bouton_2' => 'factures',
        ])->assertRedirect(route('accueil'));

        $page = $this->get(route('accueil'))->assertOk()->assertDontSee('Chiffres clés')->assertDontSee('Astuce du jour')->getContent();
        $this->assertLessThan(strpos($page, 'Tâches à faire'), strpos($page, 'Activité récente'));
        $this->assertStringContainsString('<span>Planning</span>', $page);
        $this->assertStringContainsString('<span>Factures</span>', $page);
        $this->assertStringNotContainsString('<span>Clients</span>', $page);

        // Deux fois le même bouton : refusé ; le commercial ne peut pas choisir Factures.
        $this->post(route('accueil.enregistrer'), ['bouton_1' => 'devis', 'bouton_2' => 'devis'])->assertSessionHasErrors('bouton_2');
        $commercial = User::factory()->create();
        $this->actingAs($commercial)->post(route('accueil.enregistrer'), ['bouton_1' => 'devis', 'bouton_2' => 'factures'])->assertSessionHasErrors('bouton_2');
    }

    public function test_barre_d_actions_selon_l_etape(): void
    {
        $gestion = app(GestionDevis::class);
        $devis = $gestion->creer($this->client, null, 'Toiture', $this->gerant->id);
        $gestion->remplacerLignes($devis, [['type' => 'ligne', 'designation' => 'Tuiles', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 10000]]);

        $this->actingAs($this->gerant)->get(route('devis.show', $devis))->assertSee('class="barre-etape"', false)->assertSee('>Envoyer</a>', false);
        $gestion->marquerEnvoye($devis);
        $this->get(route('devis.show', $devis))->assertSee('>Faire signer</a>', false)->assertSee('>Relancer</a>', false);
        $gestion->accepter($devis->fresh());
        $this->get(route('devis.show', $devis))->assertSee('>Facturer</a>', false)->assertSee('>Planifier</a>', false);

        $facture = $this->factureEmise();
        $this->get(route('factures.show', $facture))->assertSee('>Encaisser</a>', false);
        $this->get(route('factures.index'))->assertSee('data-glisser', false);
    }

    public function test_frais_sur_la_facture(): void
    {
        Storage::fake('local');
        $facture = $this->factureEmise(100000);
        $image = imagecreatetruecolor(50, 50);
        ob_start();
        imagejpeg($image);
        $ticket = UploadedFile::fake()->createWithContent('ticket.jpg', ob_get_clean());

        $this->actingAs($this->gerant)->post(route('frais.store', $facture), [
            'libelle' => 'Tuiles', 'categorie' => 'materiaux', 'montant_frais' => '250,40', 'date_frais' => today()->toDateString(), 'ticket' => $ticket,
        ])->assertRedirect(route('factures.show', $facture).'#frais');

        $frais = Frais::firstOrFail();
        $this->assertSame(25040, $frais->montant_ttc);
        Storage::disk('local')->assertExists($frais->justificatif);
        $this->get(route('frais.ticket', $frais))->assertOk();

        $this->get(route('factures.show', $facture))->assertSee('Frais du chantier')->assertSee('Il vous reste')->assertSee(Montant::formater($facture->total_ttc - 25040));

        $this->post(route('frais.store', $facture), ['libelle' => '', 'categorie' => 'x', 'montant_frais' => 'abc', 'date_frais' => ''])
            ->assertSessionHasErrors(['libelle', 'categorie', 'montant_frais', 'date_frais']);

        $commercial = User::factory()->create();
        $this->actingAs($commercial)->get(route('frais.ticket', $frais))->assertForbidden();

        $this->actingAs($this->gerant)->delete(route('frais.destroy', $frais));
        Storage::disk('local')->assertMissing($frais->justificatif);
    }

    public function test_statistiques_par_provenance_et_par_compte(): void
    {
        $this->factureEmise(100000);
        $this->actingAs($this->gerant)->get(route('statistiques'))->assertOk()
            ->assertSee('Bouche-à-oreille')
            ->assertSee('facturé '.Montant::formater(100000).' HT')
            ->assertSee($this->gerant->email)
            ->assertSee('<svg', false);
    }

    public function test_compteur_du_site_respecte_la_vie_privee(): void
    {
        $this->get('/s.js')->assertOk()->assertSee('Compteur désactivé');

        app(Reglages::class)->set('site.compteur_actif', true);
        app(Reglages::class)->set('identite.site', 'https://www.entreprise-fictive.test');
        $this->get('/s.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->assertSee('sendBeacon', false);

        $envoyer = fn (array $donnees, string $origine = 'https://www.entreprise-fictive.test', string $agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) Mobile') => $this->call('POST', '/s', [], [], [], [
            'HTTP_ORIGIN' => $origine, 'HTTP_USER_AGENT' => $agent, 'CONTENT_TYPE' => 'text/plain', 'REMOTE_ADDR' => '203.0.113.7',
        ], json_encode($donnees));

        $envoyer(['e' => 'vue', 'p' => '/couverture?x=1', 'r' => 'https://www.google.com/'])->assertNoContent();
        $envoyer(['e' => 'appeler', 'p' => '/couverture']);
        $envoyer(['e' => 'vue', 'p' => '/'], 'https://autre-site.test');                 // autre site : ignoré
        $envoyer(['e' => 'vue', 'p' => '/'], 'https://www.entreprise-fictive.test', 'Googlebot/2.1'); // robot : ignoré
        $envoyer(['e' => 'pirate', 'p' => '/']);                                           // événement inconnu

        $this->assertSame(2, VisiteSite::count());
        $vue = VisiteSite::where('evenement', 'vue')->first();
        $this->assertSame('/couverture', $vue->page);
        $this->assertSame('Google', $vue->source);
        $this->assertSame('Téléphone', $vue->appareil);
        $this->assertSame(16, strlen($vue->empreinte));
        $this->assertStringNotContainsString('203.0.113.7', json_encode(VisiteSite::all()->toArray()));

        $this->actingAs($this->gerant)->get(route('statistiques.site'))->assertOk()
            ->assertSee('Taux de contact')
            ->assertSee('100,0 %')
            ->assertSee('Appeler')
            ->assertSee('/couverture');

        // Données gardées 13 mois.
        VisiteSite::create(['jour' => now()->subMonths(14)->toDateString(), 'empreinte' => str_repeat('a', 16), 'evenement' => 'vue']);
        $this->artisan('app:stats-site')->assertSuccessful();
        $this->assertSame(2, VisiteSite::count());
    }

    public function test_connexion_jetpack_et_mise_a_jour(): void
    {
        app(Reglages::class)->set('site.jetpack_client_id', '12345');
        app(Reglages::class)->setSecret('site.jetpack_client_secret', 'secret-test');
        Http::fake([
            'public-api.wordpress.com/oauth2/token' => Http::response(['access_token' => 'jeton-test', 'blog_id' => '987', 'blog_url' => 'https://site.test']),
            'public-api.wordpress.com/rest/v1.1/sites/987/stats/visits*' => Http::response(['fields' => ['period', 'views', 'visitors'], 'data' => [['2026-10-01', 40, 12]]]),
            'public-api.wordpress.com/rest/v1.1/sites/987/stats/top-posts*' => Http::response(['days' => ['2026-10-01' => ['postviews' => [['title' => 'Accueil', 'views' => 30]]]]]),
            'public-api.wordpress.com/rest/v1.1/sites/987/stats/referrers*' => Http::response(['days' => ['2026-10-01' => ['groups' => [['name' => 'Moteurs de recherche', 'total' => 9]]]]]),
            'public-api.wordpress.com/rest/v1.1/sites/987/stats/clicks*' => Http::response(['days' => ['2026-10-01' => ['clicks' => [['name' => 'tel:0600000000', 'views' => 3]]]]]),
        ]);

        $this->actingAs($this->gerant)->get(route('statistiques.site'))->assertSee('Connecter WordPress.com');
        $redirection = $this->post(route('statistiques.jetpack.connecter'))->assertRedirect();
        $this->assertStringStartsWith('https://public-api.wordpress.com/oauth2/authorize?', $redirection->headers->get('Location'));
        parse_str((string) parse_url($redirection->headers->get('Location'), PHP_URL_QUERY), $parametres);

        // Mauvais état : refusé.
        $this->get(route('statistiques.jetpack.retour', ['code' => 'abc', 'state' => 'faux']))->assertSessionHas('erreur');

        $this->post(route('statistiques.jetpack.connecter'));
        $etat = session('jetpack_etat');
        $this->get(route('statistiques.jetpack.retour', ['code' => 'abc', 'state' => $etat]))->assertSessionHas('statut');

        $this->assertSame('jeton-test', app(Reglages::class)->getSecret('site.jetpack_jeton'));
        $this->assertStringNotContainsString('jeton-test', json_encode(Setting::all()->toArray()));
        Http::assertSent(fn (RequeteHttp $r) => str_contains($r->url(), 'stats/visits') && $r->hasHeader('Authorization', 'Bearer jeton-test'));

        $this->get(route('statistiques.site'))->assertSee('12</strong> visiteurs', false)->assertSee('Moteurs de recherche')->assertSee('tel:0600000000');

        $this->post(route('statistiques.jetpack.deconnecter'));
        $this->assertFalse(app(Reglages::class)->aSecret('site.jetpack_jeton'));
    }
}
