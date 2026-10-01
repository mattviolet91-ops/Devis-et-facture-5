<?php

namespace Tests\Feature;

use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReglagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucune_donnee_d_entreprise_par_defaut(): void
    {
        $valeurs = config('entreprise');

        array_walk_recursive($valeurs, function ($valeur, $cle) {
            $this->assertEmpty($valeur, "La valeur par défaut « {$cle} » doit être vide.");
        });

        $this->assertSame('', reglage('identite.nom_commercial'));
        $this->assertNull(reglage('setup.completed_at'));
    }

    public function test_enregistrer_et_relire_un_reglage(): void
    {
        $reglages = app(Reglages::class);

        $reglages->set('identite.nom_commercial', 'Entreprise Fictive');
        $reglages->set('documents.acompte', ['pourcentage' => 30]);

        $this->assertSame('Entreprise Fictive', reglage('identite.nom_commercial'));
        $this->assertSame(['pourcentage' => 30], reglage('documents.acompte'));
        $this->assertDatabaseCount('settings', 2);
    }

    public function test_le_cache_est_vide_a_chaque_modification(): void
    {
        $reglages = app(Reglages::class);
        $reglages->set('identite.ville', 'Ville A');
        $this->assertSame('Ville A', reglage('identite.ville'));

        $reglages->set('identite.ville', 'Ville B');
        $this->assertSame('Ville B', app(Reglages::class)->get('identite.ville'));

        $reglages->oublier('identite.ville');
        $this->assertSame('', reglage('identite.ville'));
    }

    public function test_valeur_par_defaut_pour_une_cle_inconnue(): void
    {
        $this->assertSame('x', reglage('cle.inconnue', 'x'));
    }
}
