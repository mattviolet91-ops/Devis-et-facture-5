<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\LienClient;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Services\GestionDevis;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureClientTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private Devis $devis;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        $this->gerant = User::factory()->gerant()->create();

        $gestion = app(GestionDevis::class);
        $this->devis = $gestion->creer(Client::factory()->create(['nom' => 'Martin']), null, 'Toiture', $this->gerant->id);
        $gestion->remplacerLignes($this->devis, [['type' => 'ligne', 'designation' => 'Démoussage', 'quantite' => 100000, 'unite' => 'm²', 'prix_unitaire_ht' => 1200, 'taux_tva' => 1000]]);
        $gestion->marquerEnvoye($this->devis);
        $this->devis->refresh();
    }

    private function signatureValide(): string
    {
        $image = imagecreatetruecolor(60, 20);
        imageline($image, 0, 0, 59, 19, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function lien(): LienClient
    {
        return LienClient::pour($this->devis);
    }

    public function test_le_lien_client_est_un_jeton_dont_seule_l_empreinte_est_en_base(): void
    {
        $lien = $this->lien();
        $jeton = $lien->jeton();

        $this->assertSame(40, strlen($jeton));
        $this->assertSame(hash('sha256', $jeton), $lien->jeton_sha256);
        $this->assertDatabaseMissing('liens_clients', ['jeton_sha256' => $jeton]);
        $this->assertStringNotContainsString($jeton, $lien->jeton_chiffre);
        $this->assertSame($lien->id, LienClient::pour($this->devis)->id);
        $this->assertStringEndsWith('/c/'.$jeton, $lien->url());
    }

    public function test_le_client_consulte_et_telecharge_sans_compte(): void
    {
        $lien = $this->lien();

        $this->get('/c/'.$lien->jeton())->assertOk()
            ->assertSee('Devis '.$this->devis->numero)
            ->assertSee('Démoussage')
            ->assertSee('Signer le devis')
            ->assertSee('Demander une modification')
            ->assertSee('Refuser le devis')
            ->assertSee('Entreprise Fictive')
            ->assertDontSee('Menu principal');

        $this->get('/c/'.$lien->jeton().'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(2, $lien->fresh()->acces);

        $this->get('/c/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/c/court')->assertNotFound();
    }

    public function test_signature_au_doigt_par_le_client(): void
    {
        Notification::fake();
        $lien = $this->lien();

        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'Sophie Martin', 'signature' => $this->signatureValide()])
            ->assertSessionHasErrors(['accord' => 'Cochez la case « Bon pour accord » pour signer.']);

        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'Sophie Martin', 'signature' => $this->signatureValide(), 'accord' => '1'], ['REMOTE_ADDR' => '203.0.113.7'])
            ->assertRedirect('/c/'.$lien->jeton());

        $this->devis->refresh();
        $signature = $this->devis->signature;
        $this->assertSame(Devis::ACCEPTE, $this->devis->statut);
        $this->assertSame('Sophie Martin', $signature->nom);
        $this->assertSame('203.0.113.7', $signature->ip_address);
        $this->assertFalse($signature->sur_place);
        Storage::disk('local')->assertExists($signature->image_chemin);
        Storage::disk('local')->assertExists($signature->pdf_chemin);
        $this->assertSame($signature->pdf_sha256, hash('sha256', Storage::disk('local')->get($signature->pdf_chemin)));

        // Le PDF servi est désormais le PDF signé ; l'original envoyé reste conservé.
        $this->assertSame($signature->pdf_sha256, hash('sha256', $this->get('/c/'.$lien->jeton().'/pdf')->getContent()));
        Storage::disk('local')->assertExists($this->devis->pdf_chemin);

        Notification::assertSentTo($this->gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Devis signé');

        // Plus de deuxième signature.
        $this->get('/c/'.$lien->jeton())->assertSee('Devis signé')->assertDontSee('Signer le devis</button>', false);
        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'X', 'signature' => $this->signatureValide(), 'accord' => '1'])->assertSessionHasErrors('signature');
    }

    public function test_signature_illisible_refusee(): void
    {
        $lien = $this->lien();

        foreach (['', 'data:image/png;base64,pasduPNG', 'data:image/svg+xml;base64,'.base64_encode('<svg/>')] as $image) {
            $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'Sophie', 'signature' => $image, 'accord' => '1'])->assertSessionHasErrors('signature');
        }
        $this->assertSame(Devis::ENVOYE, $this->devis->fresh()->statut);
    }

    public function test_refus_et_demande_de_modification(): void
    {
        Notification::fake();
        $lien = $this->lien();

        $this->post('/c/'.$lien->jeton().'/modification', ['message' => ''])->assertSessionHasErrors('message');
        $this->post('/c/'.$lien->jeton().'/modification', ['message' => 'Ajouter le nettoyage des gouttières'])->assertRedirect();
        $this->assertSame(1, $this->devis->demandesModification()->count());
        Notification::assertSentTo($this->gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Modification demandée');

        $this->actingAs($this->gerant)->get(route('devis.show', $this->devis))->assertSee('Ajouter le nettoyage des gouttières');
        auth()->logout();

        $this->post('/c/'.$lien->jeton().'/refuser', ['motif' => 'Trop cher'])->assertRedirect();
        $this->assertSame(Devis::REFUSE, $this->devis->fresh()->statut);
        Notification::assertSentTo($this->gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Devis refusé');
    }

    public function test_signature_sur_place_sur_le_telephone_de_l_artisan(): void
    {
        Notification::fake();
        $this->actingAs($this->gerant)->get(route('devis.signer', $this->devis))->assertOk()->assertSee('Signez avec le doigt');

        $this->post(route('devis.signer', $this->devis), ['nom' => 'Sophie Martin', 'signature' => $this->signatureValide(), 'accord' => '1'])
            ->assertRedirect(route('devis.show', $this->devis));

        $this->assertTrue($this->devis->fresh()->signature->sur_place);
        $this->get(route('devis.show', $this->devis))->assertSee('sur place');
    }

    public function test_execution_immediate_hors_etablissement(): void
    {
        $this->devis->update(['hors_etablissement' => true]);
        $lien = $this->lien();

        $this->get('/c/'.$lien->jeton())->assertSee('avant la fin du délai de rétractation');
        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'S', 'signature' => $this->signatureValide(), 'accord' => '1', 'execution_immediate' => '1']);

        $this->assertTrue($this->devis->fresh()->signature->execution_immediate);
    }

    public function test_devis_expire_ou_brouillon(): void
    {
        $lien = $this->lien();
        $this->travel(40)->days();

        $this->get('/c/'.$lien->jeton())->assertSee('n\'est plus valable', false)->assertDontSee('Signer le devis</button>', false);

        $brouillon = app(GestionDevis::class)->creer(Client::factory()->create(), null, null, $this->gerant->id);
        $this->actingAs($this->gerant)->post(route('devis.lien', $brouillon))->assertSessionHasErrors('devis');
    }

    public function test_liens_coupes_tant_que_la_configuration_n_est_pas_terminee(): void
    {
        $lien = $this->lien();
        app(Reglages::class)->set('setup.completed_at', null);
        app(Reglages::class)->set('setup.passe_at', now()->toIso8601String());

        $this->get('/c/'.$lien->jeton())->assertStatus(503)->assertSee('momentanément indisponible');
        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'S', 'signature' => $this->signatureValide(), 'accord' => '1'])->assertStatus(503);
    }

    public function test_l_adresse_client_n_ouvre_que_les_pages_clients(): void
    {
        config(['app.url' => 'https://app.exemple.test', 'app.client_url' => 'https://devis.exemple.test']);
        $lien = $this->lien();

        $this->assertStringStartsWith('https://devis.exemple.test/c/', $lien->url());
        $this->get('https://devis.exemple.test/c/'.$lien->jeton())->assertOk();
        $this->get('https://devis.exemple.test/connexion')->assertNotFound();
        $this->get('https://devis.exemple.test/accueil')->assertNotFound();
        $this->get('https://devis.exemple.test/reglages')->assertNotFound();

        // L'application reste normale sur son adresse.
        $this->get('https://app.exemple.test/connexion')->assertOk();
    }

    public function test_lien_cree_depuis_la_fiche_du_devis(): void
    {
        $this->actingAs($this->gerant)->post(route('devis.lien', $this->devis))->assertRedirect(route('devis.show', $this->devis).'#lien-client');

        $this->get(route('devis.show', $this->devis))
            ->assertSee(LienClient::pour($this->devis)->url())
            ->assertSee('Faire signer sur place');
    }

    public function test_pages_clients_sans_script_inline(): void
    {
        $contenu = $this->get('/c/'.$this->lien()->jeton())->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $contenu);
        $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu);
    }

    public function test_une_erreur_ramene_a_la_page_du_client_et_pas_a_la_feuille_de_style(): void
    {
        $lien = $this->lien();
        $this->get('/c/'.$lien->jeton());
        $this->get('/theme.css')->assertOk();

        $this->post('/c/'.$lien->jeton().'/signer', ['nom' => 'S', 'signature' => '', 'accord' => '1'])
            ->assertRedirect('/c/'.$lien->jeton());
    }
}
