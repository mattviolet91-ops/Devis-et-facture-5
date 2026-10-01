<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AlerteDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeploiementTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_gerant_est_prevenu_de_la_mise_a_jour(): void
    {
        Notification::fake();
        $gerant = User::factory()->gerant()->create();
        $commercial = User::factory()->create();

        $this->artisan('app:mise-a-jour')->assertSuccessful();
        Notification::assertSentTo($gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Application mise à jour');
        Notification::assertNotSentTo($commercial, AlerteDocument::class);

        $this->artisan('app:mise-a-jour', ['--echec' => true]);
        Notification::assertSentTo($gerant, AlerteDocument::class, fn ($n) => $n->titre === 'Mise à jour annulée');
    }

    public function test_script_de_deploiement(): void
    {
        $script = (string) file_get_contents(base_path('outils/deploy.sh'));
        $this->assertTrue(is_executable(base_path('outils/deploy.sh')));
        // Sauvegarde avant, retour arrière en cas d'échec, jamais de modification forcée de fichiers locaux.
        $this->assertStringContainsString('app:sauvegarder', $script);
        $this->assertStringContainsString('trap retour_arriere ERR', $script);
        $this->assertStringContainsString('git merge --ff-only', $script);
        $this->assertStringContainsString('git diff --quiet', $script);
        $this->assertLessThan(strpos($script, 'migrate --force'), strpos($script, 'app:sauvegarder'));
    }
}
