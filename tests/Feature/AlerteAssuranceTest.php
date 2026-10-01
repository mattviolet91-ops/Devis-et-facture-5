<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AssuranceBientotFinie;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlerteAssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alertes_avant_l_echeance_une_seule_fois_par_palier(): void
    {
        Notification::fake();
        $gerant = User::factory()->gerant()->create();
        User::factory()->create();
        app(Reglages::class)->set('assurance.date_fin', '2026-12-31');

        $this->travelTo('2026-11-01');
        $this->artisan('app:alerte-assurance')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo('2026-12-05');
        $this->artisan('app:alerte-assurance')->assertSuccessful();
        $this->artisan('app:alerte-assurance')->assertSuccessful();
        $this->assertSame(1, $this->alertesEcran($gerant));
        $this->assertSame(1, Notification::sent($gerant, AssuranceBientotFinie::class, fn ($n, $canaux) => $canaux === ['mail'])->count());

        $this->travelTo('2026-12-27');
        $this->artisan('app:alerte-assurance');
        $this->assertSame(2, $this->alertesEcran($gerant));

        $this->travelTo('2027-01-02');
        $this->artisan('app:alerte-assurance');
        Notification::assertSentTo($gerant, AssuranceBientotFinie::class, fn ($n) => $n->joursRestants < 0);
        $this->assertSame(3, $this->alertesEcran($gerant));
    }

    private function alertesEcran(User $user): int
    {
        return Notification::sent($user, AssuranceBientotFinie::class, fn ($n, $canaux) => $canaux === ['database'])->count();
    }

    public function test_texte_de_l_alerte(): void
    {
        $gerant = User::factory()->gerant()->create();
        app(Reglages::class)->set('assurance.date_fin', now()->addDays(10)->toDateString());

        $this->artisan('app:alerte-assurance')->assertSuccessful();

        $this->actingAs($gerant)->get('/accueil')->assertSee('Assurance décennale')->assertSee('dans 10 jour(s)');
    }

    public function test_sans_date_rien_ne_se_passe(): void
    {
        Notification::fake();
        User::factory()->gerant()->create();

        $this->artisan('app:alerte-assurance')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_planifiee_chaque_jour(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('app:alerte-assurance');
    }
}
