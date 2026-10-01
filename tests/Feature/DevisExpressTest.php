<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Prestation;
use App\Models\User;
use App\Services\DevisExpress;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevisExpressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Reglages::class)->set('tva.regime', 'assujetti');
        Client::factory()->create(['civilite' => 'Mme', 'nom' => 'Martin', 'prenom' => 'Sophie', 'ville' => 'Ville-Démo']);
        Client::factory()->create(['civilite' => null, 'nom' => 'Bérard', 'prenom' => 'Hélène', 'ville' => 'Saint-Exemple']);
        Client::factory()->create(['civilite' => null, 'nom' => 'Durand', 'prenom' => 'Paul', 'ville' => 'Lyon']);
        Client::factory()->create(['civilite' => null, 'nom' => 'Durand', 'prenom' => 'Jérôme', 'ville' => 'Ville-Démo']);
        Client::factory()->professionnel()->create(['raison_sociale' => 'SCI Les Tilleuls (Ville-Démo)', 'nom' => 'Moreau']);
        Prestation::factory()->create(['nom' => 'Démoussage de toiture', 'unite' => 'm²', 'prix_ht' => 1200]);
        Prestation::factory()->create(['nom' => 'Faîtière scellée', 'unite' => 'u', 'prix_ht' => 4500]);
        Prestation::factory()->create(['nom' => 'Gouttière zinc', 'unite' => 'ml', 'prix_ht' => 6500]);
        Prestation::factory()->create(['nom' => 'Évacuation des déchets', 'unite' => 'forfait', 'prix_ht' => null]);
    }

    /**
     * Phrases dictées réelles → [client attendu, lignes attendues [désignation, quantité (millièmes), unité, prix (centimes)]].
     *
     * @return array<string, array{string, string, list<array{string, int, string, int}>}>
     */
    public static function phrasesValides(): array
    {
        return [
            'exemple de référence' => ['Mme Martin, démoussage 120 m² à 12 €, 3 faîtières à 150 €, évacuation forfait 150 €', 'Mme Sophie Martin', [
                ['Démoussage', 120000, 'm²', 1200], ['Faîtières', 3000, 'u', 15000], ['Évacuation', 1000, 'forfait', 15000],
            ]],
            'client collé à la prestation' => ['Mme Martin démoussage 120 m² à 12 €', 'Mme Sophie Martin', [['Démoussage', 120000, 'm²', 1200]]],
            'dictée en toutes lettres' => ['Madame Martin démoussage 120 mètres carrés à 12 euros et 3 faîtières à 150 euros', 'Mme Sophie Martin', [
                ['Démoussage', 120000, 'm²', 1200], ['Faîtières', 3000, 'u', 15000],
            ]],
            'x3 150 € vaut 3 × 150 €' => ['Bérard faîtières x3 150 €', 'Hélène Bérard', [['Faîtières', 3000, 'u', 15000]]],
            '3x devant' => ['Hélène Bérard, 3x tuiles à 35 €', 'Hélène Bérard', [['Tuiles', 3000, 'u', 3500]]],
            'x 12 avec espace' => ['Mme Martin, remplacement tuiles x 12 à 35 €', 'Mme Sophie Martin', [['Remplacement tuiles', 12000, 'u', 3500]]],
            'milliers avec espace' => ['Paul Durand, démoussage 1 200 m² à 9 €/m²', 'Paul Durand', [['Démoussage', 1200000, 'm²', 900]]],
            'prix avec milliers' => ['Mme Martin, réfection couverture forfait 1 500 €', 'Mme Sophie Martin', [['Réfection couverture', 1000, 'forfait', 150000]]],
            'mètre linéaire' => ['Martin. Gouttière 12 mètres linéaires à 8 € le mètre linéaire', 'Mme Sophie Martin', [['Gouttière', 12000, 'ml', 800]]],
            'de l heure' => ['Mme Martin - main d\'œuvre 4 h à 45 € de l\'heure', 'Mme Sophie Martin', [['Main d\'œuvre', 4000, 'h', 4500]]],
            'décimales' => ['Mme Martin, nettoyage gouttières 25,5 ml à 6,50 €', 'Mme Sophie Martin', [['Nettoyage gouttières', 25500, 'ml', 650]]],
            'retours à la ligne' => ["Mme Martin\nnettoyage gouttières 25 ml à 6 €\n- traitement 100 m2 à 9 €", 'Mme Sophie Martin', [
                ['Nettoyage gouttières', 25000, 'ml', 600], ['Traitement', 100000, 'm²', 900],
            ]],
            'devis pour' => ['Devis pour Mme Martin : traitement hydrofuge 120 m² à 9 €', 'Mme Sophie Martin', [['Traitement hydrofuge', 120000, 'm²', 900]]],
            'prénom et nom' => ['Sophie Martin ; recherche de fuite forfait 180 €', 'Mme Sophie Martin', [['Recherche de fuite', 1000, 'forfait', 18000]]],
            'nom puis prénom' => ['Martin Sophie, échafaudage forfait 600 €', 'Mme Sophie Martin', [['Échafaudage', 1000, 'forfait', 60000]]],
            'homonymes départagés par la ville' => ['Durand Lyon, démoussage 100 m² à 12 €', 'Paul Durand', [['Démoussage', 100000, 'm²', 1200]]],
            'société' => ['SCI Les Tilleuls, nettoyage toiture terrasse 310 m² à 4 €', 'SCI Les Tilleuls (Ville-Démo)', [['Nettoyage toiture terrasse', 310000, 'm²', 400]]],
            'nom du catalogue écrit en entier' => ['Mme Martin, démoussage de toiture 80 m2 à 12,50 €', 'Mme Sophie Martin', [['Démoussage de toiture', 80000, 'm²', 1250]]],
            'prix du catalogue repris' => ['Mme Martin, 3 faîtières scellées', 'Mme Sophie Martin', [['Faîtière scellée', 3000, 'u', 4500]]],
            'euros en lettres et HT' => ['Mme Martin, pose de 2 fenêtres de toit à 950 euros HT', 'Mme Sophie Martin', [['Pose de fenêtres de toit', 2000, 'u', 95000]]],
        ];
    }

    /**
     * @param  list<array{string, int, string, int}>  $attendu
     */
    #[DataProvider('phrasesValides')]
    public function test_phrases_comprises(string $phrase, string $client, array $attendu): void
    {
        $r = app(DevisExpress::class)->analyser($phrase);

        $this->assertSame([], $r['erreurs'], $phrase);
        $this->assertSame($client, $r['client']?->nomComplet());
        $this->assertSame(
            $attendu,
            array_map(fn ($l) => [$l['designation'], $l['quantite'], $l['unite'], $l['prix_unitaire_ht']], $r['lignes']),
            $phrase,
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phrasesBloquees(): array
    {
        return [
            'client introuvable' => ['Mme Dupont, démoussage 100 m² à 12 €', 'Client introuvable'],
            'client ambigu' => ['Durand, démoussage 100 m² à 12 €', 'Plusieurs clients correspondent'],
            'prix manquant' => ['Mme Martin, démoussage 120 m²', 'Prix manquant pour « Démoussage »'],
            'prix manquant même si la prestation proche a un prix' => ['Mme Martin, faîtières x3', 'Prix manquant'],
            'quantité manquante' => ['Mme Martin, faîtières à 150 €', 'Quantité manquante pour « Faîtières »'],
            'quantité illisible' => ['Mme Martin, pose velux 2 à 950 €', 'Quantité illisible'],
            'prestation du catalogue sans prix' => ['Mme Martin, évacuation des déchets forfait', 'Prix manquant'],
            'phrase vide' => ['   ', 'Écrivez ou dictez votre devis.'],
            'rien après le client' => ['Mme Martin', 'Aucune prestation trouvée'],
        ];
    }

    #[DataProvider('phrasesBloquees')]
    public function test_rien_n_est_devine(string $phrase, string $erreur): void
    {
        $r = app(DevisExpress::class)->analyser($phrase);

        $this->assertNotEmpty($r['erreurs'], $phrase);
        $this->assertStringContainsString($erreur, implode(' | ', $r['erreurs']));
    }

    public function test_prestation_proche_signalee_mais_pas_reprise(): void
    {
        $r = app(DevisExpress::class)->analyser('Mme Martin, démoussage 120 m² à 12 €');

        $this->assertNull($r['lignes'][0]['prestation_id']);
        $this->assertStringContainsString('prestation proche dans le catalogue, « Démoussage de toiture »', implode(' ', $r['alertes']));
    }

    public function test_ttc_signale(): void
    {
        $r = app(DevisExpress::class)->analyser('Mme Martin, démoussage 120 m² à 12 € TTC');

        $this->assertSame([], $r['erreurs']);
        $this->assertStringContainsString('« TTC »', implode(' ', $r['alertes']));
    }

    public function test_apercu_obligatoire_puis_creation_du_brouillon(): void
    {
        $user = User::factory()->create();
        $phrase = 'Mme Martin, démoussage 120 m² à 12 €, 3 faîtières à 150 €, évacuation forfait 150 €';

        $this->actingAs($user)->get('/devis/express')->assertOk()->assertSee('Votre devis en une phrase');

        $this->post('/devis/express/apercu', ['phrase' => $phrase])
            ->assertOk()
            ->assertSee('Mme Sophie Martin')
            ->assertSee('Créer le brouillon')
            ->assertSee("2\u{202F}040,00", false);
        $this->assertDatabaseCount('devis', 0);

        $this->post('/devis/express', ['phrase' => $phrase])->assertRedirect();

        $devis = Devis::firstOrFail();
        $this->assertSame(Devis::BROUILLON, $devis->statut);
        $this->assertNull($devis->numero);
        $this->assertSame(204000, $devis->total_ht);
        $this->assertSame(3, $devis->lignes()->count());
    }

    public function test_pas_de_creation_si_la_phrase_est_bloquee(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/devis/express/apercu', ['phrase' => 'Mme Dupont, démoussage 100 m² à 12 €'])
            ->assertSee('Impossible de créer le brouillon')
            ->assertDontSee('Créer le brouillon</button>', false);

        $this->post('/devis/express', ['phrase' => 'Mme Dupont, démoussage 100 m² à 12 €'])->assertRedirect('/devis/express');
        $this->assertDatabaseCount('devis', 0);
    }
}
