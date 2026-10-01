<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_barre_du_bas_avec_nouveau_et_plus(): void
    {
        $this->actingAs(User::factory()->create())->get('/accueil')
            ->assertOk()
            ->assertSee('aria-label="Menu principal"', false)
            ->assertSeeInOrder(['Accueil', 'Clients', 'Nouveau', 'Devis', 'Plus'])
            ->assertSee('href="'.route('nouveau').'"', false)
            ->assertSee('href="'.route('plus').'"', false);
    }

    public function test_bouton_retour_vers_la_page_parente(): void
    {
        $user = User::factory()->gerant()->create();

        $this->actingAs($user)->get('/journal')
            ->assertSee('<a href="'.route('plus').'" class="retour" data-retour>‹ Retour</a>', false);

        // L'accueil est la page de départ : pas de bouton Retour.
        $this->get('/accueil')->assertDontSee('data-retour', false);
    }

    public function test_menu_plus_avec_theme_et_grands_boutons(): void
    {
        $this->actingAs(User::factory()->gerant()->create())->get('/plus')
            ->assertSee('Thème')
            ->assertSee('value="sombre"', false)
            ->assertSee('Grands boutons')
            ->assertSee("Journal d'activité", false)
            ->assertSee('Corbeille')
            ->assertSee('Se déconnecter');
    }

    public function test_le_commercial_ne_voit_pas_les_liens_du_gerant(): void
    {
        $this->actingAs(User::factory()->create())->get('/plus')
            ->assertDontSee("Journal d'activité", false)
            ->assertDontSee('Corbeille')
            ->assertDontSee('Réglages');
    }

    public function test_manifest_pour_installer_l_application(): void
    {
        $reponse = $this->get('/manifest.webmanifest')->assertOk();

        $this->assertStringContainsString('application/manifest+json', $reponse->headers->get('Content-Type'));
        $reponse->assertJsonPath('display', 'standalone')
            ->assertJsonPath('lang', 'fr')
            ->assertJsonPath('start_url', '/accueil');

        foreach ($reponse->json('icons') as $icone) {
            $this->assertFileExists(public_path(ltrim($icone['src'], '/')));
        }
    }

    public function test_le_manifest_prend_le_nom_commercial_des_reglages(): void
    {
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');

        $this->get('/manifest.webmanifest')->assertJsonPath('name', 'Entreprise Fictive');
    }

    public function test_page_bientot_et_rubrique_inconnue(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/bientot/devis')->assertOk()->assertSee('Bientôt disponible');
        $this->get('/bientot/nimporte')->assertNotFound();
    }

    public function test_accessibilite_de_base(): void
    {
        $this->get('/connexion')
            ->assertSee('<label for="email">Email</label>', false)
            ->assertSee('<label for="password">Mot de passe</label>', false)
            ->assertSee('name="viewport"', false);

        $this->actingAs(User::factory()->create())->get('/accueil')
            ->assertSee('Aller au contenu')
            ->assertSee('aria-current="page"', false);
    }
}
