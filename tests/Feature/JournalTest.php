<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_gerant_voit_le_journal(): void
    {
        $gerant = User::factory()->gerant()->create(['email' => 'g@exemple.test']);
        $this->actingAs($gerant);
        Journal::ecrire('test', 'Une action de test');

        $this->get('/journal')->assertOk()->assertSee('Une action de test')->assertSee('g@exemple.test');
    }

    public function test_le_commercial_n_a_pas_acces_au_journal(): void
    {
        $this->actingAs(User::factory()->create())->get('/journal')
            ->assertForbidden()
            ->assertSee('Accès refusé');
    }

    public function test_le_journal_enregistre_l_adresse_ip_et_l_auteur(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activite = Journal::ecrire('test', 'Action');

        $this->assertSame($user->id, $activite->user_id);
        $this->assertSame('127.0.0.1', $activite->ip_address);
    }

    public function test_journal_vide(): void
    {
        $this->actingAs(User::factory()->gerant()->create())->get('/journal')->assertSee('Aucune activité');
    }
}
