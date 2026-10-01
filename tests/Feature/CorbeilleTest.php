<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Corbeille;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ElementDeTest extends Model
{
    use SoftDeletes;

    protected $table = 'elements_test';

    protected $guarded = [];

    public function libelleCorbeille(): string
    {
        return $this->nom;
    }
}

class CorbeilleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('elements_test', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
            $table->softDeletes();
        });

        Corbeille::enregistrer('test', ElementDeTest::class, 'Élément');
    }

    protected function tearDown(): void
    {
        Corbeille::retirer('test');
        parent::tearDown();
    }

    public function test_un_element_supprime_va_dans_la_corbeille_et_peut_etre_remis(): void
    {
        $gerant = User::factory()->gerant()->create();
        $element = ElementDeTest::create(['nom' => 'Chantier fictif']);
        $element->delete();

        $this->actingAs($gerant)->get('/corbeille')
            ->assertOk()
            ->assertSee('Chantier fictif')
            ->assertSee('encore 30 jour(s)');

        $this->post("/corbeille/test/{$element->id}/restaurer")->assertRedirect('/corbeille');

        $this->assertFalse($element->fresh()->trashed());
        $this->assertDatabaseHas('activites', ['action' => 'corbeille.restauration']);
    }

    public function test_purge_automatique_apres_30_jours(): void
    {
        $ancien = ElementDeTest::create(['nom' => 'Ancien']);
        $recent = ElementDeTest::create(['nom' => 'Récent']);
        $actif = ElementDeTest::create(['nom' => 'Actif']);

        $this->travel(-31)->days();
        $ancien->delete();
        $this->travelBack();
        $this->travel(-29)->days();
        $recent->delete();
        $this->travelBack();

        $this->artisan('app:purger-corbeille')->expectsOutput('1 élément(s) supprimé(s) définitivement.')->assertSuccessful();

        $this->assertDatabaseMissing('elements_test', ['id' => $ancien->id]);
        $this->assertNotNull(ElementDeTest::withTrashed()->find($recent->id));
        $this->assertNotNull(ElementDeTest::find($actif->id));
    }

    public function test_la_purge_est_planifiee_chaque_jour(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('app:purger-corbeille')->assertSuccessful();
    }

    public function test_corbeille_vide(): void
    {
        $this->actingAs(User::factory()->gerant()->create())->get('/corbeille')->assertSee('La corbeille est vide.');
    }

    public function test_type_inconnu(): void
    {
        $this->actingAs(User::factory()->gerant()->create())->post('/corbeille/inconnu/1/restaurer')->assertNotFound();
    }

    public function test_le_commercial_n_a_pas_acces_a_la_corbeille(): void
    {
        $this->actingAs(User::factory()->create())->get('/corbeille')->assertForbidden();
    }
}
