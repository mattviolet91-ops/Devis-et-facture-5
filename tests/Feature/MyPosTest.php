<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\LienClient;
use App\Models\Paiement;
use App\Models\PaiementEnLigne;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Services\Encaissements;
use App\Services\MyPos;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyPosTest extends TestCase
{
    use RefreshDatabase;

    private static array $cles = [];

    private User $gerant;

    /**
     * Clés RSA de test : celles du marchand (pack) et celles de « myPOS » (notifications).
     *
     * @return array{marchand_prive: string, marchand_public: string, mypos_prive: string, mypos_certificat: string}
     */
    private static function cles(): array
    {
        if (self::$cles) {
            return self::$cles;
        }

        $nouvelle = function (): array {
            $cle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($cle, $prive);
            $csr = openssl_csr_new(['commonName' => 'Test'], $cle);
            openssl_x509_export(openssl_csr_sign($csr, null, $cle, 30), $certificat);

            return [$prive, $certificat];
        };
        [$marchandPrive, $marchandCertificat] = $nouvelle();
        [$myposPrive, $myposCertificat] = $nouvelle();

        return self::$cles = ['marchand_prive' => $marchandPrive, 'marchand_public' => $marchandCertificat, 'mypos_prive' => $myposPrive, 'mypos_certificat' => $myposCertificat];
    }

    private function pack(): string
    {
        $c = self::cles();

        return base64_encode(json_encode(['sid' => '000000000000010', 'cn' => '61938166610', 'pk' => $c['marchand_prive'], 'pc' => $c['mypos_certificat'], 'idx' => 1]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Reglages::class)->set('tva.regime', 'franchise');
        $this->gerant = User::factory()->gerant()->create();
    }

    private function configurer(string $mode): void
    {
        $this->actingAs($this->gerant)->put('/reglages/paiement', ['mypos__mode' => $mode, 'mypos__pack' => $this->pack()])->assertSessionHasNoErrors();
        auth()->logout();
    }

    /**
     * @return array<string, string>
     */
    private function notification(PaiementEnLigne $tentative, ?string $montant = null): array
    {
        $champs = [
            'IPCmethod' => 'IPCPurchaseNotify', 'SID' => '000000000000010', 'Amount' => $montant ?? number_format($tentative->montant / 100, 2, '.', ''),
            'Currency' => 'EUR', 'OrderID' => $tentative->order_id, 'IPC_Trnref' => '123456789', 'RequestSTAN' => '000001', 'RequestDateTime' => '2026-10-01 12:00:00',
        ];
        $champs['Signature'] = MyPos::signer($champs, self::cles()['mypos_prive']);

        return $champs;
    }

    public function test_pack_de_configuration_chiffre_et_jamais_reaffiche(): void
    {
        $this->actingAs($this->gerant)->put('/reglages/paiement', ['mypos__mode' => 'test', 'mypos__pack' => 'nimportequoi'])
            ->assertSessionHasErrors('mypos__pack');

        $this->put('/reglages/paiement', ['mypos__mode' => 'test', 'mypos__pack' => $this->pack()])->assertSessionHasNoErrors();

        $brut = json_encode(Setting::where('key', 'mypos.pack')->value('value'));
        $this->assertStringNotContainsString('PRIVATE KEY', $brut);
        $this->assertStringNotContainsString('61938166610', $brut);
        $page = $this->get('/reglages/paiement')->assertSee('Enregistré (caché)')->getContent();
        $this->assertStringNotContainsString('PRIVATE KEY', $page);
        $this->assertSame('000000000000010', app(MyPos::class)->pack()['sid']);
    }

    public function test_requete_signee_vers_mypos_pour_le_reste_a_payer(): void
    {
        $this->configurer('production');
        $facture = PaiementsTest::factureEmise(150000);
        app(Encaissements::class)->enregistrer($facture, 50000, 'virement', now()->toDateString(), null, null, null);
        $lien = LienClient::pour($facture);

        $this->get('/c/'.$lien->jeton())->assertSee("Payer par carte (1\u{202F}000,00\u{00A0}€)", false);

        $reponse = $this->post('/c/'.$lien->jeton().'/payer')->assertOk()
            ->assertSee('https://www.mypos.com/vmp/checkout"', false)
            ->assertSee('name="Amount" value="1000.00"', false);

        // La page de redirection autorise l'envoi vers myPOS, et seulement elle.
        $this->assertStringContainsString("form-action 'self' https://www.mypos.com", $reponse->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('mypos', $this->get('/c/'.$lien->jeton())->headers->get('Content-Security-Policy'));

        $tentative = PaiementEnLigne::firstOrFail();
        $this->assertSame(100000, $tentative->montant);
        $this->assertFalse($tentative->essai);

        // La signature se vérifie avec la clé publique du marchand.
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $reponse->getContent(), $m, PREG_SET_ORDER);
        $champs = [];
        foreach ($m as [, $nom, $valeur]) {
            $champs[$nom] = html_entity_decode($valeur, ENT_QUOTES);
        }
        $this->assertTrue(MyPos::verifier($champs, self::cles()['marchand_public']));
        $this->assertSame('IPCPurchase', $champs['IPCmethod']);
        $this->assertSame('1.4', $champs['IPCVersion']);
        $this->assertStringEndsWith('/paiement/mypos/notification', $champs['URL_Notify']);
    }

    public function test_notification_verifiee_enregistree_une_seule_fois(): void
    {
        Notification::fake();
        $this->configurer('production');
        $facture = PaiementsTest::factureEmise(150000);
        $lien = LienClient::pour($facture);
        $this->post('/c/'.$lien->jeton().'/payer');
        $tentative = PaiementEnLigne::firstOrFail();

        // Signature falsifiée : refusée.
        $fausse = $this->notification($tentative);
        $fausse['Amount'] = '1.00';
        $this->post('/paiement/mypos/notification', $fausse)->assertStatus(400);

        // Montant différent (même bien signé) : refusé.
        $this->post('/paiement/mypos/notification', $this->notification($tentative, '1.00'))->assertStatus(400);
        $this->assertSame(0, Paiement::count());

        // Notification valable : paiement enregistré, facture payée, gérant prévenu.
        $this->post('/paiement/mypos/notification', $this->notification($tentative))->assertOk()->assertSee('OK');
        $this->assertSame(1, Paiement::count());
        $this->assertSame('carte_en_ligne', Paiement::first()->mode);
        $this->assertSame(Facture::PAYEE, $facture->fresh()->statut);
        Notification::assertSentTo($this->gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Paiement reçu');

        // myPOS renvoie la même notification : rien n'est fait deux fois.
        $this->post('/paiement/mypos/notification', $this->notification($tentative))->assertOk();
        $this->assertSame(1, Paiement::count());
    }

    public function test_mode_test_rien_n_est_enregistre_et_les_clients_ne_voient_pas_le_bouton(): void
    {
        $this->configurer('test');
        $facture = PaiementsTest::factureEmise(150000);
        $lien = LienClient::pour($facture);

        $this->get('/c/'.$lien->jeton())->assertDontSee('Payer par carte');
        $this->post('/c/'.$lien->jeton().'/payer')->assertNotFound();

        // Lien d'essai du gérant : page de test myPOS.
        $this->actingAs($this->gerant)->get(route('factures.show', $facture))->assertSee('Essayer le paiement par carte (mode test)');
        $this->post(route('paiements.essai', $facture))->assertOk()->assertSee('https://www.mypos.com/vmp/checkout-test', false)->assertSee('aucun argent réel');
        $tentative = PaiementEnLigne::firstOrFail();
        $this->assertTrue($tentative->essai);

        $this->post('/paiement/mypos/notification', $this->notification($tentative))->assertOk();
        $this->assertSame('payee', $tentative->fresh()->statut);
        $this->assertSame(0, Paiement::count());
        $this->assertSame(Facture::EMISE, $facture->fresh()->statut);
    }

    public function test_annulation_seulement_pour_la_facture_du_lien(): void
    {
        $this->configurer('production');
        $facture = PaiementsTest::factureEmise(150000);
        $autre = PaiementsTest::factureEmise(80000);
        $this->post('/c/'.LienClient::pour($facture)->jeton().'/payer');
        $this->post('/c/'.LienClient::pour($autre)->jeton().'/payer');
        $tentativeAutre = PaiementEnLigne::where('facture_id', $autre->id)->firstOrFail();

        // Le lien d'une facture ne peut pas annuler le paiement d'une autre.
        $this->get('/c/'.LienClient::pour($facture)->jeton().'/paiement/annule?commande='.$tentativeAutre->order_id)->assertRedirect();
        $this->assertSame('en_attente', $tentativeAutre->fresh()->statut);

        $this->get('/c/'.LienClient::pour($autre)->jeton().'/paiement/annule?commande='.$tentativeAutre->order_id)->assertRedirect();
        $this->assertSame('annulee', $tentativeAutre->fresh()->statut);

        $this->get('/c/'.LienClient::pour($facture)->jeton().'/paiement/merci?commande=x')->assertOk()->assertSee('en cours de confirmation');
    }

    public function test_desactive_sans_pack(): void
    {
        app(Reglages::class)->set('mypos.mode', 'production');
        $this->assertSame('desactive', app(MyPos::class)->mode());
        $facture = PaiementsTest::factureEmise();
        $this->get('/c/'.LienClient::pour($facture)->jeton())->assertDontSee('Payer par carte');
    }
}
