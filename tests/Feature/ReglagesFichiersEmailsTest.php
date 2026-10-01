<?php

namespace Tests\Feature;

use App\Mail\EmailDeTest;
use App\Models\Setting;
use App\Models\User;
use App\Services\ConfigurationEmail;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReglagesFichiersEmailsTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gerant = User::factory()->gerant()->create(['email' => 'gerant@exemple.test']);
    }

    private function apparence(array $modifs = []): array
    {
        return array_merge(['apparence__couleur_principale' => '#1f4e79', 'apparence__couleur_accent' => '#c25e00', 'apparence__police' => 'systeme'], $modifs);
    }

    public function test_logo_enregistre_et_servi(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/apparence', $this->apparence(['apparence__logo' => UploadedFile::fake()->image('logo.png', 400, 120)]))
            ->assertSessionHasNoErrors();

        $chemin = reglage('apparence.logo');
        Storage::disk('local')->assertExists($chemin);
        $this->assertStringStartsWith('reglages/', $chemin);

        $this->get('/fichiers/logo')->assertOk();
        $this->get('/reglages/apparence')->assertSee('Logo actuel')->assertSee('Retirer ce fichier');

        $this->put('/reglages/apparence', $this->apparence(['apparence__logo__effacer' => '1']))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($chemin);
        $this->get('/fichiers/logo')->assertNotFound();
    }

    public function test_un_faux_logo_est_refuse(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/apparence', $this->apparence(['apparence__logo' => UploadedFile::fake()->create('logo.php', 10, 'text/x-php')]))
            ->assertSessionHasErrors('apparence__logo');
    }

    public function test_icone_redimensionnee_pour_le_telephone(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/apparence', $this->apparence(['apparence__icone' => UploadedFile::fake()->image('icone.png', 600, 600)]))
            ->assertSessionHasNoErrors();

        foreach ([180, 192, 512] as $taille) {
            Storage::disk('local')->assertExists("reglages/icone-{$taille}.png");
            [$largeur] = getimagesizefromstring(Storage::disk('local')->get("reglages/icone-{$taille}.png"));
            $this->assertSame($taille, $largeur);
        }

        $this->get('/fichiers/icone-512.png')->assertOk();
        $this->get('/manifest.webmanifest')->assertJsonPath('icons.0.src', '/fichiers/icone-192.png');
        $this->get('/fichiers/icone-64.png')->assertNotFound();
    }

    public function test_icone_qui_n_est_pas_carree_refusee(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/apparence', $this->apparence(['apparence__icone' => UploadedFile::fake()->image('icone.png', 600, 300)]))
            ->assertSessionHasErrors(['apparence__icone' => 'L\'image doit être carrée et faire au moins 192 × 192 pixels (512 × 512 conseillé).']);
    }

    public function test_attestation_d_assurance_reservee_au_gerant(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/assurance', ['assurance__alerte_jours' => '30', 'assurance__attestation' => UploadedFile::fake()->create('attestation.pdf', 200, 'application/pdf')])
            ->assertSessionHasNoErrors();

        $this->get('/reglages/assurance/attestation')->assertOk();

        $this->actingAs(User::factory()->create())->get('/reglages/assurance/attestation')->assertForbidden();
        auth()->logout();
        $this->get('/reglages/assurance/attestation')->assertRedirect('/connexion');
    }

    public function test_le_mot_de_passe_email_est_chiffre_et_jamais_reaffiche(): void
    {
        $secret = 'abcdefghijklmnop';

        $this->actingAs($this->gerant)
            ->put('/reglages/emails', ['emails__adresse' => 'entreprise.fictive@gmail.test', 'emails__mot_de_passe' => 'abcd efgh ijkl mnop', 'emails__copie_cachee' => '1'])
            ->assertSessionHasNoErrors();

        $brut = Setting::where('key', 'emails.mot_de_passe')->value('value');
        $this->assertStringNotContainsString($secret, json_encode($brut));
        $this->assertSame($secret, app(Reglages::class)->getSecret('emails.mot_de_passe'));

        $page = $this->get('/reglages/emails')->assertSee('Enregistré (caché)')->getContent();
        $this->assertStringNotContainsString($secret, $page);

        // Laisser vide ne change rien.
        $this->put('/reglages/emails', ['emails__adresse' => 'entreprise.fictive@gmail.test', 'emails__mot_de_passe' => ''])->assertSessionHasNoErrors();
        $this->assertSame($secret, app(Reglages::class)->getSecret('emails.mot_de_passe'));

        // « Effacer » le retire.
        $this->put('/reglages/emails', ['emails__adresse' => 'entreprise.fictive@gmail.test', 'emails__mot_de_passe__effacer' => '1']);
        $this->assertNull(app(Reglages::class)->getSecret('emails.mot_de_passe'));

        $this->assertDatabaseMissing('activites', ['description' => $secret]);
    }

    public function test_email_de_test(): void
    {
        Mail::fake();

        $this->actingAs($this->gerant)->post('/reglages/emails/test')
            ->assertSessionHasErrors('test');
        Mail::assertNothingSent();

        app(Reglages::class)->set('emails.adresse', 'entreprise.fictive@gmail.test');
        app(Reglages::class)->setSecret('emails.mot_de_passe', 'abcdefghijklmnop');

        $this->post('/reglages/emails/test')->assertSessionHas('statut');

        Mail::assertSent(EmailDeTest::class, fn ($mail) => $mail->hasTo('gerant@exemple.test'));
    }

    public function test_configuration_gmail_appliquee(): void
    {
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        app(Reglages::class)->set('emails.adresse', 'entreprise.fictive@gmail.test');
        app(Reglages::class)->setSecret('emails.mot_de_passe', 'abcdefghijklmnop');

        app(ConfigurationEmail::class)->appliquer();

        $this->assertSame('smtp.gmail.com', config('mail.mailers.gmail.host'));
        $this->assertSame(587, config('mail.mailers.gmail.port'));
        $this->assertSame('abcdefghijklmnop', config('mail.mailers.gmail.password'));
        $this->assertSame(['address' => 'entreprise.fictive@gmail.test', 'name' => 'Entreprise Fictive'], config('mail.from'));
        // Pendant les tests, rien ne part réellement.
        $this->assertSame('array', config('mail.default'));
    }

    public function test_textes_types(): void
    {
        $this->actingAs($this->gerant)->get('/reglages/textes-types')->assertSee('Aucun texte type');

        $this->post('/reglages/textes-types', ['titre' => 'Accès', 'texte' => 'Accès au toit par échafaudage.'])->assertRedirect('/reglages/textes-types');
        $this->post('/reglages/textes-types', ['titre' => '', 'texte' => ''])->assertSessionHasErrors(['titre' => 'Remplissez « titre ».']);
        $this->post('/reglages/textes-types', ['titre' => 'Garantie', 'texte' => 'Garantie décennale.']);

        $this->put('/reglages/textes-types/0', ['titre' => 'Accès chantier', 'texte' => 'Nacelle.'])->assertSessionHas('statut');
        $this->delete('/reglages/textes-types/1')->assertSessionHas('statut');
        $this->delete('/reglages/textes-types/5')->assertNotFound();

        $this->assertSame([['titre' => 'Accès chantier', 'texte' => 'Nacelle.']], reglage('textes_types'));
        $this->get('/reglages/textes-types')->assertSee('Accès chantier');
    }
}
