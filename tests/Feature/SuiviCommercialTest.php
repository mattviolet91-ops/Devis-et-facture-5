<?php

namespace Tests\Feature;

use App\Mail\EmailDocument;
use App\Models\Client;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\EmailRecu;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Services\BoiteEmails;
use App\Services\GestionDevis;
use App\Services\LectureDemandes;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuiviCommercialTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
        $this->client = Client::factory()->create(['email' => 'client@exemple.test', 'telephone' => '0600000000']);
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function devisEnvoye(): Devis
    {
        $gestion = app(GestionDevis::class);
        $devis = $gestion->creer($this->client, null, 'Toiture', $this->gerant->id);
        $gestion->remplacerLignes($devis, [['type' => 'ligne', 'designation' => 'Tuiles', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 10000]]);
        $gestion->marquerEnvoye($devis);

        return $devis->fresh();
    }

    public function test_relances_de_devis_a_7_et_15_jours(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-01 10:00');
        $devis = $this->devisEnvoye();

        Carbon::setTestNow('2026-10-07 10:00');
        $this->artisan('app:relancer-devis')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow('2026-10-08 10:00');
        $this->artisan('app:relancer-devis');
        Mail::assertSent(EmailDocument::class, fn (EmailDocument $m) => $m->hasTo('client@exemple.test') && str_contains($m->corps, '/c/'));
        $this->assertSame(1, $devis->fresh()->relances);

        $this->artisan('app:relancer-devis');
        Mail::assertSentCount(1);

        Carbon::setTestNow('2026-10-16 10:00');
        $this->artisan('app:relancer-devis');
        Mail::assertSentCount(2);

        Carbon::setTestNow('2026-10-25 10:00');
        $this->artisan('app:relancer-devis');
        Mail::assertSentCount(2);
        $this->assertSame(2, $devis->fresh()->relances);
    }

    public function test_relances_coupees_dans_les_reglages(): void
    {
        Mail::fake();
        app(Reglages::class)->set('suivi.relances_devis', false);
        Carbon::setTestNow('2026-10-01 10:00');
        $this->devisEnvoye();
        Carbon::setTestNow('2026-10-09 10:00');
        $this->artisan('app:relancer-devis');
        Mail::assertNothingSent();
    }

    public function test_ecran_de_suivi(): void
    {
        Carbon::setTestNow('2026-10-01 10:00');
        $devis = $this->devisEnvoye();
        Carbon::setTestNow('2026-10-10 10:00');
        app(Reglages::class)->set('suivi.lien_avis', 'https://g.page/r/exemple/review');

        // Chantier fini récemment → avis ; chantier vieux de 13 mois → entretien.
        RendezVous::create(['type' => 'chantier', 'titre' => 'Chantier', 'debut' => now()->subDays(5), 'fin' => now()->subDays(4), 'client_id' => $this->client->id, 'fait' => true]);
        $ancien = Client::factory()->create(['nom' => 'Ancien']);
        RendezVous::create(['type' => 'chantier', 'titre' => 'Vieux chantier', 'debut' => now()->subMonths(13), 'fin' => now()->subMonths(13), 'client_id' => $ancien->id, 'fait' => true]);

        $this->actingAs($this->gerant)->get(route('suivi'))->assertOk()
            ->assertSee('Devis sans réponse (1)')
            ->assertSee($devis->reference())
            ->assertSee('Demander un avis Google (1)')
            ->assertSee('Entretien à proposer (1)')
            ->assertSee('Ancien');

        $this->get(route('envoi.create', ['devis', $devis->id, 'modele' => 'relance_devis']))->assertOk()->assertSee('Nous revenons vers vous');

        Mail::fake();
        $this->post(route('suivi.avis', $this->client))->assertSessionHas('statut');
        Mail::assertSent(EmailDocument::class, fn (EmailDocument $m) => str_contains($m->corps, 'https://g.page/r/exemple/review'));
        $this->assertNotNull($this->client->fresh()->avis_demande_at);

        $this->post(route('suivi.entretien', $ancien));
        $this->get(route('suivi'))->assertSee('Demander un avis Google (0)')->assertSee('Entretien à proposer (0)');
    }

    public function test_formulaire_public_et_champ_piege(): void
    {
        Notification::fake();
        $this->get('/demande')->assertNotFound();
        app(Reglages::class)->set('suivi.formulaire_actif', true);

        app(Reglages::class)->oublier('setup.completed_at');
        $this->get('/demande')->assertStatus(503);
        app(Reglages::class)->set('setup.completed_at', now()->toIso8601String());
        $this->get('/demande')->assertOk()->assertSee('Demander un devis')->assertSee('name="site_web"', false);

        $horodatage = Crypt::encryptString((string) now()->subSeconds(30)->timestamp);
        $donnees = ['nom' => 'Jean Exemple', 'telephone' => '06 00 00 00 01', 'ville' => 'Ville-Test', 'message' => 'Fuite', 'accord' => '1', 'horodatage' => $horodatage];

        // Robot : champ piège rempli → ignoré sans le dire.
        $this->post('/demande', $donnees + ['site_web' => 'http://spam.test'])->assertRedirect('/demande/merci');
        // Robot trop rapide.
        $this->post('/demande', ['horodatage' => Crypt::encryptString((string) now()->timestamp)] + $donnees)->assertRedirect('/demande/merci');
        $this->assertSame(0, Demande::count());

        $this->post('/demande', array_merge($donnees, ['telephone' => '', 'accord' => '']))->assertSessionHasErrors(['telephone', 'accord']);

        Storage::fake('local');
        $image = imagecreatetruecolor(100, 80);
        ob_start();
        imagejpeg($image);
        $photo = UploadedFile::fake()->createWithContent('toit.jpg', ob_get_clean());
        $this->post('/demande', $donnees + ['photos' => [$photo], 'code_postal' => '00100'])->assertRedirect('/demande/merci');
        $demande = Demande::firstOrFail();
        $this->assertCount(1, $demande->photos);
        $this->assertSame('0600000001', $demande->telephone);
        Notification::assertSentTo($this->gerant, AlerteDocument::class);

        // Traitement : créer le client.
        $this->actingAs($this->gerant)->get(route('suivi'))->assertSee('Nouvelles demandes (1)')->assertSee('Jean Exemple');
        $this->post(route('suivi.demande.client', $demande))->assertRedirect();
        $client = Client::where('nom', 'Exemple')->firstOrFail();
        $this->assertSame('Jean', $client->prenom);
        $this->assertSame('Site internet', $client->provenance);
        $this->assertSame('traitee', $demande->fresh()->statut);
        $this->assertSame('00100', $client->code_postal);
        $this->assertSame(1, $client->photos()->where('moment', 'probleme')->count());
    }

    public function test_analyse_d_un_email_wordpress(): void
    {
        $texte = "De : Marie Durand <marie@exemple.test>\nObjet : Devis toiture\n\nNom : Marie Durand\nE-mail : Marie@Exemple.test\nTéléphone : 06 11 22 33 44\nVille : Village-Test\nMessage : Bonjour,\nj'ai une fuite.\nMerci\n\n--\nCet e-mail a été envoyé via le formulaire de contact de Site (WordPress)";
        $resultat = LectureDemandes::analyser($texte);

        $this->assertSame('Marie Durand', $resultat['nom']);
        $this->assertSame('marie@exemple.test', $resultat['email']);
        $this->assertSame('0611223344', $resultat['telephone']);
        $this->assertSame('Village-Test', $resultat['ville']);
        $this->assertSame("Bonjour,\nj'ai une fuite.\nMerci", $resultat['message']);
    }

    public function test_analyse_format_wpforms_et_pieges(): void
    {
        $texte = "Nom\nPaul Martin\n\nAdresse e-mail\npaul@exemple.test\n\nNombre de pièces : 4\n\nAdresse des travaux : 3 rue du Test\nCode postal : 00100\nVille : Ville-Test\n\nTravaux\nRemplacer la gouttière";
        $resultat = LectureDemandes::analyser($texte);

        $this->assertSame('Paul Martin', $resultat['nom']);
        $this->assertSame('paul@exemple.test', $resultat['email']);
        $this->assertSame('3 rue du Test', $resultat['adresse']);
        $this->assertSame('00100', $resultat['code_postal']);
        $this->assertSame('Ville-Test', $resultat['ville']);
        $this->assertSame('Remplacer la gouttière', $resultat['message']);
    }

    public function test_lecture_des_emails_sans_doublon(): void
    {
        Notification::fake();
        app(Reglages::class)->set('suivi.imap_actif', true);
        app(Reglages::class)->set('emails.adresse', 'entreprise@exemple.test');
        app(Reglages::class)->setSecret('emails.mot_de_passe', 'mot-de-passe-test');

        $emails = [
            ['message_id' => 'a@site', 'expediteur' => 'WordPress', 'expediteur_email' => 'wordpress@site.test', 'sujet' => 'Nouveau message', 'texte' => "Nom : Paul\nTéléphone : 0600000009\nMessage : Gouttière", 'date' => now()],
            ['message_id' => 'b@site', 'expediteur' => 'Banque', 'expediteur_email' => 'info@banque.test', 'sujet' => 'Relevé', 'texte' => 'Votre relevé', 'date' => now()->subHour()],
        ];
        $this->mock(BoiteEmails::class, function ($mock) use ($emails) {
            $mock->shouldReceive('estConfiguree')->andReturn(true);
            $mock->shouldReceive('derniers')->andReturn($emails);
        });

        $this->artisan('app:lire-emails')->expectsOutputToContain('1 nouvelle(s) demande(s)')->assertSuccessful();
        $this->artisan('app:lire-emails')->expectsOutputToContain('0 nouvelle(s) demande(s)');

        $this->assertSame(2, EmailRecu::count());
        $this->assertSame(1, Demande::count());
        $this->assertSame('Paul', Demande::first()->nom);

        $this->actingAs($this->gerant)->get(route('suivi.emails'))->assertOk()->assertSee('Nouveau message')->assertSee('Relevé');

        // Vérification sans rien créer.
        Demande::query()->delete();
        $this->post(route('suivi.emails.verifier'))->assertOk()->assertSee('Repéré comme demande')->assertSee('Ignoré')->assertSee('Tél. : 0600000009');
        $this->assertSame(0, Demande::count());
        $commercial = User::factory()->create();
        $this->actingAs($commercial)->get(route('suivi.emails'))->assertForbidden();
        $this->get(route('suivi'))->assertOk()->assertDontSee('Voir mes derniers emails');
    }
}
