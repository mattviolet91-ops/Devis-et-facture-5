<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\User;
use App\Services\GestionDevis;
use App\Services\Sauvegardes;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SauvegardesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gerant = User::factory()->gerant()->create();
    }

    public function test_sauvegarde_puis_restauration_de_la_base(): void
    {
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        app(Reglages::class)->setSecret('emails.mot_de_passe', 'secret-test');
        $client = Client::factory()->create(['nom' => 'Avant']);
        $gestion = app(GestionDevis::class);
        $devis = $gestion->creer($client, null, 'Toiture', $this->gerant->id);
        $gestion->remplacerLignes($devis, [['type' => 'ligne', 'designation' => 'Tuiles', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 10000]]);

        $this->artisan('app:sauvegarder')->assertSuccessful();
        $liste = app(Sauvegardes::class)->liste();
        $this->assertEqualsCanonicalizing(['base', 'fichiers'], array_unique(array_column($liste, 'type')));
        $base = collect($liste)->firstWhere('type', 'base')['nom'];

        // Le secret reste chiffré dans la sauvegarde.
        $contenu = gzdecode(Storage::disk('local')->get('sauvegardes/'.$base));
        $this->assertStringNotContainsString('secret-test', $contenu);
        $this->assertStringContainsString('Avant', $contenu);

        // Changements après la sauvegarde…
        $client->update(['nom' => 'Après']);
        Client::factory()->create(['nom' => 'Nouveau']);
        $devis->delete();

        $this->travel(1)->seconds();
        $this->artisan('app:restaurer', ['fichier' => $base, '--force' => true])->assertSuccessful();

        // … ils ont disparu : on retrouve l'état sauvegardé.
        $this->assertSame(['Avant'], Client::pluck('nom')->all());
        $this->assertSame(10000, Devis::firstOrFail()->total_ht);
        $this->assertSame(1, Devis::firstOrFail()->lignes()->count());
        $this->assertSame('Entreprise Fictive', reglage('identite.nom_commercial'));
        $this->assertSame('secret-test', app(Reglages::class)->getSecret('emails.mot_de_passe'));
        $this->assertTrue(User::whereKey($this->gerant->id)->exists());

        // Une sauvegarde de l'état d'avant la restauration a été faite.
        $this->assertGreaterThanOrEqual(2, collect(app(Sauvegardes::class)->liste())->where('type', 'base')->count());
    }

    public function test_restauration_refusee_sans_confirmation_ou_fichier_inconnu(): void
    {
        $this->artisan('app:restaurer', ['fichier' => 'base-inconnue.json.gz', '--force' => true])->assertFailed();
        $this->artisan('app:sauvegarder');
        $nom = app(Sauvegardes::class)->liste()[0]['nom'];
        Client::factory()->create();
        $this->artisan('app:restaurer', ['fichier' => $nom])->expectsConfirmation('Toutes les données actuelles seront remplacées par celles de la sauvegarde. Continuer ?', 'no')->assertFailed();
        $this->assertSame(1, Client::count());
    }

    public function test_page_des_sauvegardes_et_telechargement(): void
    {
        $this->actingAs($this->gerant)->get(route('reglages.sauvegardes'))->assertOk()->assertSee('Aucune sauvegarde');
        $this->post(route('reglages.sauvegardes.maintenant'))->assertSessionHas('statut');
        $nom = app(Sauvegardes::class)->liste()[0]['nom'];
        $this->get(route('reglages.sauvegardes'))->assertSee('Base de données');
        $this->get(route('reglages.sauvegardes.telecharger', $nom))->assertOk()->assertDownload($nom);
        $this->get(route('reglages.sauvegardes.telecharger', '..%2F.env'))->assertNotFound();

        $commercial = User::factory()->create();
        $this->actingAs($commercial)->get(route('reglages.sauvegardes.telecharger', $nom))->assertForbidden();
    }

    public function test_on_garde_30_sauvegardes_de_la_base(): void
    {
        for ($i = 0; $i < 32; $i++) {
            Storage::disk('local')->put('sauvegardes/base-2026-01-'.str_pad((string) ($i % 28 + 1), 2, '0', STR_PAD_LEFT).'-0000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'.json.gz', 'x');
        }
        app(Sauvegardes::class)->sauvegarderBase();
        $this->assertCount(30, collect(app(Sauvegardes::class)->liste())->where('type', 'base'));
    }
}
