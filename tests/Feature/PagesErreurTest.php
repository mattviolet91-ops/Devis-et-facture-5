<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesErreurTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function codes(): array
    {
        return [
            '403' => [403, 'Accès refusé'],
            '404' => [404, 'Page introuvable'],
            '419' => [419, 'Page expirée'],
            '429' => [429, 'Trop d&#039;essais'],
            '500' => [500, 'Erreur de l&#039;application'],
            '503' => [503, 'Mise à jour en cours'],
        ];
    }

    #[DataProvider('codes')]
    public function test_pages_d_erreur_en_francais(int $code, string $titre): void
    {
        Route::get('/test-erreur', fn () => abort($code));

        $this->get('/test-erreur')
            ->assertStatus($code)
            ->assertSee($titre, false)
            ->assertSee('<html lang="fr">', false)
            ->assertDontSee('Laravel');
    }

    public function test_une_vraie_erreur_ne_montre_pas_de_detail_technique(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-exception', fn () => throw new \RuntimeException('secret technique'));

        $this->get('/test-exception')
            ->assertStatus(500)
            ->assertSee('Erreur de l&#039;application', false)
            ->assertDontSee('secret technique');
    }
}
