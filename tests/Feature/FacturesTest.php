<?php

namespace Tests\Feature;

use App\Mail\EmailDocument;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\LienClient;
use App\Models\User;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Services\PdfFacture;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Reglages::class)->set('tva.regime', 'assujetti');
        app(Reglages::class)->set('documents.delai_paiement_jours', 30);
        $this->gerant = User::factory()->gerant()->create();
    }

    /**
     * Devis accepté : 1 200,00 € HT à 10 % + 500,00 € HT à 20 % (+ une option à 999 €).
     */
    private function devisAccepte(array $client = [], array $devis = []): Devis
    {
        $gestion = app(GestionDevis::class);
        $d = $gestion->creer(Client::factory()->create($client), null, 'Toiture', $this->gerant->id);
        $d->update($devis);
        $gestion->remplacerLignes($d, [
            ['type' => 'ligne', 'designation' => 'Démoussage', 'quantite' => 100000, 'unite' => 'm²', 'prix_unitaire_ht' => 1200, 'taux_tva' => 1000],
            ['type' => 'ligne', 'designation' => 'Gouttière neuve', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 50000, 'taux_tva' => 2000],
            ['type' => 'ligne', 'designation' => 'Option fenêtre', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 99900, 'taux_tva' => 2000, 'option' => true],
        ]);
        $gestion->marquerEnvoye($d);
        $gestion->accepter($d->fresh());

        return $d->fresh();
    }

    private function facturer(Devis $devis, string $type, ?string $pourcentage = null): Facture
    {
        $this->actingAs($this->gerant)->post(route('factures.depuis-devis', $devis), array_filter(['type' => $type, 'pourcentage' => $pourcentage]))->assertSessionHasNoErrors();

        return Facture::latest('id')->firstOrFail();
    }

    public function test_le_commercial_n_a_jamais_acces_aux_factures(): void
    {
        $facture = $this->facturer($this->devisAccepte(), 'facture');
        $commercial = User::factory()->create();

        $this->actingAs($commercial);
        foreach (['/factures', '/factures/nouvelle', route('factures.show', $facture), route('factures.pdf', $facture)] as $page) {
            $this->get($page)->assertForbidden();
        }
        $this->post(route('factures.emettre', $facture))->assertForbidden();
        $this->get('/plus')->assertDontSee('Factures');
        $this->get('/visionneuse?f=/factures/'.$facture->id.'/pdf')->assertOk();
        // La visionneuse s'ouvre, mais le document lui-même reste refusé.
        $this->get('/factures/'.$facture->id.'/pdf')->assertForbidden();
    }

    public function test_facture_complete_depuis_un_devis_accepte(): void
    {
        $devis = $this->devisAccepte();
        $facture = $this->facturer($devis, 'facture');

        $this->assertSame(Facture::BROUILLON, $facture->statut);
        $this->assertNull($facture->numero);
        $this->assertSame(2, $facture->lignes()->count()); // l'option n'est pas facturée
        $this->assertSame($devis->total_ttc, $facture->total_ttc);
        $this->assertSame(132000 + 60000, $facture->total_ttc);
    }

    public function test_un_devis_non_accepte_ne_se_facture_pas(): void
    {
        $gestion = app(GestionDevis::class);
        $devis = $gestion->creer(Client::factory()->create(), null, null, $this->gerant->id);

        $this->actingAs($this->gerant)->post(route('factures.depuis-devis', $devis), ['type' => 'facture'])
            ->assertSessionHasErrors(['facture' => 'Seul un devis accepté peut être facturé.']);
    }

    public function test_acompte_situations_et_solde(): void
    {
        $devis = $this->devisAccepte();

        $acompte = $this->facturer($devis, 'acompte', '30');
        $this->assertSame(36000 + 15000, $acompte->total_ht);
        $this->assertSame(39600 + 18000, $acompte->total_ttc);
        $this->assertSame(2, $acompte->lignes()->count());

        $situation1 = $this->facturer($devis, 'situation', '50');
        // 50 % du devis moins l'acompte de 30 % = 20 %.
        $this->assertSame(intdiv(170000 * 20, 100), $situation1->total_ht);
        $this->assertSame(1, $situation1->numero_situation);

        $situation2 = $this->facturer($devis, 'situation', '80');
        $this->assertSame(intdiv(170000 * 30, 100), $situation2->total_ht);
        $this->assertSame(2, $situation2->numero_situation);

        $this->actingAs($this->gerant)->post(route('factures.depuis-devis', $devis), ['type' => 'situation', 'pourcentage' => '70'])
            ->assertSessionHasErrors('pourcentage');

        $solde = $this->facturer($devis, 'solde');
        $this->assertSame($devis->total_ttc - $acompte->total_ttc - $situation1->total_ttc - $situation2->total_ttc, $solde->total_ttc);
        $this->assertTrue($solde->lignes()->where('designation', 'like', 'À déduire%')->exists());

        $this->actingAs($this->gerant)->post(route('factures.depuis-devis', $devis), ['type' => 'acompte'])
            ->assertSessionHasErrors('pourcentage');
    }

    public function test_emission_numero_echeance_et_facture_figee(): void
    {
        $facture = $this->facturer($this->devisAccepte(), 'facture');

        $this->post(route('factures.emettre', $facture))->assertRedirect(route('factures.show', $facture));
        $facture->refresh();

        $this->assertSame('FAC-'.now()->year.'-0001', $facture->numero);
        $this->assertSame(Facture::EMISE, $facture->statut);
        $this->assertSame(now()->addDays(30)->toDateString(), $facture->date_echeance->toDateString());
        Storage::disk('local')->assertExists($facture->pdf_chemin);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($facture->pdf_chemin)), $facture->pdf_sha256);

        // Plus jamais modifiable ni supprimable.
        $this->get(route('factures.edit', $facture))->assertRedirect(route('factures.show', $facture));
        $this->put(route('factures.update', $facture), ['delai_paiement_jours' => 10, 'lignes' => []])->assertRedirect(route('factures.show', $facture));
        $this->delete(route('factures.destroy', $facture))->assertSessionHasErrors('facture');
        $this->post(route('factures.emettre', $facture))->assertSessionHasErrors('facture');
        $this->assertNotSoftDeleted($facture);
        $this->assertSame(2, $facture->lignes()->count());

        $autre = $this->facturer($this->devisAccepte(), 'facture');
        $this->post(route('factures.emettre', $autre));
        $this->assertSame('FAC-'.now()->year.'-0002', $autre->fresh()->numero);
    }

    public function test_avoir_total_et_partiel(): void
    {
        $facture = $this->facturer($this->devisAccepte(), 'facture');
        app(GestionFactures::class)->emettre($facture);
        $facture->refresh();

        // Avoir partiel de 120,00 € TTC.
        $this->post(route('factures.avoir', $facture), ['mode' => 'partiel', 'montant' => '120', 'motif' => 'Geste commercial'])->assertRedirect();
        $avoir = Facture::where('type', 'avoir')->latest('id')->firstOrFail();
        $this->assertSame(12000, $avoir->total_ttc);
        $this->assertNull($avoir->numero);
        $this->post(route('factures.emettre', $avoir));
        $avoir->refresh();
        $this->assertSame('AV-'.now()->year.'-0001', $avoir->numero);
        $this->assertSame($facture->total_ttc - 12000, $facture->fresh()->resteAPayer());

        // Trop élevé.
        $this->post(route('factures.avoir', $facture), ['mode' => 'partiel', 'montant' => '999999', 'motif' => 'x'])->assertSessionHasErrors('montant');
        $this->post(route('factures.avoir', $facture), ['mode' => 'partiel', 'montant' => '10'])->assertSessionHasErrors('motif');

        // Avoir du reste : la facture est annulée.
        $this->post(route('factures.avoir', $facture), ['mode' => 'total', 'motif' => 'Erreur de client']);
        $dernier = Facture::where('type', 'avoir')->latest('id')->firstOrFail();
        $this->assertSame($facture->total_ttc - 12000, $dernier->total_ttc);
        $this->post(route('factures.emettre', $dernier));
        $this->assertSame(Facture::ANNULEE, $facture->fresh()->statut);
        $this->assertSame(0, $facture->fresh()->resteAPayer());

        $this->get(route('factures.show', $facture))->assertSee('Avoirs')->assertSee('AV-'.now()->year.'-0002');
    }

    public function test_rappel_sept_jours_hors_etablissement(): void
    {
        $devis = $this->devisAccepte([], ['hors_etablissement' => true]);

        $this->actingAs($this->gerant)->post(route('factures.depuis-devis', $devis), ['type' => 'acompte', 'pourcentage' => '30'])
            ->assertSessionHas('rappel', fn ($r) => str_contains($r, 'aucun paiement ne peut être demandé ni encaissé avant le'));

        $this->travel(8)->days();
        $this->assertNull(GestionFactures::rappelSeptJours($devis->fresh()));
    }

    public function test_relances_automatiques(): void
    {
        Mail::fake();
        $facture = $this->facturer($this->devisAccepte(['email' => 'client@exemple.test']), 'facture');
        app(GestionFactures::class)->emettre($facture);
        $this->post(route('factures.relances', $facture), ['relances_auto' => '1']);

        $this->artisan('app:relancer-factures')->expectsOutput('0 relance(s) envoyée(s).');

        $this->travel(31)->days();
        $this->artisan('app:relancer-factures')->expectsOutput('1 relance(s) envoyée(s).');
        $this->artisan('app:relancer-factures')->expectsOutput('0 relance(s) envoyée(s).');
        Mail::assertSent(EmailDocument::class, fn ($m) => $m->hasTo('client@exemple.test') && str_contains($m->sujet, $facture->fresh()->numero) && $m->pdf !== null);

        foreach ([8, 16, 24] as $jours) {
            $this->travel(8)->days();
            $this->artisan('app:relancer-factures');
        }
        $this->assertSame(3, $facture->fresh()->relances);
        $this->assertDatabaseHas('emails_envoyes', ['modele' => 'relance', 'automatique' => true]);
    }

    public function test_mentions_de_la_facture(): void
    {
        app(Reglages::class)->set('documents.iban', 'FR7630006000011234567890189');
        app(Reglages::class)->set('assurance.assureur', 'Assureur Fictif');
        $facture = $this->facturer($this->devisAccepte(['type' => 'professionnel', 'raison_sociale' => 'Société Fictive', 'siret' => '12345678901237']), 'facture');
        app(GestionFactures::class)->emettre($facture);

        $html = app(PdfFacture::class)->html($facture->fresh());
        foreach ([$facture->fresh()->numero, 'Date de la prestation', 'Échéance', 'Catégorie de l\'opération : prestation de services',
            'SIREN 123456789', 'Pas d\'escompte', 'indemnité forfaitaire pour frais de recouvrement de 40 €', 'Assurance décennale',
            'IBAN FR76', 'Adresse de réalisation des travaux', 'TVA 10 %', 'TVA 20 %'] as $mention) {
            $this->assertTrue(str_contains($html, $mention) || str_contains($html, e($mention)), $mention);
        }
    }

    public function test_page_client_de_la_facture(): void
    {
        $facture = $this->facturer($this->devisAccepte(), 'facture');
        app(GestionFactures::class)->emettre($facture);
        $this->post(route('factures.lien', $facture))->assertRedirect();

        $lien = LienClient::pour($facture->fresh());
        auth()->logout();
        $this->get('/c/'.$lien->jeton())->assertOk()->assertSee('Reste à payer')->assertSee("1\u{202F}920,00", false)->assertSee($facture->fresh()->numero);
        $this->get('/c/'.$lien->jeton().'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_facture_directe_avec_ligne_de_deduction(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->gerant)->post('/factures', ['client_id' => $client->id])->assertRedirect();
        $facture = Facture::firstOrFail();

        $this->get(route('factures.edit', $facture))->assertOk()->assertDontSee('En option');
        $this->put(route('factures.update', $facture), ['delai_paiement_jours' => '15', 'lignes' => [
            ['type' => 'ligne', 'designation' => 'Réparation', 'quantite' => '1', 'prix' => '500', 'taux_tva' => '1000'],
            ['type' => 'ligne', 'designation' => 'Déduction acompte versé', 'quantite' => '1', 'prix' => '-100', 'taux_tva' => '1000'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(44000, $facture->fresh()->total_ttc);
        $this->assertSame(15, $facture->fresh()->delai_paiement_jours);
    }

    public function test_liste_et_inline(): void
    {
        $facture = $this->facturer($this->devisAccepte(), 'facture');
        app(GestionFactures::class)->emettre($facture);

        $this->get('/factures')->assertOk()->assertSee('à encaisser')->assertSee("1\u{202F}920,00", false);
        $this->get('/factures?filtre=brouillons')->assertSee('Aucune facture dans cette liste');
        $this->travel(40)->days();
        $this->get('/factures?filtre=retard')->assertSee('En retard');

        foreach (['/factures', '/factures/nouvelle', route('factures.show', $facture)] as $page) {
            $contenu = $this->get($page)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu, $page);
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu, $page);
        }
    }
}
