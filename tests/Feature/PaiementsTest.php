<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Encaissements;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaiementsTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Reglages::class)->set('tva.regime', 'franchise');
        $this->gerant = User::factory()->gerant()->create();
    }

    public static function factureEmise(int $montant = 150000, array $client = []): Facture
    {
        $gestion = app(GestionFactures::class);
        $facture = $gestion->creerVide(Client::factory()->create($client), User::first()->id);
        $gestion->remplacerLignes($facture, [['type' => 'ligne', 'designation' => 'Travaux', 'quantite' => 1000, 'unite' => 'forfait', 'prix_unitaire_ht' => $montant]]);

        return $gestion->emettre($facture)->fresh();
    }

    private function encaisser(Facture $facture, array $donnees): TestResponse
    {
        return $this->actingAs($this->gerant)->post(route('paiements.store', $facture), array_merge(['date_paiement' => now()->toDateString(), 'mode' => 'virement'], $donnees));
    }

    public function test_encaissements_reste_a_payer_et_statut_payee(): void
    {
        $facture = self::factureEmise();

        $this->encaisser($facture, ['montant' => '500', 'mode' => 'cheque', 'reference' => 'Chèque 1234'])->assertSessionHasNoErrors();
        $this->assertSame(100000, $facture->fresh()->resteAPayer());
        $this->assertSame(Facture::EMISE, $facture->fresh()->statut);

        $this->encaisser($facture, ['montant' => '1 000,00'])->assertSessionHasNoErrors();
        $this->assertSame(0, $facture->fresh()->resteAPayer());
        $this->assertSame(Facture::PAYEE, $facture->fresh()->statut);

        $this->get(route('factures.show', $facture))->assertSee('Encaissements')->assertSee('Chèque 1234')->assertDontSee('Enregistrer l&#039;encaissement', false);
    }

    public function test_controles_de_l_encaissement(): void
    {
        $facture = self::factureEmise();

        $this->encaisser($facture, ['montant' => '2000'])->assertSessionHasErrors('montant');
        $this->encaisser($facture, ['montant' => 'cent'])->assertSessionHasErrors(['montant' => 'Montant illisible (exemple : 250,00).']);
        $this->encaisser($facture, ['montant' => '10', 'date_paiement' => now()->addDay()->toDateString()])->assertSessionHasErrors('date_paiement');
        $this->encaisser($facture, ['montant' => '10', 'mode' => 'carte_en_ligne'])->assertSessionHasErrors('mode');
        // Plafond légal des espèces.
        $this->encaisser($facture, ['montant' => '1200', 'mode' => 'especes'])->assertSessionHasErrors(['mode']);
        $this->encaisser($facture, ['montant' => '1000', 'mode' => 'especes'])->assertSessionHasNoErrors();

        // Brouillon : pas d'encaissement.
        $gestion = app(GestionFactures::class);
        $brouillon = $gestion->creerVide(Client::factory()->create(), $this->gerant->id);
        $this->encaisser($brouillon, ['montant' => '10'])->assertSessionHasErrors('montant');
    }

    public function test_un_paiement_ne_s_efface_pas_il_s_annule(): void
    {
        $facture = self::factureEmise();
        $this->encaisser($facture, ['montant' => '1500']);
        $paiement = Paiement::firstOrFail();
        $this->assertSame(Facture::PAYEE, $facture->fresh()->statut);

        $this->expectExceptionOnDelete($paiement);

        $this->post(route('paiements.annuler', $paiement), [])->assertSessionHasErrors('motif');
        $this->post(route('paiements.annuler', $paiement), ['motif' => 'Chèque refusé'])->assertRedirect();

        $this->assertSame(2, Paiement::count());
        $this->assertSame(-150000, Paiement::latest('id')->first()->montant);
        $this->assertSame(Facture::EMISE, $facture->fresh()->statut);
        $this->assertSame(150000, $facture->fresh()->resteAPayer());

        $this->post(route('paiements.annuler', $paiement), ['motif' => 'encore'])->assertSessionHasErrors('paiement');
    }

    private function expectExceptionOnDelete(Paiement $paiement): void
    {
        try {
            $paiement->delete();
            $this->fail('Un paiement ne doit pas pouvoir être supprimé.');
        } catch (\LogicException) {
        }
        try {
            $paiement->update(['montant' => 1]);
            $this->fail('Un paiement ne doit pas pouvoir être modifié.');
        } catch (\LogicException) {
        }
    }

    public function test_chaine_d_empreintes_inalterable(): void
    {
        $facture = self::factureEmise();
        $this->encaisser($facture, ['montant' => '100']);
        $this->encaisser($facture, ['montant' => '200']);
        $this->encaisser($facture, ['montant' => '300']);

        $encaissements = app(Encaissements::class);
        $this->assertNull($encaissements->premiereAlteration());

        // Une modification directe en base est détectée.
        $deuxieme = Paiement::orderBy('id')->skip(1)->first();
        DB::table('paiements')->where('id', $deuxieme->id)->update(['montant' => 99999]);
        $this->assertSame($deuxieme->id, $encaissements->premiereAlteration());
    }

    public function test_rappel_sept_jours_a_l_encaissement(): void
    {
        $gestionDevis = app(GestionDevis::class);
        $devis = $gestionDevis->creer(Client::factory()->create(), null, null, $this->gerant->id);
        $devis->update(['hors_etablissement' => true]);
        $gestionDevis->remplacerLignes($devis, [['type' => 'ligne', 'designation' => 'X', 'quantite' => 1000, 'prix_unitaire_ht' => 50000]]);
        $gestionDevis->marquerEnvoye($devis);
        $gestionDevis->accepter($devis->fresh());
        $facture = app(GestionFactures::class)->depuisDevis($devis->fresh(), 'acompte', 3000, $this->gerant->id);
        app(GestionFactures::class)->emettre($facture);

        // Non bloquant : l'encaissement passe, avec un rappel.
        $this->encaisser($facture->fresh(), ['montant' => '50'])->assertSessionHasNoErrors()->assertSessionHas('rappel');
    }

    public function test_le_commercial_ne_voit_pas_les_paiements(): void
    {
        $facture = self::factureEmise();
        $this->actingAs(User::factory()->create())->post(route('paiements.store', $facture), ['montant' => '10', 'mode' => 'virement', 'date_paiement' => now()->toDateString()])->assertForbidden();
    }
}
