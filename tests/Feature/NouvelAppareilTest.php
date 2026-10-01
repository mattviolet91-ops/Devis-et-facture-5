<?php

namespace Tests\Feature;

use App\Models\Appareil;
use App\Models\User;
use App\Notifications\NouvelAppareil;
use App\Services\DetecteurAppareil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NouvelAppareilTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private function connecter(string $email, ?string $jeton = null): TestResponse
    {
        $requete = $this->withHeader('User-Agent', self::IPHONE);
        if ($jeton) {
            $requete = $requete->withCookie(DetecteurAppareil::COOKIE, $jeton);
        }

        $reponse = $requete->post('/connexion', ['email' => $email, 'password' => 'password']);
        $this->post('/deconnexion');

        return $reponse;
    }

    public function test_le_premier_appareil_est_enregistre_sans_alerte(): void
    {
        Notification::fake();
        $user = User::factory()->gerant()->create(['email' => 'g@exemple.test']);

        $this->connecter('g@exemple.test')->assertCookie(DetecteurAppareil::COOKIE);

        $this->assertSame(1, $user->appareils()->count());
        $this->assertSame('iPhone – Safari', $user->appareils()->first()->libelle);
        Notification::assertNothingSent();
    }

    public function test_un_nouvel_appareil_declenche_une_alerte_au_compte_et_au_gerant(): void
    {
        Notification::fake();
        $gerant = User::factory()->gerant()->create();
        $commercial = User::factory()->create(['email' => 'com@exemple.test']);
        Appareil::create(['user_id' => $commercial->id, 'empreinte' => hash('sha256', 'ancien'), 'libelle' => 'Android']);

        $this->connecter('com@exemple.test');

        Notification::assertSentTo([$commercial, $gerant], NouvelAppareil::class);
        $this->assertSame(2, $commercial->appareils()->count());
    }

    public function test_un_appareil_connu_ne_declenche_pas_d_alerte(): void
    {
        Notification::fake();
        $user = User::factory()->gerant()->create(['email' => 'h@exemple.test']);
        $jeton = str_repeat('a', 64);
        Appareil::create(['user_id' => $user->id, 'empreinte' => hash('sha256', $jeton), 'libelle' => 'iPhone']);
        Appareil::create(['user_id' => $user->id, 'empreinte' => hash('sha256', 'autre'), 'libelle' => 'PC']);

        $this->connecter('h@exemple.test', $jeton);

        Notification::assertNothingSent();
        $this->assertSame(2, $user->appareils()->count());
    }

    public function test_le_jeton_n_est_jamais_stocke_en_clair(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'i@exemple.test']);
        $jeton = str_repeat('b', 64);

        $this->connecter('i@exemple.test', $jeton);

        $this->assertDatabaseMissing('appareils', ['empreinte' => $jeton]);
        $this->assertDatabaseHas('appareils', ['empreinte' => hash('sha256', $jeton)]);
    }

    public function test_l_alerte_apparait_sur_l_accueil_et_peut_etre_marquee_vue(): void
    {
        $gerant = User::factory()->gerant()->create();
        $appareil = Appareil::create(['user_id' => $gerant->id, 'empreinte' => hash('sha256', 'x'), 'libelle' => 'Android – Chrome']);
        $gerant->notifyNow(new NouvelAppareil($gerant, $appareil), ['database']);

        $this->actingAs($gerant)->get('/accueil')->assertSee('Nouvel appareil')->assertSee('Android – Chrome');

        $id = $gerant->unreadNotifications()->first()->id;
        $this->post("/alertes/{$id}/lue")->assertRedirect('/accueil');
        $this->assertSame(0, $gerant->unreadNotifications()->count());
    }

    public function test_libelles_des_appareils(): void
    {
        $this->assertSame('Android – Chrome', DetecteurAppareil::libelle('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36'));
        $this->assertSame('Windows – Edge', DetecteurAppareil::libelle('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0'));
        $this->assertSame('Appareil inconnu', DetecteurAppareil::libelle(''));
    }
}
