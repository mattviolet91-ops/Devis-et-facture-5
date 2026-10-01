<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportClientsTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contenu, string $nom = 'clients.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    public function test_apercu_puis_import(): void
    {
        $user = User::factory()->create();
        Client::factory()->create(['telephone' => '0600000003', 'nom' => 'Existant']);

        $contenu = "\u{FEFF}Civilité;Nom;Prénom;Téléphone;E-mail;Adresse;CP;Ville;Société\n"
            ."Mme;Martin;Sophie;06 00 00 00 01;sophie@exemple.test;12 rue des Lilas;1000;Ville-Test;\n"
            ."M.;Durand;Paul;;paul@exemple.test;;;Lyon;\n"
            ."M;Doublon;Fichier;06.00.00.00.01;;;;;\n"
            ."Mme;Déjà;Là;+33600000003;;;;;\n"
            .";;;0600000005;;;;;\n"
            ."M.;Moreau;Paul;0600000006;contact@fictive.test;;;;SCI Fictive\n"
            ."Mme;SansContact;Anne;;;;;;\n"
            .";;;;;;;;\n";

        $this->actingAs($user)->get('/clients/import')->assertOk()->assertSee("Voir l'aperçu", false);
        $reponse = $this->post('/clients/import', ['fichier' => $this->csv($contenu)]);
        $reponse->assertRedirect();
        $apercu = $reponse->headers->get('Location');

        // Rien n'est encore importé.
        $this->assertDatabaseCount('clients', 1);

        $this->get($apercu)->assertOk()
            ->assertSee('<strong>3</strong> client(s) à importer', false)
            ->assertSee('<strong>2</strong> déjà présent(s)', false)
            ->assertSee('<strong>2</strong> ligne(s) incomplète(s)', false)
            ->assertSee('Déjà présent plus haut dans le fichier')
            ->assertSee('Déjà dans l&#039;application : Mme Camille Existant', false)
            ->assertSee('Nom manquant')
            ->assertSee('Ni téléphone ni email');

        $this->post($apercu)->assertRedirect('/clients')->assertSessionHas('statut', '3 client(s) importé(s). 4 ligne(s) ignorée(s).');

        $this->assertDatabaseCount('clients', 4);
        $martin = Client::where('nom', 'Martin')->firstOrFail();
        $this->assertSame('01000', $martin->code_postal);
        $this->assertSame('Mme', $martin->civilite);
        $this->assertSame('0600000001', $martin->telephone);
        $this->assertTrue(Client::where('raison_sociale', 'SCI Fictive')->firstOrFail()->estProfessionnel());
        $this->assertDatabaseHas('activites', ['action' => 'client.import']);

        // L'aperçu ne sert qu'une fois.
        $this->post($apercu)->assertRedirect('/clients/import');
        $this->assertDatabaseCount('clients', 4);
    }

    public function test_importer_aussi_les_doublons(): void
    {
        Client::factory()->create(['telephone' => '0600000003']);

        $reponse = $this->actingAs(User::factory()->create())->post('/clients/import', [
            'fichier' => $this->csv("nom,telephone\nAutre Personne,0600000003\n"),
        ]);

        $this->post($reponse->headers->get('Location'), ['avec_doublons' => '1'])->assertSessionHas('statut', '1 client(s) importé(s). 0 ligne(s) ignorée(s).');
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_fichier_excel_en_windows_1252_avec_virgules(): void
    {
        $contenu = mb_convert_encoding("Nom,Prénom,Ville,Email\nBérard,Hélène,Saint-Étienne,helene@exemple.test\n", 'Windows-1252', 'UTF-8');

        $reponse = $this->actingAs(User::factory()->create())->post('/clients/import', ['fichier' => $this->csv($contenu)]);
        $this->post($reponse->headers->get('Location'));

        $client = Client::firstOrFail();
        $this->assertSame('Bérard', $client->nom);
        $this->assertSame('Hélène', $client->prenom);
        $this->assertSame('Saint-Étienne', $client->ville);
    }

    public function test_fichier_sans_colonne_nom(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/clients/import', ['fichier' => $this->csv("a;b;c\n1;2;3\n")])
            ->assertSessionHasErrors('fichier');
    }

    public function test_mauvais_type_de_fichier(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/clients/import', ['fichier' => UploadedFile::fake()->image('photo.jpg')])
            ->assertSessionHasErrors(['fichier' => 'Le fichier doit être au format CSV (dans Excel : Enregistrer sous → CSV).']);
    }

    public function test_un_autre_compte_ne_peut_pas_utiliser_l_apercu(): void
    {
        $reponse = $this->actingAs(User::factory()->create())->post('/clients/import', ['fichier' => $this->csv("nom;email\nA;a@exemple.test\n")]);
        $apercu = $reponse->headers->get('Location');

        $this->actingAs(User::factory()->create())->post($apercu)->assertRedirect('/clients/import');
        $this->assertDatabaseCount('clients', 0);
    }
}
