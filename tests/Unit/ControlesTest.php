<?php

namespace Tests\Unit;

use App\Rules\Iban;
use App\Rules\Luhn;
use App\Rules\Siret;
use App\Rules\TvaIntracom;
use App\Support\Couleurs;
use App\Support\Montant;
use App\Support\Tva;
use PHPUnit\Framework\TestCase;

class ControlesTest extends TestCase
{
    /**
     * Fabrique un SIRET fictif dont la clé est juste (aucun numéro réel dans les tests).
     */
    public static function siretFictif(string $debut = '1234567890123'): string
    {
        for ($c = 0; $c <= 9; $c++) {
            if (Luhn::estValide($debut.$c)) {
                return $debut.$c;
            }
        }

        throw new \LogicException('impossible');
    }

    public function test_siret(): void
    {
        $siret = self::siretFictif();

        $this->assertTrue(Siret::estValide($siret));
        $this->assertTrue(Siret::estValide(chunk_split($siret, 3, ' ')));
        $this->assertFalse(Siret::estValide(substr($siret, 0, 13).(((int) substr($siret, -1) + 1) % 10)));
        $this->assertFalse(Siret::estValide('1234567890'));
        $this->assertFalse(Siret::estValide('1234567890123A'));
        // Exception La Poste : somme des chiffres multiple de 5.
        $this->assertTrue(Siret::estValide('35600000000010'));
    }

    public function test_tva_intracommunautaire(): void
    {
        $siren = substr(self::siretFictif(), 0, 9);
        $tva = 'FR'.TvaIntracom::cle($siren).$siren;

        $erreurs = [];
        (new TvaIntracom(self::siretFictif()))->validate('tva', $tva, function ($m) use (&$erreurs) {
            $erreurs[] = $m;
        });
        $this->assertSame([], $erreurs);

        (new TvaIntracom)->validate('tva', 'FR00'.$siren, function ($m) use (&$erreurs) {
            $erreurs[] = $m;
        });
        $this->assertCount(1, $erreurs);

        $erreurs = [];
        (new TvaIntracom('98765432100017'))->validate('tva', $tva, function ($m) use (&$erreurs) {
            $erreurs[] = $m;
        });
        $this->assertStringContainsString('ne correspond pas au SIRET', $erreurs[0]);
    }

    public function test_iban(): void
    {
        // Exemple publié dans les documentations bancaires (compte fictif).
        $this->assertTrue(Iban::estValide('FR76 3000 6000 0112 3456 7890 189'));
        $this->assertTrue(Iban::estValide('fr7630006000011234567890189'));
        $this->assertFalse(Iban::estValide('FR76 3000 6000 0112 3456 7890 188'));
        $this->assertFalse(Iban::estValide('FR76 3000 6000 0112 3456 7890'));
        $this->assertFalse(Iban::estValide('pas un iban'));
        $this->assertSame('FR76 3000 6000 0112 3456 7890 189', Iban::formater('fr7630006000011234567890189'));
    }

    public function test_taux_de_tva_en_entiers(): void
    {
        $this->assertSame([2000, 1000, 550, 0], Tva::lireListe('20 ; 10 ; 5,5 ; 0'));
        $this->assertSame([2000, 550], Tva::lireListe("5.5%\n20 %"));
        $this->assertNull(Tva::lireListe('20 ; dix'));
        $this->assertNull(Tva::lireListe('150'));
        $this->assertSame(210, Tva::lire('2,1'));
        $this->assertSame('5,5 %', Tva::formater(550));
        $this->assertSame('20 %', Tva::formater(2000));
        $this->assertSame('0 %', Tva::formater(0));
    }

    public function test_contraste_des_couleurs(): void
    {
        $this->assertSame('#ffffff', Couleurs::texteSur('#1f4e79'));
        $this->assertSame('#1b1d21', Couleurs::texteSur('#ffd700'));
        $this->assertGreaterThanOrEqual(4.5, Couleurs::contraste(Couleurs::pourModeSombre('#1f4e79'), '#1d2025'));
        $this->assertGreaterThanOrEqual(4.5, Couleurs::contraste(Couleurs::pourModeClair('#ffd700'), '#ffffff'));
    }

    public function test_montants_en_centimes(): void
    {
        $this->assertSame("1\u{202F}234,56\u{00A0}€", Montant::formater(123456));
        $this->assertSame("0,05\u{00A0}€", Montant::formater(5));
        $this->assertSame("-12,00\u{00A0}€", Montant::formater(-1200));
        $this->assertSame(123456, Montant::lire('1 234,56'));
        $this->assertSame(150000, Montant::lire('1500 €'));
        $this->assertSame(1250, Montant::lire('12.5'));
        $this->assertNull(Montant::lire('douze'));
        // TVA arrondie au centime : 10,00 € à 5,5 % = 0,55 € ; 0,09 € à 5,5 % = 0,005 → 0,01 €.
        $this->assertSame(55, Montant::tva(1000, 550));
        $this->assertSame(0, Montant::tva(9, 550));
        $this->assertSame(1, Montant::tva(10, 550));
        $this->assertSame(24000, Montant::tva(120000, 2000));
    }
}
