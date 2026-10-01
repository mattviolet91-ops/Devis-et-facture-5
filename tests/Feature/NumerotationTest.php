<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Numerotation;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumerotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeros_continus_par_type_et_par_annee(): void
    {
        $this->travelTo('2026-03-15');
        $n = app(Numerotation::class);

        $this->assertSame('DEV-2026-0001', $n->attribuer('devis'));
        $this->assertSame('DEV-2026-0002', $n->attribuer('devis'));
        $this->assertSame('FAC-2026-0001', $n->attribuer('facture'));
        $this->assertSame('AV-2026-0001', $n->attribuer('avoir'));
        $this->assertSame('DEV-2026-0003', $n->prochain('devis'));

        $this->travelTo('2027-01-02');
        $this->assertSame('DEV-2027-0001', $n->attribuer('devis'));
    }

    public function test_reprendre_une_numerotation_existante(): void
    {
        app(Reglages::class)->set('numerotation.facture_premier', 158);
        app(Reglages::class)->set('numerotation.facture_prefixe', 'F');

        $this->assertSame('F-'.now()->year.'-0158', app(Numerotation::class)->attribuer('facture'));
        $this->assertSame('F-'.now()->year.'-0159', app(Numerotation::class)->attribuer('facture'));
    }

    public function test_aucun_trou_si_l_enregistrement_echoue(): void
    {
        $n = app(Numerotation::class);
        $n->attribuer('facture');

        try {
            DB::transaction(function () use ($n) {
                $n->attribuer('facture');
                throw new \RuntimeException('échec de l\'enregistrement');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('FAC-'.now()->year.'-0002', $n->attribuer('facture'));
    }

    public function test_reglages_verrouilles_une_fois_des_numeros_attribues(): void
    {
        $gerant = User::factory()->gerant()->create();
        $donnees = [
            'numerotation__devis_prefixe' => 'DV', 'numerotation__devis_premier' => '10',
            'numerotation__facture_prefixe' => 'FA', 'numerotation__facture_premier' => '50',
            'numerotation__avoir_prefixe' => 'AV', 'numerotation__avoir_premier' => '1',
        ];

        $this->actingAs($gerant)->get('/reglages/numerotation')->assertSee('Prochain numéro : DEV-'.now()->year.'-0001');
        $this->put('/reglages/numerotation', $donnees)->assertSessionHasNoErrors();
        $this->assertSame(50, reglage('numerotation.facture_premier'));

        app(Numerotation::class)->attribuer('facture');

        $this->get('/reglages/numerotation')->assertSee('ne peut plus changer');
        $this->put('/reglages/numerotation', array_merge($donnees, ['numerotation__facture_premier' => '1', 'numerotation__facture_prefixe' => 'XX']))->assertSessionHasNoErrors();

        $this->assertSame(50, reglage('numerotation.facture_premier'));
        $this->assertSame('FA', reglage('numerotation.facture_prefixe'));
        $this->assertSame('FA-'.now()->year.'-0051', app(Numerotation::class)->attribuer('facture'));
    }

    public function test_prefixe_invalide(): void
    {
        $this->actingAs(User::factory()->gerant()->create())
            ->put('/reglages/numerotation', [
                'numerotation__devis_prefixe' => 'dév', 'numerotation__devis_premier' => '1',
                'numerotation__facture_prefixe' => 'FAC', 'numerotation__facture_premier' => '0',
                'numerotation__avoir_prefixe' => 'AV', 'numerotation__avoir_premier' => '1',
            ])
            ->assertSessionHasErrors(['numerotation__devis_prefixe', 'numerotation__facture_premier']);
    }

    public function test_type_inconnu(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(Numerotation::class)->attribuer('bon');
    }
}
