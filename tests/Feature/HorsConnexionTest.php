<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorsConnexionTest extends TestCase
{
    public function test_les_fichiers_gardes_par_le_service_worker_existent(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));
        preg_match('/var FICHIERS_FIXES = \[(.*?)\];/s', $sw, $m);
        preg_match_all("/'([^']+)'/", $m[1] ?? '', $fichiers);

        $this->assertNotEmpty($fichiers[1]);
        foreach ($fichiers[1] as $fichier) {
            $this->assertFileExists(public_path(ltrim($fichier, '/')), "Le service worker garde {$fichier}, qui n'existe pas (installation impossible).");
        }
    }

    public function test_pages_jamais_gardees(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));
        // L'espace client, l'API et la connexion ne sont jamais gardés sur le téléphone.
        foreach (['c\/', 'api\/', 'connexion', 'invitation'] as $motif) {
            $this->assertStringContainsString($motif, $sw);
        }
        $this->assertStringContainsString("'oublier-pages'", $sw);
    }
}
