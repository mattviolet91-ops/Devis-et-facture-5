<?php

namespace Tests\Feature;

use App\Mail\EmailDeTest;
use App\Models\User;
use App\Rules\TvaIntracom;
use App\Services\Numerotation;
use App\Services\PdfExemple;
use App\Support\Configuration;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\ControlesTest;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configurationTerminee = false;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gerant = User::factory()->gerant()->create();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ecransValides(): array
    {
        $siret = ControlesTest::siretFictif();

        return [
            'metier' => ['metier' => 'couvreur'],
            'identite' => [
                'identite__nom_commercial' => 'Entreprise Fictive', 'identite__forme_juridique' => 'EURL',
                'identite__siret' => $siret, 'identite__adresse' => '1 rue de l\'Exemple', 'identite__code_postal' => '00000',
                'identite__ville' => 'Ville-Test', 'identite__telephone' => '01 00 00 00 00', 'identite__email' => 'contact@fictive.test',
            ],
            'tva' => ['tva__regime' => 'franchise', 'tva__taux_defaut' => '0'],
            'assurance' => [
                'assurance__assureur' => 'Assureur Fictif', 'assurance__numero_contrat' => 'X-1',
                'assurance__date_debut' => '2026-01-01', 'assurance__date_fin' => '2027-01-01', 'assurance__activites' => 'Couverture',
            ],
            'documents' => [
                'numerotation__devis_prefixe' => 'DEV', 'numerotation__devis_premier' => '1',
                'numerotation__facture_prefixe' => 'FAC', 'numerotation__facture_premier' => '1',
                'numerotation__avoir_prefixe' => 'AV', 'numerotation__avoir_premier' => '1',
                'documents__validite_devis_jours' => '30', 'documents__delai_paiement_jours' => '30', 'documents__acompte_pourcentage' => '30',
                'documents__iban' => 'FR7630006000011234567890189', 'documents__bic' => 'AGRIFRPP',
                'identite__mediateur_nom' => 'Médiateur Fictif',
            ],
            'cgv' => ['documents__cgv' => 'CGV relues.', 'cgv_relues' => '1'],
        ];
    }

    private function remplirTout(): void
    {
        foreach ($this->ecransValides() as $etape => $donnees) {
            $this->actingAs($this->gerant)->put("/configuration/{$etape}", $donnees)->assertSessionHasNoErrors();
        }
    }

    public function test_l_application_est_bloquee_avant_la_configuration(): void
    {
        $this->actingAs($this->gerant);

        foreach (['/accueil', '/plus', '/reglages', '/reglages/entreprise', '/journal', '/nouveau'] as $page) {
            $this->get($page)->assertRedirect('/configuration/metier');
        }

        $this->get('/configuration')->assertRedirect('/configuration/metier');
        $this->get('/configuration/metier')->assertOk()->assertSee('Étape 1 sur 9')->assertSee('Couvreur');

        // La déconnexion reste possible.
        $this->post('/deconnexion')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_le_commercial_attend_le_gerant(): void
    {
        $commercial = User::factory()->create();

        $this->actingAs($commercial)->get('/accueil')
            ->assertOk()
            ->assertSee('L\'application est en cours de configuration par le gérant.', false);

        $this->get('/configuration/metier')->assertForbidden();
        $this->put('/configuration/metier', ['metier' => 'couvreur'])->assertForbidden();
        $this->assertFalse(Configuration::estFaite('metier'));
    }

    public function test_le_metier_charge_les_cgv_et_les_modules(): void
    {
        $this->actingAs($this->gerant)->put('/configuration/metier', ['metier' => 'couvreur'])
            ->assertRedirect('/configuration/identite');

        $this->assertSame('couvreur', reglage('entreprise.metier'));
        $this->assertContains('calculateur_toiture', reglage('modules.actifs'));
        $this->assertSame('couvreur', reglage('catalogue.depart_a_charger'));
        $this->assertStringContainsString('Travaux de toiture', reglage('documents.cgv'));
        $this->assertStringContainsString('Droit de rétractation', reglage('documents.cgv'));

        $this->put('/configuration/metier', ['metier' => 'peintre', 'modules' => ['photos']]);
        $this->assertSame(['photos'], reglage('modules.actifs'));
        $this->assertStringContainsString('Peinture', reglage('documents.cgv'));

        $this->put('/configuration/metier', [])->assertSessionHasErrors(['metier' => 'Choisissez votre métier.']);
        $this->put('/configuration/metier', ['metier' => 'astronaute'])->assertSessionHasErrors('metier');
    }

    public function test_reprise_d_un_menu_commence(): void
    {
        $ecrans = $this->ecransValides();
        $this->actingAs($this->gerant)->put('/configuration/metier', $ecrans['metier']);
        $this->put('/configuration/identite', $ecrans['identite'])->assertRedirect('/configuration/tva');

        // On s'arrête… puis on revient : retour au premier écran pas encore fait.
        $this->get('/accueil')->assertRedirect('/configuration/tva');
        $this->get('/configuration')->assertRedirect('/configuration/tva');

        // On peut revenir en arrière : les valeurs sont gardées.
        $this->get('/configuration/identite')
            ->assertOk()
            ->assertSee('value="Entreprise Fictive"', false)
            ->assertSee('href="'.route('configuration.etape', 'metier').'" class="retour"', false);
    }

    public function test_controle_du_siret(): void
    {
        $donnees = $this->ecransValides()['identite'];

        $this->actingAs($this->gerant)
            ->put('/configuration/identite', array_merge($donnees, ['identite__siret' => '12345678901234']))
            ->assertSessionHasErrors('identite__siret');
        $this->assertFalse(Configuration::estFaite('identite'));

        $this->put('/configuration/identite', array_merge($donnees, ['identite__forme_juridique' => '']))
            ->assertSessionHasErrors(['identite__forme_juridique' => 'Remplissez « Forme juridique ».']);
    }

    public function test_controle_de_l_iban_et_du_mediateur(): void
    {
        $donnees = $this->ecransValides()['documents'];

        $this->actingAs($this->gerant)
            ->put('/configuration/documents', array_merge($donnees, ['documents__iban' => 'FR76 1234']))
            ->assertSessionHasErrors('documents__iban');

        $this->put('/configuration/documents', array_merge($donnees, ['documents__iban' => '', 'identite__mediateur_nom' => '']))
            ->assertSessionHasErrors(['documents__iban' => 'Remplissez « IBAN ».', 'identite__mediateur_nom']);

        $this->put('/configuration/documents', array_merge($donnees, ['numerotation__facture_premier' => '231']))
            ->assertSessionHasNoErrors();
        $this->assertSame('FAC-'.now()->year.'-0231', app(Numerotation::class)->prochain('facture'));
    }

    public function test_tva_assujetti_exige_le_numero_intracommunautaire(): void
    {
        $this->actingAs($this->gerant)
            ->put('/configuration/tva', ['tva__regime' => 'assujetti', 'tva__taux_defaut' => '1000'])
            ->assertSessionHasErrors('identite__tva_intracom');

        $siret = ControlesTest::siretFictif();
        app(Reglages::class)->set('identite.siret', $siret);
        $siren = substr($siret, 0, 9);

        $this->put('/configuration/tva', ['tva__regime' => 'assujetti', 'identite__tva_intracom' => 'FR'.TvaIntracom::cle($siren).$siren, 'tva__taux_defaut' => '1000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1000, reglage('tva.taux_defaut'));
    }

    public function test_assurance_et_attestation(): void
    {
        $this->actingAs($this->gerant)->put('/configuration/assurance', [])
            ->assertSessionHasErrors(['assurance__assureur', 'assurance__numero_contrat', 'assurance__date_fin']);

        $this->put('/configuration/assurance', array_merge($this->ecransValides()['assurance'], [
            'assurance__attestation' => UploadedFile::fake()->create('attestation.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->get('/configuration/assurance')->assertSee("Voir l'attestation enregistrée", false);
        $this->get('/reglages/assurance/attestation')->assertOk();
    }

    public function test_les_cgv_doivent_etre_relues(): void
    {
        $this->actingAs($this->gerant)->put('/configuration/metier', ['metier' => 'couvreur']);

        $this->get('/configuration/cgv')->assertOk()->assertSee('Travaux de toiture')->assertSee("J'ai relu mes CGV", false);

        $this->put('/configuration/cgv', ['documents__cgv' => 'Mes CGV'])
            ->assertSessionHasErrors(['cgv_relues' => 'Cochez « J\'ai relu mes CGV » après les avoir lues et adaptées.']);

        $this->put('/configuration/cgv', ['documents__cgv' => 'Mes CGV', 'cgv_relues' => '1'])->assertRedirect('/configuration/recapitulatif');
        $this->assertSame('Mes CGV', reglage('documents.cgv'));
    }

    public function test_les_emails_et_l_apparence_peuvent_attendre(): void
    {
        $this->actingAs($this->gerant)->post('/configuration/emails/plus-tard')->assertRedirect('/configuration/cgv');
        $this->post('/configuration/apparence/plus-tard')->assertRedirect('/configuration/emails');
        $this->post('/configuration/identite/plus-tard')->assertNotFound();
        $this->assertFalse(Configuration::estFaite('emails'));
    }

    public function test_email_de_test_pendant_la_configuration(): void
    {
        Mail::fake();
        $this->actingAs($this->gerant)->put('/configuration/emails', ['emails__adresse' => 'entreprise.fictive@gmail.test', 'emails__mot_de_passe' => 'abcdefghijklmnop'])
            ->assertRedirect('/configuration/cgv');

        $this->from('/configuration/emails')->post('/configuration/emails/test')->assertRedirect('/configuration/emails')->assertSessionHas('statut');
        Mail::assertSent(EmailDeTest::class);
    }

    public function test_terminer_refuse_s_il_manque_des_ecrans(): void
    {
        $this->actingAs($this->gerant)->put('/configuration/metier', ['metier' => 'couvreur']);

        $this->from('/configuration/recapitulatif')->post('/configuration/terminer')
            ->assertRedirect('/configuration/recapitulatif')
            ->assertSessionHasErrors(['terminer' => 'Il reste à remplir : « Votre entreprise », « TVA », « Assurance décennale », « Devis et factures », « Conditions générales de vente ».']);

        $this->assertFalse(Configuration::estTerminee());
        $this->get('/configuration/recapitulatif')->assertSee('À remplir');
    }

    public function test_acces_debloque_apres_la_fin(): void
    {
        $this->remplirTout();
        $this->assertFalse(Configuration::accesClientsActif());

        $this->get('/configuration/recapitulatif')->assertOk()->assertSee("Voir le devis d'exemple (PDF)", false)->assertDontSee('À remplir');
        $this->post('/configuration/terminer')->assertRedirect('/accueil');

        $this->assertTrue(Configuration::estTerminee());
        $this->assertTrue(Configuration::accesClientsActif());
        $this->get('/accueil')->assertOk()->assertDontSee('Configuration à terminer');
        $this->get('/reglages/entreprise')->assertOk()->assertSee('Entreprise Fictive');
        $this->assertDatabaseHas('activites', ['action' => 'configuration.terminee']);

        // Le commercial peut maintenant travailler.
        $this->actingAs(User::factory()->create())->get('/accueil')->assertOk()->assertDontSee('en cours de configuration');
    }

    public function test_passer_la_configuration(): void
    {
        $this->actingAs($this->gerant)->post('/configuration/passer')->assertRedirect('/accueil');

        $this->assertTrue(Configuration::estPassee());
        $this->assertFalse(Configuration::estTerminee());
        $this->assertFalse(Configuration::accesClientsActif());

        $this->get('/accueil')->assertOk()->assertSee('Configuration à terminer')->assertSee('Continuer la configuration');
        $this->get('/reglages')->assertOk();
        $this->get('/configuration')->assertRedirect('/configuration/metier');

        $this->actingAs(User::factory()->create())->get('/accueil')->assertOk()->assertDontSee('Configuration à terminer');
    }

    public function test_devis_d_exemple_en_pdf(): void
    {
        $this->remplirTout();

        $reponse = $this->get('/configuration/devis-exemple.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
        $this->assertGreaterThan(5000, strlen($reponse->getContent()));

        // Contenu : mentions présentes, apostrophes non abîmées.
        $html = app(PdfExemple::class)->html();
        $this->assertStringContainsString('1 rue de l&#039;Exemple', $html);
        $this->assertStringNotContainsString('&amp;#039;', $html);
        $this->assertStringContainsString('TVA non applicable, art. 293 B du CGI', $html);
        $this->assertStringContainsString('Bon pour accord', $html);
        $this->assertStringContainsString('Assurance décennale : Assureur Fictif', $html);
        $this->assertStringContainsString('Médiateur de la consommation : Médiateur Fictif', $html);
        $this->assertStringContainsString('Démoussage de toiture', $html);
    }

    public function test_barre_de_progression_et_ecran_inconnu(): void
    {
        $this->actingAs($this->gerant)->get('/configuration/documents')
            ->assertSee('Étape 5 sur 9')
            ->assertSee('<progress max="9" value="5"', false)
            ->assertSee('aria-current="step"', false);

        $this->get('/configuration/inconnu')->assertNotFound();
        $this->put('/configuration/recapitulatif', [])->assertNotFound();
    }

    public function test_aucun_script_ni_style_inline_dans_le_menu(): void
    {
        $this->actingAs($this->gerant);
        $pages = array_map(fn ($e) => "/configuration/{$e}", array_keys(Configuration::ETAPES));
        $pages[] = '/accueil';

        foreach ($pages as $page) {
            $contenu = $this->followingRedirects()->get($page)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu, $page);
        }
    }
}
