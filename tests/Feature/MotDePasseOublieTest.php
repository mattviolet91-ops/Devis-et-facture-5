<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LienMotDePasse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MotDePasseOublieTest extends TestCase
{
    use RefreshDatabase;

    public function test_demande_de_lien(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'c@exemple.test']);

        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Recevoir le lien');

        $this->post('/mot-de-passe-oublie', ['email' => 'c@exemple.test'])
            ->assertSessionHas('statut');

        Notification::assertSentTo($user, LienMotDePasse::class, function (LienMotDePasse $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->subject === 'Choisir un nouveau mot de passe'
                && str_contains($mail->actionUrl, '/nouveau-mot-de-passe/');
        });
    }

    public function test_meme_message_si_le_compte_n_existe_pas(): void
    {
        Notification::fake();

        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@exemple.test'])
            ->assertSessionHas('statut')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_choisir_un_nouveau_mot_de_passe(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'd@exemple.test']);
        $this->post('/mot-de-passe-oublie', ['email' => 'd@exemple.test']);

        $jeton = null;
        Notification::assertSentTo($user, LienMotDePasse::class, function (LienMotDePasse $n) use (&$jeton) {
            $jeton = $n->token;

            return true;
        });

        $this->get('/nouveau-mot-de-passe/'.$jeton.'?email=d@exemple.test')->assertOk()->assertSee('Nouveau mot de passe');

        $this->post('/nouveau-mot-de-passe', [
            'token' => $jeton,
            'email' => 'd@exemple.test',
            'password' => 'unNouveauMotDePasse',
            'password_confirmation' => 'unNouveauMotDePasse',
        ])->assertRedirect('/connexion')->assertSessionHas('statut');

        $this->assertTrue(Hash::check('unNouveauMotDePasse', $user->fresh()->password));
    }

    public function test_lien_invalide(): void
    {
        $user = User::factory()->create(['email' => 'e@exemple.test']);

        $this->post('/nouveau-mot-de-passe', [
            'token' => 'faux',
            'email' => 'e@exemple.test',
            'password' => 'unNouveauMotDePasse',
            'password_confirmation' => 'unNouveauMotDePasse',
        ])->assertSessionHasErrors(['email' => 'Ce lien n\'est plus valable. Demandez-en un nouveau.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_mot_de_passe_trop_court(): void
    {
        $this->post('/nouveau-mot-de-passe', [
            'token' => 'x',
            'email' => 'e@exemple.test',
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertSessionHasErrors(['password' => '« mot de passe » doit faire au moins 10 caractères.']);
    }
}
