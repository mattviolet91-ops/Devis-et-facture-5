<?php

namespace Tests\Feature;

use App\Mail\EmailDocument;
use App\Models\Client;
use App\Models\Devis;
use App\Models\EmailEnvoye;
use App\Models\User;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnvoiEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private Devis $devis;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $r = app(Reglages::class);
        $r->set('identite.nom_commercial', 'Entreprise Fictive');
        $r->set('emails.adresse', 'entreprise.fictive@gmail.test');
        $r->setSecret('emails.mot_de_passe', 'abcdefghijklmnop');
        $r->set('emails.copie_cachee', true);
        $this->gerant = User::factory()->gerant()->create();

        $gestion = app(GestionDevis::class);
        $this->devis = $gestion->creer(Client::factory()->create(['civilite' => 'Mme', 'nom' => 'Martin', 'email' => 'martin@exemple.test', 'telephone' => '0600000001']), null, 'Toiture', $this->gerant->id);
        $gestion->remplacerLignes($this->devis, [['type' => 'ligne', 'designation' => 'Démoussage', 'quantite' => 100000, 'unite' => 'm²', 'prix_unitaire_ht' => 1200, 'taux_tva' => 1000]]);
    }

    public function test_formulaire_prerempli_avec_le_modele(): void
    {
        $this->actingAs($this->gerant)->get(route('envoi.create', ['devis', $this->devis->id]))
            ->assertOk()
            ->assertSee('value="martin@exemple.test"', false)
            ->assertSee('Bonjour Madame Martin')
            ->assertSee('Entreprise Fictive')
            ->assertSee('il recevra son numéro');
    }

    public function test_envoi_du_devis_numero_pdf_copie_cachee_et_historique(): void
    {
        Mail::fake();

        $this->actingAs($this->gerant)->post(route('envoi.store', ['devis', $this->devis->id]), [
            'destinataire' => 'martin@exemple.test', 'modele' => 'devis',
            'sujet' => 'Votre devis {numero}', 'corps' => "Bonjour,\nvoici le devis {numero} : {lien}", 'joindre_pdf' => '1',
        ])->assertRedirect(route('devis.show', $this->devis))->assertSessionHas('statut');

        $devis = $this->devis->fresh();
        $this->assertSame('envoye', $devis->statut);
        $this->assertNotNull($devis->numero);

        Mail::assertSent(EmailDocument::class, function (EmailDocument $mail) use ($devis) {
            return $mail->hasTo('martin@exemple.test')
                && $mail->hasBcc('entreprise.fictive@gmail.test')
                && $mail->sujet === 'Votre devis '.$devis->numero
                && str_contains($mail->corps, '/c/')
                && str_starts_with((string) $mail->pdf, '%PDF');
        });

        $historique = EmailEnvoye::firstOrFail();
        $this->assertSame('envoye', $historique->statut);
        $this->assertSame('devis', $historique->modele);
        $this->assertStringStartsWith('devis-', $historique->piece_jointe);
        $this->get(route('envoi.create', ['devis', $this->devis->id]))->assertSee('Déjà envoyés');
    }

    public function test_echec_d_envoi_note_dans_l_historique(): void
    {
        app(GestionDevis::class)->marquerEnvoye($this->devis);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connexion SMTP refusée'));

        $this->actingAs($this->gerant)->post(route('envoi.store', ['devis', $this->devis->id]), [
            'destinataire' => 'martin@exemple.test', 'modele' => 'devis', 'sujet' => 'Devis', 'corps' => 'Bonjour', 'joindre_pdf' => '0',
        ])->assertSessionHasErrors('envoi');

        $this->assertSame('erreur', EmailEnvoye::firstOrFail()->statut);
        $this->assertStringContainsString('SMTP', EmailEnvoye::first()->erreur);
    }

    public function test_facture_brouillon_non_envoyable_et_relance_proposee(): void
    {
        $facture = app(GestionFactures::class)->creerVide($this->devis->client, $this->gerant->id);
        app(GestionFactures::class)->remplacerLignes($facture, [['type' => 'ligne', 'designation' => 'X', 'quantite' => 1000, 'prix_unitaire_ht' => 10000]]);

        $this->actingAs($this->gerant)->post(route('envoi.store', ['facture', $facture->id]), [
            'destinataire' => 'martin@exemple.test', 'modele' => 'facture', 'sujet' => 'x', 'corps' => 'y',
        ])->assertSessionHasErrors('envoi');

        app(GestionFactures::class)->emettre($facture);
        $this->travel(40)->days();
        $this->get(route('envoi.create', ['facture', $facture->id]))->assertOk()->assertSee('Relance')->assertSee('data-choix-modele', false);
    }

    public function test_le_commercial_envoie_les_devis_mais_pas_les_factures(): void
    {
        $commercial = User::factory()->create();
        $facture = app(GestionFactures::class)->creerVide($this->devis->client, $this->gerant->id);

        $this->actingAs($commercial)->get(route('envoi.create', ['devis', $this->devis->id]))->assertOk();
        $this->get(route('envoi.create', ['facture', $facture->id]))->assertForbidden();
        $this->get('/envoyer/autre/1')->assertNotFound();
    }

    public function test_boutons_whatsapp_sms_et_copier(): void
    {
        app(GestionDevis::class)->marquerEnvoye($this->devis);
        $this->actingAs($this->gerant)->post(route('devis.lien', $this->devis));

        $this->get(route('devis.show', $this->devis))
            ->assertSee('https://wa.me/33600000001?text=', false)
            ->assertSee('sms:0600000001?&amp;body=', false)
            ->assertSee('data-copier="message-pret"', false)
            ->assertSee('Renvoyer par email');
    }
}
