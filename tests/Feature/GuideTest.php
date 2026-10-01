<?php

namespace Tests\Feature;

use App\Models\AbonnementPush;
use App\Models\Prestation;
use App\Models\User;
use App\Support\BienDemarrer;
use App\Support\Guide;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_rubrique_mene_a_une_page_qui_existe(): void
    {
        foreach (Guide::rubriques() as $cle => $rubrique) {
            $this->assertTrue(Route::has($rubrique['route']), "Rubrique {$cle} : la page {$rubrique['route']} n'existe pas.");
            $this->assertNotEmpty($rubrique['etapes']);
            $this->assertNotSame('', $rubrique['bon_a_savoir']);
            if ($rubrique['capture']) {
                $this->assertFileExists(public_path('images/guide/'.$rubrique['capture'].'.png'), "Capture manquante pour {$cle}.");
            }
        }
    }

    public function test_aucun_dossier_public_ne_cache_une_page(): void
    {
        // Un dossier public/xxx est servi par le serveur à la place de la page /xxx.
        foreach (Route::getRoutes() as $route) {
            $segment = explode('/', $route->uri())[0];
            if ($segment !== '' && ! str_contains($segment, '{')) {
                $this->assertDirectoryDoesNotExist(public_path($segment), "Le dossier public/{$segment} cache la page /{$segment}.");
            }
        }
    }

    public function test_les_libelles_cites_par_le_guide_existent_dans_les_ecrans(): void
    {
        // Chaque bouton cité entre guillemets dans le guide doit exister dans une vue ou une classe.
        $code = '';
        foreach (['resources/views', 'app'] as $dossier) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dossier))) as $fichier) {
                if ($fichier->isFile() && ! str_contains($fichier->getPathname(), 'Support/Guide.php')) {
                    $code .= file_get_contents($fichier->getPathname());
                }
            }
        }
        $code = html_entity_decode(str_replace("\\'", "'", $code));

        foreach (Guide::rubriques() as $rubrique) {
            foreach ($rubrique['etapes'] as $etape) {
                preg_match_all('/« ([^»]+) »/u', $etape, $m);
                foreach ($m[1] as $libelle) {
                    $this->assertStringContainsString($libelle, $code, "Le guide cite « {$libelle} » qui n'existe pas dans l'application.");
                }
            }
        }
    }

    public function test_le_commercial_ne_voit_que_ses_rubriques(): void
    {
        $commercial = User::factory()->create();
        $page = $this->actingAs($commercial)->get(route('guide'))->assertOk()->assertSee('Faire un devis')->getContent();
        foreach (['Factures et avoirs', 'Encaisser un paiement', 'Réglages', 'Statistiques', 'Comptes', 'Bien démarrer'] as $titre) {
            $this->assertStringNotContainsString('<h2>'.$titre, $page);
        }

        $gerant = User::factory()->gerant()->create();
        $this->actingAs($gerant)->get(route('guide'))->assertSee('Factures et avoirs')->assertSee('Bien démarrer');
    }

    public function test_recherche_et_bouton_aide(): void
    {
        $user = User::factory()->gerant()->create();
        $this->actingAs($user)->get(route('guide', ['q' => 'gel']))->assertOk()->assertSee('Planning et rappels')->assertDontSee('Le devis express');
        $this->get(route('guide', ['q' => 'xyzinconnu']))->assertSee('Rien trouvé');

        $this->get(route('planning.index'))->assertSee('href="'.route('guide', ['r' => 'planning']).'#planning"', false);
        $this->get(route('reglages.claude'))->assertSee('#claude"', false);
        $this->assertMatchesRegularExpression('/id="planning"\s+open/', $this->get(route('guide', ['r' => 'planning']))->getContent());
    }

    public function test_bien_demarrer(): void
    {
        Storage::fake('local');
        $user = User::factory()->gerant()->create();
        $this->assertFalse(BienDemarrer::complet($user));
        $this->actingAs($user)->get(route('accueil'))->assertSee('quelques réglages restent à faire');

        $reglages = app(Reglages::class);
        foreach (['identite.nom_commercial' => 'Fictive', 'identite.adresse' => '1 rue', 'identite.ville' => 'Ville', 'identite.siret' => '00000000000000', 'identite.telephone' => '0100000000', 'identite.email' => 'a@exemple.test',
            'documents.iban' => 'FR7630006000011234567890189', 'documents.cgv' => 'CGV', 'emails.adresse' => 'a@exemple.test', 'assurance.date_fin' => now()->addYear()->toDateString()] as $cle => $valeur) {
            $reglages->set($cle, $valeur);
        }
        $reglages->setSecret('emails.mot_de_passe', 'test');
        Prestation::factory()->count(5)->create();
        AbonnementPush::create(['user_id' => $user->id, 'endpoint' => 'https://push.exemple.test/1', 'cle_p256dh' => 'x', 'cle_auth' => 'y']);
        $this->assertFalse(BienDemarrer::complet($user), 'Il manque la sauvegarde du mois.');

        Storage::disk('local')->put('sauvegardes/base-'.now()->format('Y-m-d-Hi').'.sql.gz', 'x');
        $this->assertTrue(BienDemarrer::complet($user));
        $this->get(route('accueil'))->assertDontSee('quelques réglages restent à faire');
    }

    public function test_astuce_du_jour_tiree_du_guide(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('accueil'))->assertSee('Astuce du jour')->assertSee('En savoir plus dans le guide');
    }
}
