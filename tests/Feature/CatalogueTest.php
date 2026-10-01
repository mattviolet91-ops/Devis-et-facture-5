<?php

namespace Tests\Feature;

use App\Models\Prestation;
use App\Models\User;
use App\Services\CatalogueDepart;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
    }

    public function test_catalogue_vide_et_chargement_du_catalogue_de_depart(): void
    {
        app(Reglages::class)->set('catalogue.depart_a_charger', 'couvreur');

        $this->actingAs($this->gerant)->get('/catalogue')
            ->assertSee('Votre catalogue est vide')
            ->assertSee('Charger le catalogue de départ de mon métier');

        $this->post('/catalogue/depart')->assertRedirect('/catalogue');

        $this->assertGreaterThan(10, Prestation::count());
        // Aucun prix n'est inventé : chaque entreprise saisit les siens.
        $this->assertSame(0, Prestation::whereNotNull('prix_ht')->count());
        $this->assertFalse(app(CatalogueDepart::class)->enAttente());

        $this->get('/catalogue')
            ->assertSee('Démoussage de toiture')
            ->assertSee('Prix à compléter')
            ->assertSee('sans prix');
    }

    public function test_le_menu_de_configuration_charge_le_catalogue_du_metier(): void
    {
        $this->actingAs($this->gerant)->put('/configuration/metier', ['metier' => 'plombier']);

        $this->assertTrue(Prestation::where('nom', 'Entretien de chaudière')->exists());

        // Changer d'avis ensuite ne remplace pas un catalogue déjà commencé.
        $this->put('/configuration/metier', ['metier' => 'couvreur']);
        $this->assertFalse(Prestation::where('nom', 'Démoussage de toiture')->exists());
    }

    public function test_creer_modifier_supprimer_une_prestation(): void
    {
        $this->actingAs($this->gerant)->post('/catalogue', [
            'nom' => 'Démoussage', 'categorie' => 'Entretien', 'prix' => '12,50', 'unite' => 'm²', 'taux_tva' => '1000',
        ])->assertRedirect('/catalogue');

        $prestation = Prestation::firstOrFail();
        $this->assertSame(1250, $prestation->prix_ht);
        $this->assertSame(1000, $prestation->taux_tva);

        $this->get('/catalogue')->assertSee('12,50')->assertSee('HT / m²');

        $this->put(route('catalogue.update', $prestation), ['nom' => 'Démoussage', 'prix' => '1 500', 'unite' => 'forfait', 'taux_tva' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame(150000, $prestation->fresh()->prix_ht);
        $this->assertNull($prestation->fresh()->taux_tva);

        $this->delete(route('catalogue.destroy', $prestation))->assertRedirect('/catalogue');
        $this->assertSoftDeleted($prestation);
    }

    public function test_controles(): void
    {
        $this->actingAs($this->gerant)->post('/catalogue', ['nom' => '', 'prix' => 'douze', 'unite' => 'litre'])
            ->assertSessionHasErrors(['nom', 'unite', 'prix_lisible' => 'Le prix est illisible. Écrivez par exemple 12,50.']);
        $this->assertDatabaseCount('prestations', 0);
    }

    public function test_recherche_rapide_pour_les_devis(): void
    {
        Prestation::factory()->create(['nom' => 'Faîtière scellée', 'prix_ht' => 4500, 'unite' => 'u']);
        Prestation::factory()->create(['nom' => 'Démoussage de toiture']);

        $commercial = User::factory()->create();
        $reponse = $this->actingAs($commercial)->getJson('/catalogue/recherche?q=faitiere')->assertOk();

        $this->assertCount(1, $reponse->json());
        $reponse->assertJsonPath('0.nom', 'Faîtière scellée')
            ->assertJsonPath('0.prix_ht', 4500)
            ->assertJsonPath('0.taux_tva', (int) reglage('tva.taux_defaut'));
    }

    public function test_tva_zero_en_franchise(): void
    {
        app(Reglages::class)->set('tva.regime', 'franchise');
        $prestation = Prestation::factory()->create(['taux_tva' => 2000]);

        $this->assertSame(0, $prestation->tauxEffectif());
    }

    public function test_le_commercial_consulte_mais_ne_modifie_pas(): void
    {
        $prestation = Prestation::factory()->create(['nom' => 'Démoussage']);
        $commercial = User::factory()->create();

        $this->actingAs($commercial)->get('/catalogue')->assertOk()->assertSee('Démoussage')->assertDontSee('Nouvelle prestation');
        $this->get(route('catalogue.edit', $prestation))->assertForbidden();
        $this->post('/catalogue', ['nom' => 'X', 'unite' => 'u'])->assertForbidden();
        $this->delete(route('catalogue.destroy', $prestation))->assertForbidden();
    }

    public function test_la_demonstration_a_des_prix_d_exemple(): void
    {
        $this->artisan('app:demo', ['--force' => false])->expectsConfirmation('Des comptes existent déjà. Ajouter quand même les données de démonstration ?', 'yes')->assertSuccessful();

        $this->assertSame(1200, Prestation::where('nom', 'Démoussage de toiture')->value('prix_ht'));
        $this->assertSame(0, Prestation::whereNull('prix_ht')->count());
    }
}
