<?php

namespace Tests\Feature;

use App\Mail\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ComptesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create(['email' => 'gerant@exemple.test']);
    }

    public function test_inviter_un_commercial_qui_choisit_son_mot_de_passe(): void
    {
        Mail::fake();
        $this->actingAs($this->gerant)->get(route('comptes'))->assertOk()->assertSee('gerant@exemple.test')->assertSee('Inviter une personne');

        $this->post(route('comptes.inviter'), ['email' => 'Commercial@Exemple.test', 'role' => 'commercial'])->assertRedirect(route('comptes'));

        $lien = null;
        Mail::assertSent(Invitation::class, function (Invitation $mail) use (&$lien) {
            $lien = $mail->lien;

            return $mail->hasTo('commercial@exemple.test');
        });
        $compte = User::where('email', 'commercial@exemple.test')->firstOrFail();
        $this->assertSame('commercial', $compte->role);
        $this->assertTrue($compte->invitationEnAttente());
        // Seule l'empreinte du lien est en base.
        $jeton = basename((string) parse_url($lien, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $jeton), $compte->invitation_sha256);

        $this->post(route('comptes.inviter'), ['email' => 'commercial@exemple.test', 'role' => 'commercial'])->assertSessionHasErrors('email');

        auth()->logout();
        $this->get($lien)->assertOk()->assertSee('commercial@exemple.test');
        $this->post($lien, ['password' => 'court', 'password_confirmation' => 'court'])->assertSessionHasErrors('password');
        $this->post($lien, ['password' => 'mot-de-passe-solide', 'password_confirmation' => 'mot-de-passe-solide'])->assertRedirect(route('accueil'));
        $this->assertAuthenticatedAs($compte->fresh());
        $this->assertFalse($compte->fresh()->invitationEnAttente());

        // Le lien ne sert qu'une fois.
        auth()->logout();
        $this->get($lien)->assertOk()->assertSee('n\'est plus valable', false);
    }

    public function test_invitation_expiree(): void
    {
        Mail::fake();
        $this->actingAs($this->gerant)->post(route('comptes.inviter'), ['email' => 'retard@exemple.test', 'role' => 'gerant']);
        $lien = null;
        Mail::assertSent(Invitation::class, function (Invitation $mail) use (&$lien) {
            $lien = $mail->lien;

            return true;
        });
        auth()->logout();
        $this->travel(8)->days();
        $this->get($lien)->assertSee('n\'est plus valable', false);
        $this->post($lien, ['password' => 'mot-de-passe-solide', 'password_confirmation' => 'mot-de-passe-solide'])->assertNotFound();
    }

    public function test_desactivation_immediate_et_role(): void
    {
        $commercial = User::factory()->create();

        $this->actingAs($commercial)->get(route('accueil'))->assertOk();
        $this->actingAs($this->gerant)->post(route('comptes.desactiver', $commercial))->assertSessionHas('statut');
        $this->assertFalse($commercial->fresh()->is_active);

        // Session déjà ouverte : coupée à la page suivante.
        $this->actingAs($commercial->fresh())->get(route('accueil'))->assertRedirect(route('login'));

        $this->actingAs($this->gerant)->post(route('comptes.reactiver', $commercial));
        $this->assertTrue($commercial->fresh()->is_active);

        $this->post(route('comptes.role', $commercial), ['role' => 'gerant']);
        $this->assertTrue($commercial->fresh()->estGerant());

        // Pas sur soi-même.
        $this->post(route('comptes.desactiver', $this->gerant))->assertSessionHas('erreur');
        $this->post(route('comptes.role', $this->gerant), ['role' => 'commercial'])->assertSessionHas('erreur');
        $this->assertTrue($this->gerant->fresh()->estGerant());
    }

    public function test_le_commercial_n_a_pas_acces_aux_pages_reservees(): void
    {
        $commercial = User::factory()->create();
        $this->actingAs($commercial);

        foreach (['/comptes', '/factures', '/factures/nouvelle', '/reglages', '/reglages/entreprise', '/journal', '/corbeille', '/statistiques', '/statistiques/site', '/suivi/emails'] as $page) {
            $this->get($page)->assertForbidden();
        }
        foreach (['/accueil', '/clients', '/devis', '/planning', '/suivi', '/catalogue', '/nouveau', '/plus'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/plus')->assertDontSee('Factures')->assertDontSee('Réglages')->assertDontSee('Statistiques')->assertDontSee('Comptes');
        $this->get('/nouveau')->assertDontSee('Une facture');
    }

    public function test_email_non_parti_le_lien_est_montre_une_fois(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp'));
        $this->actingAs($this->gerant)->post(route('comptes.inviter'), ['email' => 'x@exemple.test', 'role' => 'commercial'])
            ->assertSessionHas('lien_invitation');
        $this->get(route('comptes'))->assertSee('/invitation/', false);
        $this->get(route('comptes'))->assertDontSee('/invitation/', false);
    }
}
