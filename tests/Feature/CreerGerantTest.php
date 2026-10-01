<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreerGerantTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configurationTerminee = false;

    public function test_creation_du_compte_gerant(): void
    {
        $this->artisan('app:creer-gerant')
            ->expectsQuestion('Email de connexion du gérant', 'gerant@exemple.test')
            ->expectsQuestion('Mot de passe (10 caractères minimum, il ne s\'affiche pas)', 'MotDePasseSolide')
            ->expectsQuestion('Retapez le mot de passe', 'MotDePasseSolide')
            ->expectsOutputToContain('Compte gérant créé')
            ->assertSuccessful();

        $user = User::where('email', 'gerant@exemple.test')->firstOrFail();
        $this->assertTrue($user->estGerant());
        $this->assertTrue($user->is_active);
        $this->assertNull($user->name);
        $this->assertTrue(Hash::check('MotDePasseSolide', $user->password));
        $this->assertDatabaseMissing('activites', ['description' => 'MotDePasseSolide']);
    }

    public function test_mot_de_passe_trop_court_refuse(): void
    {
        $this->artisan('app:creer-gerant', ['--email' => 'g@exemple.test'])
            ->expectsQuestion('Mot de passe (10 caractères minimum, il ne s\'affiche pas)', 'court')
            ->expectsQuestion('Retapez le mot de passe', 'court')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_les_deux_saisies_doivent_etre_identiques(): void
    {
        $this->artisan('app:creer-gerant', ['--email' => 'g@exemple.test'])
            ->expectsQuestion('Mot de passe (10 caractères minimum, il ne s\'affiche pas)', 'MotDePasseSolide')
            ->expectsQuestion('Retapez le mot de passe', 'AutreMotDePasse')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_deja_utilise(): void
    {
        User::factory()->create(['email' => 'g@exemple.test']);

        $this->artisan('app:creer-gerant', ['--email' => 'g@exemple.test'])
            ->expectsOutput('Un compte existe déjà avec cet email.')
            ->assertFailed();
    }

    public function test_la_base_de_depart_est_vide(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('settings', 0);
    }
}
