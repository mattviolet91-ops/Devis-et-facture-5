<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\User;
use App\Services\GestionDevis;
use App\Services\Pdf;
use App\Services\PdfDevis;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfDevisTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $r = app(Reglages::class);
        foreach ([
            'identite.nom_commercial' => 'Entreprise Fictive', 'identite.siret' => '12345678901237', 'identite.forme_juridique' => 'EURL',
            'identite.adresse' => '1 rue de l\'Exemple', 'identite.code_postal' => '00000', 'identite.ville' => 'Ville-Test',
            'assurance.assureur' => 'Assureur Fictif', 'assurance.numero_contrat' => 'X-1', 'assurance.zone' => 'France métropolitaine',
            'identite.mediateur_nom' => 'Médiateur Fictif', 'documents.iban' => 'FR7630006000011234567890189', 'documents.bic' => 'AGRIFRPP',
            'tva.regime' => 'assujetti', 'identite.tva_intracom' => 'FR00123456789',
        ] as $cle => $valeur) {
            $r->set($cle, $valeur);
        }
        $this->user = User::factory()->create();
    }

    private function devis(array $client = [], array $attributs = []): Devis
    {
        $gestion = app(GestionDevis::class);
        $devis = $gestion->creer(Client::factory()->create($client), null, 'Toiture', $this->user->id);
        $devis->update($attributs);
        $gestion->remplacerLignes($devis, [
            ['type' => 'ligne', 'designation' => 'Démoussage', 'quantite' => 120000, 'unite' => 'm²', 'prix_unitaire_ht' => 1200, 'taux_tva' => 1000],
            ['type' => 'ligne', 'designation' => 'Gouttière', 'quantite' => 1000, 'unite' => 'u', 'prix_unitaire_ht' => 5000, 'taux_tva' => 2000],
        ]);

        return $devis->fresh();
    }

    private function nombreDePages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_mentions_obligatoires_pour_un_particulier(): void
    {
        $html = app(PdfDevis::class)->html($this->devis());

        foreach ([
            'Entreprise Fictive', 'SIRET 12345678901237', 'EURL', 'N° TVA intracommunautaire : FR00123456789',
            'Adresse des travaux', "Valable jusqu'au", 'Prix unit. HT', 'Total HT', 'TVA 10 %', 'TVA 20 %', 'Total TTC',
            'Gestion des déchets', 'Conditions de paiement', 'IBAN FR76 3000 6000 0112 3456 7890 189',
            'Assurance décennale', 'Assureur Fictif, contrat n° X-1', 'couverture géographique : France métropolitaine',
            'Médiation de la consommation', 'Médiateur Fictif', 'Bon pour accord', 'Devis gratuit',
        ] as $mention) {
            $this->assertStringContainsString($mention, $html, $mention);
        }
        // Indemnité de 40 € : seulement pour les professionnels.
        $this->assertStringNotContainsString('40 €', $html);
    }

    public function test_mentions_pour_un_professionnel(): void
    {
        $html = app(PdfDevis::class)->html($this->devis(['type' => 'professionnel', 'raison_sociale' => 'Société Fictive']));

        $this->assertStringContainsString('indemnité forfaitaire pour frais de recouvrement de 40 €', $html);
        $this->assertStringNotContainsString('Médiation de la consommation', $html);
    }

    public function test_franchise_de_tva(): void
    {
        app(Reglages::class)->set('tva.regime', 'franchise');
        $devis = $this->devis();
        $devis->recalculer();
        $html = app(PdfDevis::class)->html($devis->fresh());

        $this->assertStringContainsString('TVA non applicable, art. 293 B du CGI', $html);
        $this->assertStringNotContainsString('TVA intracommunautaire', $html);
    }

    public function test_retractation_hors_etablissement(): void
    {
        $pdf = app(PdfDevis::class);
        $devis = $this->devis([], ['hors_etablissement' => true, 'urgence' => true]);

        $html = $pdf->html($devis);
        $this->assertStringContainsString("vous disposez d'un délai de 14 jours", $html);
        $this->assertStringContainsString('Aucun paiement ne peut être exigé avant 7 jours', $html);
        $this->assertStringContainsString('Travaux de réparation urgents', $html);
        $this->assertTrue($pdf->formulaireRetractation($devis));

        // Formulaire de rétractation en annexe : une page de plus.
        $sans = $this->devis();
        $this->assertSame($this->nombreDePages($pdf->generer($sans)) + 1, $this->nombreDePages($pdf->generer($devis)));
    }

    public function test_annexes_cgv_couverture_et_attestation(): void
    {
        $pdf = app(PdfDevis::class);
        $devis = $this->devis();
        $base = $this->nombreDePages($pdf->generer($devis));

        app(Reglages::class)->set('documents.cgv', 'Article 1 — Objet.');
        app(Reglages::class)->set('documents.page_couverture', true);

        // Attestation d'assurance (un PDF d'une page).
        $attestation = app(Pdf::class)->nouveau('Attestation');
        $attestation->WriteHTML('<p>Attestation fictive</p>');
        Storage::disk('local')->put('reglages/assurance-attestation.pdf', $attestation->Output('', 'S'));
        app(Reglages::class)->set('assurance.attestation', 'reglages/assurance-attestation.pdf');

        $this->assertSame($base + 3, $this->nombreDePages($pdf->generer($devis)));
    }

    public function test_pdf_d_un_brouillon_et_pdf_fige_a_l_envoi(): void
    {
        $devis = $this->devis();
        $this->actingAs($this->user);

        $brouillon = $this->get(route('devis.pdf', $devis))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $brouillon->getContent());
        $this->assertNull($devis->fresh()->pdf_chemin);

        $this->post(route('devis.envoyer', $devis));
        $devis->refresh();

        Storage::disk('local')->assertExists($devis->pdf_chemin);
        $fichier = Storage::disk('local')->get($devis->pdf_chemin);
        $this->assertSame(hash('sha256', $fichier), $devis->pdf_sha256);

        // Le PDF envoyé ne change plus, même si les réglages changent ensuite.
        app(Reglages::class)->set('identite.nom_commercial', 'Autre Nom');
        $servi = $this->get(route('devis.pdf', $devis))->getContent();
        $this->assertSame($devis->pdf_sha256, hash('sha256', $servi));

        $this->get(route('devis.show', $devis))->assertOk()->assertSee($devis->pdf_sha256)->assertSee('Voir le PDF');
    }

    public function test_visionneuse_seulement_pour_les_documents_de_l_application(): void
    {
        $devis = $this->devis();
        $this->actingAs($this->user);

        $this->get('/visionneuse?f=/devis/'.$devis->id.'/pdf')->assertOk()
            ->assertSee('✕ Fermer')->assertSee('Partager')
            ->assertSee('vendor/pdfjs/pdf.worker.min.mjs');

        foreach (['https://exemple.test/x.pdf', '//exemple.test/x.pdf', '/devis/1/pdf/../../etc', '/clients/1', 'javascript:alert(1)'] as $interdit) {
            $this->get('/visionneuse?f='.urlencode($interdit))->assertNotFound();
        }

        auth()->logout();
        $this->get('/visionneuse?f=/devis/'.$devis->id.'/pdf')->assertRedirect('/connexion');
        $this->get(route('devis.pdf', $devis))->assertRedirect('/connexion');
    }

    public function test_pdf_js_present_et_sans_appel_exterieur(): void
    {
        $this->assertFileExists(public_path('vendor/pdfjs/pdf.min.mjs'));
        $this->assertFileExists(public_path('vendor/pdfjs/pdf.worker.min.mjs'));
        $this->assertStringNotContainsString('https://', file_get_contents(public_path('js/visionneuse.js')));
    }
}
