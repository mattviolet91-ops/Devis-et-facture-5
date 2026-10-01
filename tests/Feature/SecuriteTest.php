<?php

namespace Tests\Feature;

use App\Http\Middleware\EnTetesSecurite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_tetes_de_securite_sur_toutes_les_pages(): void
    {
        $reponse = $this->get('/connexion');

        $csp = $reponse->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);

        $reponse->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->get('/page-qui-n-existe-pas')->assertHeader('Content-Security-Policy', EnTetesSecurite::CSP);
    }

    public function test_hsts_seulement_en_https(): void
    {
        $this->get('/connexion')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/connexion')->assertHeader('Strict-Transport-Security');
    }

    public function test_redirection_https_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://localhost/connexion')->assertRedirect('https://localhost/connexion')->assertStatus(301);
    }

    public function test_cookies_securises_en_production(): void
    {
        $config = require base_path('config/session.php');
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);

        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        try {
            $config = require base_path('config/session.php');
            $this->assertTrue($config['secure']);
        } finally {
            putenv('APP_ENV=testing');
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        }
    }

    public function test_protection_csrf(): void
    {
        // Les tests désactivent le CSRF : on le réactive en sortant de l'environnement de test.
        $this->app['env'] = 'local';
        User::factory()->create(['email' => 'csrf@exemple.test']);

        $this->post('/connexion', ['email' => 'csrf@exemple.test', 'password' => 'password'])
            ->assertStatus(419)
            ->assertSee('Page expirée');

        $this->assertGuest();
    }

    public function test_aucun_script_ni_style_inline_dans_les_pages(): void
    {
        $gerant = User::factory()->gerant()->create();
        $pages = ['/accueil', '/plus', '/nouveau', '/journal', '/corbeille', '/bientot/factures'];

        $html = [$this->get('/connexion')->getContent(), $this->get('/mot-de-passe-oublie')->getContent()];
        foreach ($pages as $page) {
            $html[] = $this->actingAs($gerant)->get($page)->assertOk()->getContent();
        }
        $html[] = $this->get('/404-inexistante')->getContent();

        foreach ($html as $contenu) {
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $contenu, 'Script inline trouvé');
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $contenu, 'Gestionnaire d\'événement inline trouvé');
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $contenu, 'Style inline trouvé');
            $this->assertDoesNotMatchRegularExpression('#https?://(?!localhost)#i', $contenu, 'Ressource externe trouvée');
        }
    }
}
