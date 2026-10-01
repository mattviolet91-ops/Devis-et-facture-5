<?php

namespace Tests\Unit;

use App\Services\CalculDevis;
use App\Support\Quantite;
use App\Support\Toiture;
use PHPUnit\Framework\TestCase;

class CalculDevisTest extends TestCase
{
    private function ligne(int $quantite, int $prix, int $taux = 1000, bool $option = false): array
    {
        return ['type' => 'ligne', 'quantite' => $quantite, 'prix_unitaire_ht' => $prix, 'taux_tva' => $taux, 'option' => $option];
    }

    public function test_calcul_exact_ht_tva_ttc(): void
    {
        $r = (new CalculDevis)->calculer([
            $this->ligne(120000, 1200),          // 120 m² × 12,00 € = 1 440,00
            $this->ligne(3000, 15000),           // 3 × 150,00 € = 450,00
            $this->ligne(1000, 15000, 2000),     // forfait 150,00 € à 20 %
        ]);

        $this->assertSame([144000, 45000, 15000], $r['lignes']);
        $this->assertSame(204000, $r['total_ht']);
        $this->assertSame(['base' => 189000, 'montant' => 18900], $r['tva'][1000]);
        $this->assertSame(['base' => 15000, 'montant' => 3000], $r['tva'][2000]);
        $this->assertSame(21900, $r['total_tva']);
        $this->assertSame(225900, $r['total_ttc']);
    }

    public function test_arrondis_au_centime(): void
    {
        // 0,333 m² × 10,01 € = 3,33333 → 3,33 € ; TVA 5,5 % de 3,33 = 0,18315 → 0,18 €.
        $r = (new CalculDevis)->calculer([$this->ligne(333, 1001, 550)]);
        $this->assertSame(333, $r['total_ht']);
        $this->assertSame(18, $r['total_tva']);

        // 1,5 × 0,03 € = 0,045 → 0,05 € (arrondi au plus proche).
        $this->assertSame([5], (new CalculDevis)->calculer([$this->ligne(1500, 3)])['lignes']);
    }

    public function test_options_hors_total_et_sous_totaux_de_sections(): void
    {
        $r = (new CalculDevis)->calculer([
            ['type' => 'section', 'designation' => 'Toiture'],
            $this->ligne(1000, 10000),
            $this->ligne(2000, 5000),
            ['type' => 'texte', 'designation' => 'Remarque'],
            ['type' => 'section', 'designation' => 'Zinguerie'],
            $this->ligne(1000, 30000),
            $this->ligne(1000, 99900, 1000, true),
        ]);

        $this->assertSame([0 => 20000, 4 => 30000], $r['sections']);
        $this->assertSame(50000, $r['total_ht']);
        $this->assertSame(99900, $r['total_options_ht']);
    }

    public function test_remise_en_pourcentage_repartie_entre_les_taux(): void
    {
        $r = (new CalculDevis)->calculer([$this->ligne(1000, 10000, 1000), $this->ligne(1000, 10000, 2000)], 'pourcentage', 1000);

        $this->assertSame(2000, $r['remise']);
        $this->assertSame(18000, $r['total_ht']);
        $this->assertSame(9000, $r['tva'][1000]['base']);
        $this->assertSame(9000, $r['tva'][2000]['base']);
        $this->assertSame(900 + 1800, $r['total_tva']);
    }

    public function test_remise_en_montant_jamais_superieure_au_total(): void
    {
        $r = (new CalculDevis)->calculer([$this->ligne(1000, 3333, 1000), $this->ligne(1000, 6667, 2000)], 'montant', 1001);
        $this->assertSame(1001, $r['remise']);
        $this->assertSame($r['total_ht'], array_sum(array_column($r['tva'], 'base')));

        $r = (new CalculDevis)->calculer([$this->ligne(1000, 1000)], 'montant', 5000);
        $this->assertSame(1000, $r['remise']);
        $this->assertSame(0, $r['total_ttc']);
    }

    public function test_franchise_sans_tva(): void
    {
        $r = (new CalculDevis)->calculer([$this->ligne(1000, 10000, 2000)], null, 0, true);

        $this->assertSame(0, $r['total_tva']);
        $this->assertSame(10000, $r['total_ttc']);
    }

    public function test_quantites_en_milliemes(): void
    {
        $this->assertSame(1500, Quantite::lire('1,5'));
        $this->assertSame(1200000, Quantite::lire('1 200'));
        $this->assertSame(125, Quantite::lire('0.125'));
        $this->assertNull(Quantite::lire('1,2345'));
        $this->assertNull(Quantite::lire('deux'));
        $this->assertSame('1,5', Quantite::formater(1500));
        $this->assertSame("1\u{202F}200", Quantite::formater(1200000));
    }

    public function test_surface_de_toiture_selon_la_pente(): void
    {
        $this->assertSame(100.0, Toiture::surfaceRampant(100, 0));
        $this->assertSame(141.42, Toiture::surfaceRampant(100, 45));
        $this->assertSame(141.42, Toiture::surfaceRampant(100, 100, 'pourcentage'));
        $this->assertSame(115.47, Toiture::surfaceRampant(100, 30));

        $this->expectException(\InvalidArgumentException::class);
        Toiture::surfaceRampant(100, 90);
    }
}
