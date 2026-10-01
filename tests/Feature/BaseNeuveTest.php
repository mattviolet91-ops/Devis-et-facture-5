<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaseNeuveTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuse_une_base_qui_contient_des_tables(): void
    {
        $this->artisan('app:base-neuve')->expectsOutputToContain('Refusé')->assertFailed();
    }

    public function test_accepte_une_base_vide(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'base');
        $avant = config('database.default');
        config(['database.connections.vide' => ['driver' => 'sqlite', 'database' => $chemin, 'prefix' => '', 'foreign_key_constraints' => true]]);
        config(['database.default' => 'vide']);

        try {
            $this->artisan('app:base-neuve')->expectsOutputToContain('Base vide')->assertSuccessful();
        } finally {
            config(['database.default' => $avant]);
            DB::purge('vide');
            unlink($chemin);
        }
    }

    public function test_aucune_donnee_de_demonstration_dans_le_seeder(): void
    {
        $seeder = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('app:demo', $seeder);
        $this->assertStringNotContainsString('DonneesDemo', $seeder);
        $script = (string) file_get_contents(base_path('outils/installer-entreprise.sh'));
        $this->assertStringNotContainsString('app:demo', $script);
        $this->assertStringContainsString('app:base-neuve', $script);
    }
}
