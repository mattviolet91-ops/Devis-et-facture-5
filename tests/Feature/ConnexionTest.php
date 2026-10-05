<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\User;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_la_page_de_connexion_est_en_francais(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('Se connecter')
            ->assertSee('Mot de passe oublié ?');
    }

    public function test_la_page_de_connexion_montre_le_nom_et_la_phrase_d_accroche(): void
    {
        // Base neuve : ni nom ni phrase, seulement l'icône de l'application.
        $this->get('/connexion')->assertOk()->assertDontSee('marque-nom', false)->assertDontSee('marque-slogan', false);

        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        app(Reglages::class)->set('identite.slogan', 'Toitures et gouttières');

        $this->get('/connexion')->assertOk()->assertSee('Entreprise Fictive')->assertSee('Toitures et gouttières');
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/accueil')->assertRedirect('/connexion');
        $this->get('/plus')->assertRedirect('/connexion');
    }

    public function test_connexion_avec_le_bon_mot_de_passe(): void
    {
        $user = User::factory()->gerant()->create(['email' => 'gerant@exemple.test']);

        $this->post('/connexion', ['email' => 'gerant@exemple.test', 'password' => 'password'])
            ->assertRedirect('/accueil');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(Activite::where('action', 'connexion')->exists());
    }

    public function test_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'a@exemple.test']);

        $this->from('/connexion')
            ->post('/connexion', ['email' => 'a@exemple.test', 'password' => 'faux'])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_champs_obligatoires_expliques_en_francais(): void
    {
        $this->post('/connexion', [])
            ->assertSessionHasErrors(['email' => 'Remplissez « email ».', 'password' => 'Remplissez « mot de passe ».']);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactif()->create(['email' => 'off@exemple.test']);

        $this->post('/connexion', ['email' => 'off@exemple.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_compte_desactive_est_deconnecte_immediatement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/accueil')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/accueil')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_limitation_des_essais(): void
    {
        User::factory()->create(['email' => 'b@exemple.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => 'b@exemple.test', 'password' => 'faux']);
        }

        // Même le bon mot de passe est refusé tant que l'attente n'est pas finie.
        $this->post('/connexion', ['email' => 'b@exemple.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString("Trop d'essais", session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_limitation_globale_par_adresse_ip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/connexion', ['email' => "x{$i}@exemple.test", 'password' => 'faux']);
        }

        $this->post('/connexion', ['email' => 'y@exemple.test', 'password' => 'faux'])
            ->assertStatus(429)
            ->assertSee('Trop d&#039;essais', false);
    }

    public function test_deconnexion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/deconnexion')->assertRedirect('/connexion');

        $this->assertGuest();
        $this->assertTrue(Activite::where('action', 'deconnexion')->exists());
    }

    public function test_un_utilisateur_connecte_ne_revoit_pas_la_connexion(): void
    {
        $this->actingAs(User::factory()->create())->get('/connexion')->assertRedirect('/accueil');
    }
}
