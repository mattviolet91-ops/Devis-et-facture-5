<?php

namespace Tests\Feature;

use App\Mail\EmailDocument;
use App\Models\Client;
use App\Models\Devis;
use App\Models\LieuGeocode;
use App\Models\MeteoPrevision;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Meteo;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->gerant()->create();
        $this->client = Client::factory()->create([
            'nom' => 'Testeur', 'email' => 'client@exemple.test', 'adresse' => '1 rue du Test', 'code_postal' => '00100', 'ville' => 'Ville-Test',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ajouter_un_rendez_vous_et_le_voir_dans_la_semaine(): void
    {
        Carbon::setTestNow('2026-10-05 08:00:00'); // un lundi
        $this->actingAs($this->user);

        $this->get(route('planning.create'))->assertOk()->assertSee('Nouveau rendez-vous');

        $reponse = $this->post(route('planning.store'), [
            'type' => 'rdv', 'titre' => 'Visite pour devis', 'date_debut' => '2026-10-06', 'heure_debut' => '10:00',
            'client_id' => $this->client->id, 'rappel_client_jours' => '1',
        ]);
        $rdv = RendezVous::firstOrFail();
        $reponse->assertRedirect(route('planning.show', $rdv));

        $this->assertSame('2026-10-06 10:00', $rdv->debut->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 11:00', $rdv->fin->format('Y-m-d H:i'));
        $this->assertSame(1, $rdv->rappel_client_jours);

        $this->get(route('planning.index'))->assertOk()
            ->assertSee('Visite pour devis')
            ->assertSee('10h00 – 11h00')
            ->assertSee('Testeur');
        $this->get(route('planning.index', ['vue' => 'mois']))->assertOk()->assertSee('Visite pour')->assertSee('Octobre 2026');
        $this->get(route('planning.show', $rdv))->assertOk()->assertSee('Itinéraire')->assertSee('1 rue du Test');
    }

    public function test_chantier_sur_plusieurs_jours_et_controles(): void
    {
        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->actingAs($this->user);

        $this->post(route('planning.store'), [
            'type' => 'chantier', 'titre' => 'Réfection', 'date_debut' => '2026-10-07', 'date_fin' => '2026-10-09',
        ])->assertSessionHasNoErrors();

        $chantier = RendezVous::firstOrFail();
        $this->assertTrue($chantier->journee_entiere);
        $this->assertSame('Du 07/10 au 09/10', $chantier->horaire());

        // Visible chacun des trois jours de la semaine.
        $page = $this->get(route('planning.index'))->getContent();
        $this->assertSame(3, substr_count($page, 'Réfection'));

        $this->post(route('planning.store'), ['type' => 'rdv', 'titre' => 'X', 'date_debut' => '2026-10-09', 'date_fin' => '2026-10-08', 'heure_debut' => '10:00', 'heure_fin' => '09:00'])
            ->assertSessionHasErrors('date_fin');
        $this->post(route('planning.store'), ['type' => 'autre', 'titre' => '', 'date_debut' => 'demain'])
            ->assertSessionHasErrors(['type', 'titre', 'date_debut']);
    }

    public function test_devis_acceptes_a_planifier(): void
    {
        $this->actingAs($this->user);
        $devis = Devis::create(['client_id' => $this->client->id, 'objet' => 'Toiture complète', 'created_by' => $this->user->id]);
        $devis->forceFill(['statut' => Devis::ACCEPTE, 'accepte_at' => now()])->save();

        $this->get(route('planning.a-planifier'))->assertOk()->assertSee('Toiture complète');
        $this->get(route('planning.create', ['devis' => $devis->id]))->assertOk()->assertSee('Planifier le chantier')->assertSee('value="Toiture complète"', false);

        $this->post(route('planning.store'), [
            'type' => 'chantier', 'titre' => 'Toiture complète', 'date_debut' => now()->addDays(3)->toDateString(),
            'devis_id' => $devis->id, 'client_id' => $this->client->id,
        ])->assertSessionHasNoErrors();

        $this->get(route('planning.a-planifier'))->assertSee('Rien à planifier');
    }

    public function test_modifier_marquer_fait_provenance_et_corbeille(): void
    {
        $this->actingAs($this->user);
        $rdv = RendezVous::create(['titre' => 'Visite', 'debut' => now()->addDay()->setTime(9, 0), 'fin' => now()->addDay()->setTime(10, 0), 'client_id' => $this->client->id]);
        $rdv->noterRappel('veille');

        $this->put(route('planning.update', $rdv), ['type' => 'rdv', 'titre' => 'Visite décalée', 'date_debut' => now()->addDays(2)->toDateString(), 'heure_debut' => '14:00', 'client_id' => $this->client->id])
            ->assertRedirect(route('planning.show', $rdv));
        $rdv->refresh();
        $this->assertSame('Visite décalée', $rdv->titre);
        $this->assertFalse($rdv->rappelEnvoye('veille'), 'Une nouvelle date relance les rappels.');

        $this->post(route('planning.fait', $rdv));
        $this->assertTrue($rdv->fresh()->fait);

        $this->post(route('planning.provenance', $rdv), ['provenance' => 'Bouche-à-oreille', 'provenance_detail' => 'Voisin'])->assertSessionHasNoErrors();
        $this->assertSame('Bouche-à-oreille', $this->client->fresh()->provenance);

        $this->delete(route('planning.destroy', $rdv));
        $this->assertSoftDeleted($rdv);
        $this->get(route('corbeille'))->assertSee('Visite décalée');
    }

    public function test_export_ics(): void
    {
        $this->actingAs($this->user);
        $rdv = RendezVous::create([
            'titre' => 'Visite, toit; fuite', 'debut' => Carbon::parse('2026-10-06 10:00'), 'fin' => Carbon::parse('2026-10-06 11:00'),
            'client_id' => $this->client->id, 'notes' => "Ligne 1\nLigne 2",
        ]);

        $reponse = $this->get(route('planning.ics', $rdv))->assertOk();
        $this->assertStringStartsWith('text/calendar', $reponse->headers->get('Content-Type'));
        $ics = $reponse->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART:20261006T080000Z', $ics); // 10 h à Paris = 8 h UTC
        $this->assertStringContainsString('SUMMARY:Visite\, toit\; fuite', $ics);
        $this->assertStringContainsString('Ligne 1\nLigne 2', $ics);
        foreach (explode("\r\n", $ics) as $ligne) {
            $this->assertLessThanOrEqual(75, strlen($ligne));
        }

        $this->get(route('planning.exporter'))->assertOk();
    }

    public function test_rappels_au_telephone_et_email_au_client(): void
    {
        Mail::fake();
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        $rdv = RendezVous::create([
            'titre' => 'Visite', 'debut' => Carbon::parse('2026-10-08 10:00'), 'fin' => Carbon::parse('2026-10-08 11:00'),
            'client_id' => $this->client->id, 'user_id' => $this->user->id, 'rappel_client_jours' => 2,
        ]);

        Carbon::setTestNow('2026-10-06 08:55:00');
        $this->artisan('app:rappels-planning')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->artisan('app:rappels-planning');
        Mail::assertSent(EmailDocument::class, fn (EmailDocument $m) => $m->hasTo('client@exemple.test') && str_contains($m->corps, 'Entreprise Fictive') && str_contains($m->sujet, 'jeudi 8 octobre'));
        $this->assertTrue($rdv->fresh()->rappelEnvoye('client'));

        // Pas de second email.
        $this->artisan('app:rappels-planning');
        Mail::assertSentCount(1);

        Carbon::setTestNow('2026-10-07 19:00:00');
        $this->artisan('app:rappels-planning');
        $this->assertTrue($rdv->fresh()->rappelEnvoye('veille'));

        Carbon::setTestNow('2026-10-08 09:05:00');
        $this->artisan('app:rappels-planning');
        $this->assertTrue($rdv->fresh()->rappelEnvoye('heure'));
    }

    public function test_meteo_en_cache_sans_appel_a_l_affichage(): void
    {
        Carbon::setTestNow('2026-10-05 08:00:00');
        Http::fake([
            'data.geopf.fr/*' => Http::response(['features' => [['geometry' => ['coordinates' => [2.3522, 48.8566]], 'properties' => ['label' => '1 rue du Test']]]]),
            'api.met.no/*' => Http::response(['properties' => ['timeseries' => [
                ['time' => '2026-10-06T10:00:00Z', 'data' => ['instant' => ['details' => ['air_temperature' => -1.4, 'wind_speed' => 15.0]], 'next_1_hours' => ['summary' => ['symbol_code' => 'rain'], 'details' => ['precipitation_amount' => 1.5]]]],
                ['time' => '2026-10-06T11:00:00Z', 'data' => ['instant' => ['details' => ['air_temperature' => 6.2, 'wind_speed' => 5.0]], 'next_1_hours' => ['summary' => ['symbol_code' => 'rain'], 'details' => ['precipitation_amount' => 1.0]]]],
                ['time' => '2026-10-06T12:00:00Z', 'data' => ['instant' => ['details' => ['air_temperature' => 8.0, 'wind_speed' => 4.0]], 'next_6_hours' => ['summary' => ['symbol_code' => 'cloudy'], 'details' => ['precipitation_amount' => 3.0]]]],
                ['time' => '2026-10-06T14:00:00Z', 'data' => ['instant' => ['details' => ['air_temperature' => 7.0, 'wind_speed' => 4.0]], 'next_6_hours' => ['summary' => ['symbol_code' => 'cloudy'], 'details' => ['precipitation_amount' => 9.0]]]],
            ]]]),
        ]);

        $rdv = RendezVous::create(['titre' => 'Visite', 'debut' => Carbon::parse('2026-10-06 10:00'), 'fin' => Carbon::parse('2026-10-06 11:00'), 'client_id' => $this->client->id]);

        $this->artisan('app:meteo')->assertSuccessful();
        $this->artisan('app:meteo')->assertSuccessful(); // deuxième passage : mise à jour, pas de doublon
        $this->assertSame(1, MeteoPrevision::count());

        Http::assertSent(fn (RequeteHttp $r) => str_contains($r->url(), 'api.met.no') && str_contains($r->url(), 'lat=48.86') && $r->hasHeader('User-Agent'));
        $this->assertSame(1, LieuGeocode::count());

        $prevision = MeteoPrevision::firstOrFail();
        $this->assertSame('48.86,2.35', $prevision->point);
        $this->assertSame(55, $prevision->pluie_dixiemes_mm); // 1,5 + 1 + 3 (le bloc de 14 h est déjà couvert)
        $this->assertSame(54, $prevision->vent_max_kmh);
        $this->assertSame(-1, $prevision->temperature_min);
        $this->assertSame(['Pluie (5,5 mm)', 'Vent fort (54 km/h)', 'Gel (-1 °C)'], $prevision->alertes());

        // L'affichage n'appelle plus Internet.
        Http::fake(fn () => throw new \RuntimeException('Aucun appel réseau à l\'affichage'));
        $this->actingAs($this->user)->get(route('planning.index'))->assertOk()->assertSee('Vent fort (54 km/h)');
        $this->get(route('planning.show', $rdv))->assertOk()->assertSee('Météo prévue')->assertSee('Gel (-1 °C)');

        // Adresse introuvable : gardée en cache, pas redemandée.
        $this->assertNull(app(Meteo::class)->situer('adresse inconnue'));
    }

    public function test_notifications_telephone_abonnement(): void
    {
        $this->actingAs($this->user);
        $this->get(route('plus'))->assertOk()->assertSee('Activer les notifications')->assertSee('data-cle="B', false);
        $this->assertNotNull(app(Reglages::class)->get('push.cle_publique'));
        $this->assertTrue(app(Reglages::class)->aSecret('push.cle_privee'));

        $abonnement = ['endpoint' => 'https://push.exemple.test/abc', 'keys' => ['p256dh' => 'BCle', 'auth' => 'secret']];
        $this->postJson(route('push.abonner'), $abonnement)->assertOk();
        $this->assertDatabaseHas('abonnements_push', ['user_id' => $this->user->id, 'endpoint' => 'https://push.exemple.test/abc']);

        $this->postJson(route('push.abonner'), ['endpoint' => 'http://pas-https.test', 'keys' => []])->assertUnprocessable();

        $this->deleteJson(route('push.desabonner'), ['endpoint' => 'https://push.exemple.test/abc'])->assertOk();
        $this->assertDatabaseCount('abonnements_push', 0);
    }

    public function test_le_commercial_a_acces_au_planning(): void
    {
        $commercial = User::factory()->create(['role' => User::ROLE_COMMERCIAL]);
        $this->actingAs($commercial)->get(route('planning.index'))->assertOk();
        $this->get(route('nouveau'))->assertSee(route('planning.create'), false);
    }
}
