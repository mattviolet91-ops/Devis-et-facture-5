<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DemoStatiqueTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function pages(): array
    {
        $pages = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('docs/demo'), \FilesystemIterator::SKIP_DOTS)) as $fichier) {
            if (str_ends_with($fichier->getFilename(), '.html')) {
                $pages[] = $fichier->getPathname();
            }
        }

        return $pages;
    }

    public function test_la_demo_a_regarder_est_complete_et_sans_adresse_locale(): void
    {
        $this->assertFileExists(base_path('docs/demo/index.html'));
        $this->assertFileExists(base_path('docs/demo/accueil.html'));
        $pages = $this->pages();
        $this->assertGreaterThan(100, count($pages));

        foreach ($pages as $page) {
            $html = (string) file_get_contents($page);
            $this->assertStringNotContainsString('127.0.0.1', $html, "Adresse locale dans {$page}");
            if (! str_ends_with($page, 'docs/demo/index.html')) {
                $this->assertStringContainsString('demo-statique.js', $html, "Démo sans blocage des formulaires : {$page}");
                $this->assertStringContainsString('Démonstration : toutes les données sont fictives', $html, "Page sans bandeau de démonstration : {$page}");
            }
        }
    }

    public function test_liens_construits_avec_l_adresse_publique_derriere_un_relais(): void
    {
        config(['app.url' => 'https://essai-8000.app.github.dev', 'app.forcer_url' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('https://essai-8000.app.github.dev/connexion', route('login'));
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
}
