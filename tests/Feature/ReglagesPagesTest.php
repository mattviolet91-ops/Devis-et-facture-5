<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\TvaIntracom;
use App\Support\Reglages;
use App\Support\SectionsReglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\ControlesTest;

class ReglagesPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function entrepriseValide(array $modifs = []): array
    {
        return array_merge([
            'identite__nom_commercial' => 'Entreprise Fictive',
            'identite__forme_juridique' => 'EURL',
            'identite__adresse' => '1 rue de l\'Exemple',
            'identite__code_postal' => '00000',
            'identite__ville' => 'Ville-Test',
            'identite__telephone' => '01 00 00 00 00',
            'identite__email' => 'contact@fictive.test',
            'identite__siret' => ControlesTest::siretFictif(),
            'identite__code_ape' => '43.91b',
        ], $modifs);
    }

    public function test_toutes_les_rubriques_s_ouvrent(): void
    {
        $this->actingAs($this->gerant)->get('/reglages')->assertOk()->assertSee('Entreprise')->assertSee('Textes types');

        foreach (array_keys(SectionsReglages::toutes()) as $section) {
            $this->get("/reglages/{$section}")->assertOk()->assertSee('Enregistrer');
        }

        $this->get('/reglages/inconnue')->assertNotFound();
    }

    public function test_le_commercial_n_a_pas_acces_aux_reglages(): void
    {
        $commercial = User::factory()->create();

        $this->actingAs($commercial)->get('/reglages')->assertForbidden();
        $this->get('/reglages/entreprise')->assertForbidden();
        $this->put('/reglages/entreprise', $this->entrepriseValide())->assertForbidden();
        $this->assertSame('', reglage('identite.nom_commercial'));
    }

    public function test_enregistrer_l_entreprise(): void
    {
        $siret = ControlesTest::siretFictif();

        $this->actingAs($this->gerant)
            ->put('/reglages/entreprise', $this->entrepriseValide(['identite__siret' => chunk_split($siret, 3, ' ')]))
            ->assertRedirect('/reglages/entreprise')
            ->assertSessionHas('statut', 'Réglages enregistrés.');

        $this->assertSame('Entreprise Fictive', reglage('identite.nom_commercial'));
        $this->assertSame($siret, reglage('identite.siret'));
        $this->assertSame('4391B', reglage('identite.code_ape'));
        $this->assertDatabaseHas('activites', ['action' => 'reglages.modification']);

        $this->get('/reglages/entreprise')->assertSee('value="Entreprise Fictive"', false);
    }

    public function test_champs_obligatoires_signales(): void
    {
        $this->actingAs($this->gerant)->get('/reglages/entreprise')->assertSee('(obligatoire)');

        $this->put('/reglages/entreprise', [])
            ->assertSessionHasErrors([
                'identite__nom_commercial' => 'Remplissez « Nom commercial ».',
                'identite__siret' => 'Remplissez « SIRET ».',
            ]);
    }

    public function test_siret_et_code_postal_controles(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/entreprise', $this->entrepriseValide(['identite__siret' => '12345678901234', 'identite__code_postal' => '7500']))
            ->assertSessionHasErrors(['identite__code_postal' => 'Le code postal fait 5 chiffres.']);

        $this->assertStringContainsString('un chiffre est sans doute mal tapé', session('errors')->first('identite__siret'));

        $this->put('/reglages/entreprise', $this->entrepriseValide(['identite__siret' => '123']))
            ->assertSessionHasErrors(['identite__siret' => 'Le SIRET doit faire 14 chiffres (vous en avez saisi 3).']);
    }

    public function test_tva_assujetti_exige_le_numero_intracommunautaire(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/tva', ['tva__regime' => 'assujetti', 'tva__taux' => '20 ; 10 ; 5,5', 'tva__taux_defaut' => '1000', 'tva__unites' => "m²\nforfait"])
            ->assertSessionHasErrors(['identite__tva_intracom' => 'Assujetti à la TVA : le numéro de TVA intracommunautaire est obligatoire.']);
    }

    public function test_tva_assujetti_valide(): void
    {
        $siret = ControlesTest::siretFictif();
        app(Reglages::class)->set('identite.siret', $siret);
        $siren = substr($siret, 0, 9);
        $tva = 'FR '.TvaIntracom::cle($siren).' '.$siren;

        $this->actingAs($this->gerant)
            ->put('/reglages/tva', ['tva__regime' => 'assujetti', 'identite__tva_intracom' => $tva, 'tva__taux' => '20 ; 10 ; 5,5 ; 0', 'tva__taux_defaut' => '550', 'tva__unites' => "m²\nml\nm²\n\nforfait"])
            ->assertSessionHasNoErrors();

        $this->assertSame([2000, 1000, 550, 0], reglage('tva.taux'));
        $this->assertSame(550, reglage('tva.taux_defaut'));
        $this->assertSame(['m²', 'ml', 'forfait'], reglage('tva.unites'));
        $this->assertSame(str_replace(' ', '', $tva), reglage('identite.tva_intracom'));
    }

    public function test_franchise_en_base(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/tva', ['tva__regime' => 'franchise', 'tva__taux' => '20', 'tva__taux_defaut' => '2000', 'tva__unites' => 'm²'])
            ->assertSessionHasNoErrors();

        $this->assertSame('franchise', reglage('tva.regime'));
        $this->assertSame(0, reglage('tva.taux_defaut'));
        $this->get('/reglages/tva')->assertSee('art. 293 B du CGI');
    }

    public function test_taux_illisible_ou_taux_par_defaut_absent(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/tva', ['tva__regime' => 'franchise', 'tva__taux' => '20 ; vingt', 'tva__taux_defaut' => '0', 'tva__unites' => 'm²'])
            ->assertSessionHasErrors('tva__taux');

        $siret = ControlesTest::siretFictif();
        app(Reglages::class)->set('identite.siret', $siret);
        $siren = substr($siret, 0, 9);

        $this->put('/reglages/tva', ['tva__regime' => 'assujetti', 'identite__tva_intracom' => 'FR'.TvaIntracom::cle($siren).$siren, 'tva__taux' => '20 ; 10', 'tva__taux_defaut' => '550', 'tva__unites' => 'm²'])
            ->assertSessionHasErrors(['tva__taux_defaut' => 'Le taux par défaut doit faire partie des taux utilisés.']);
    }

    public function test_documents_iban_bic_et_pourcentages(): void
    {
        $base = ['documents__validite_devis_jours' => '30', 'documents__delai_paiement_jours' => '30', 'documents__acompte_pourcentage' => '30'];

        $this->actingAs($this->gerant)
            ->put('/reglages/documents', $base + ['documents__iban' => 'FR76 3000 6000 0112 3456 7890 188'])
            ->assertSessionHasErrors(['documents__bic' => 'Avec un IBAN, indiquez aussi le BIC.']);
        $this->assertStringContainsString('IBAN n\'est pas valable', session('errors')->first('documents__iban'));

        $this->put('/reglages/documents', array_merge($base, ['documents__delai_paiement_jours' => '90', 'documents__acompte_pourcentage' => '120']))
            ->assertSessionHasErrors(['documents__delai_paiement_jours', 'documents__acompte_pourcentage']);

        $this->put('/reglages/documents', $base + ['documents__iban' => 'fr76 3000 6000 0112 3456 7890 189', 'documents__bic' => 'agrifrpp', 'documents__page_couverture' => '1', 'documents__cgv' => 'Article 1 — Objet.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('FR7630006000011234567890189', reglage('documents.iban'));
        $this->assertSame('AGRIFRPP', reglage('documents.bic'));
        $this->assertSame(30, reglage('documents.acompte_pourcentage'));
        $this->assertTrue(reglage('documents.page_couverture'));
        $this->get('/reglages/documents')->assertSee('FR76 3000 6000 0112 3456 7890 189');
    }

    public function test_dates_de_l_assurance(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/assurance', ['assurance__date_debut' => '2026-01-01', 'assurance__date_fin' => '2025-12-31', 'assurance__alerte_jours' => '30'])
            ->assertSessionHasErrors(['assurance__date_fin' => 'La date de fin doit être après la date de début.']);

        $this->put('/reglages/assurance', ['assurance__assureur' => 'Assureur Fictif', 'assurance__date_debut' => '2026-01-01', 'assurance__date_fin' => '2026-12-31', 'assurance__alerte_jours' => '45'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-12-31', reglage('assurance.date_fin'));
        $this->assertSame(45, reglage('assurance.alerte_jours'));
    }

    public function test_modeles_d_emails(): void
    {
        $this->actingAs($this->gerant)->get('/reglages/modeles')->assertSee('Votre devis {numero}');

        $donnees = [];
        foreach (array_keys(config('reglages.emails.modeles')) as $modele) {
            $donnees["emails__modeles__{$modele}__sujet"] = "Sujet {$modele}";
            $donnees["emails__modeles__{$modele}__corps"] = "{salutation}, corps {$modele}";
        }

        $this->put('/reglages/modeles', $donnees)->assertSessionHasNoErrors();
        $this->assertSame('Sujet relance', reglage('emails.modeles.relance.sujet'));
    }

    public function test_apparence_couleurs_et_theme(): void
    {
        $this->actingAs($this->gerant)
            ->put('/reglages/apparence', ['apparence__couleur_principale' => '#FFD700', 'apparence__couleur_accent' => '#2a9d8f', 'apparence__police' => 'classique'])
            ->assertSessionHasNoErrors();

        $this->assertSame('#ffd700', reglage('apparence.couleur_principale'));

        $css = $this->get('/theme.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->getContent();
        $this->assertStringContainsString('Georgia', $css);
        // Les boutons gardent la couleur choisie, avec un texte lisible dessus.
        $this->assertStringContainsString('--couleur-accent: #2a9d8f;', $css);
        $this->assertStringContainsString('--couleur-accent-texte:', $css);
        $this->assertStringContainsString('prefers-color-scheme: dark', $css);
        // Le jaune est trop clair pour du texte sur fond clair : une version plus foncée est utilisée.
        $this->assertStringNotContainsString('--couleur-principale: #ffd700', strtok($css, "\n"));

        $this->put('/reglages/apparence', ['apparence__couleur_principale' => '#494949', 'apparence__couleur_accent' => '#3cbde8', 'apparence__police' => 'moderne'])
            ->assertSessionHasNoErrors();
        $this->assertStringContainsString('Montserrat', $this->get('/theme.css')->getContent());

        $this->put('/reglages/apparence', ['apparence__couleur_principale' => 'rouge', 'apparence__couleur_accent' => '#2a9d8f', 'apparence__police' => 'systeme'])
            ->assertSessionHasErrors(['apparence__couleur_principale' => 'Choisissez une couleur.']);
    }
}
