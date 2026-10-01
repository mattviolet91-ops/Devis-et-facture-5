<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ControleSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_signale_ce_qui_manque(): void
    {
        Storage::fake('local');
        config(['app.debug' => true, 'app.url' => 'http://exemple.test', 'session.secure' => false]);

        $this->artisan('app:security-check')
            ->expectsOutputToContain('✗ Mode production')
            ->expectsOutputToContain('✗ Sauvegarde de moins de 2 jours')
            ->expectsOutputToContain('✗ Au moins un compte gérant actif')
            ->assertFailed();
    }

    public function test_tout_en_ordre(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sauvegardes/base-'.now()->format('Y-m-d-His').'.json.gz', 'x');
        User::factory()->gerant()->create();
        $this->app['env'] = 'production';
        $env = tempnam(sys_get_temp_dir(), 'env');
        chmod($env, 0600);
        $this->app->loadEnvironmentFrom(basename($env));
        $this->app->useEnvironmentPath(dirname($env));
        config(['app.debug' => false, 'app.url' => 'https://gestion.exemple.test', 'session.secure' => true, 'session.http_only' => true]);
        Http::fake(['gestion.exemple.test/.env' => Http::response('', 403)]);

        $this->artisan('app:security-check', ['--http' => true])->expectsOutputToContain('Tout est en ordre.')->assertSuccessful();
        unlink($env);
    }
}
