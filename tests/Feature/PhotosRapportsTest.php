<?php

namespace Tests\Feature;

use App\Mail\EmailDocument;
use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\LienClient;
use App\Models\Photo;
use App\Models\Rapport;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\PdfDevis;
use App\Services\PhotosAnnexe;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotosRapportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->gerant()->create();
        $this->client = Client::factory()->create(['email' => 'client@exemple.test']);
    }

    private function jpeg(int $largeur = 3000, int $hauteur = 2000): UploadedFile
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
        ob_start();
        imagejpeg($image);
        $contenu = ob_get_clean();

        return UploadedFile::fake()->createWithContent('IMG_0001.jpg', $contenu);
    }

    public function test_ajout_de_photos_reduites(): void
    {
        $chantier = Chantier::create(['client_id' => $this->client->id, 'adresse' => '1 rue', 'ville' => 'Ville-Test']);

        $this->actingAs($this->user)->post(route('photos.store', $this->client), [
            'photos' => [$this->jpeg(), $this->jpeg(800, 1200)], 'moment' => 'avant', 'chantier_id' => $chantier->id, 'legende' => 'Faîtage',
        ])->assertRedirect(route('photos.index', $this->client));

        $this->assertSame(2, Photo::count());
        $grande = Photo::first();
        $this->assertSame([1600, 1067], [$grande->largeur, $grande->hauteur]);
        $this->assertSame($chantier->id, $grande->chantier_id);
        Storage::disk('local')->assertExists([$grande->chemin, $grande->miniature]);
        $this->assertSame([400, 267], array_slice(getimagesize($grande->cheminComplet(true)), 0, 2));

        $this->get(route('photos.index', $this->client))->assertOk()->assertSee('Avant (2)')->assertSee('Faîtage');
        $this->get(route('photos.show', $grande))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('clients.show', $this->client))->assertSee('Photos du chantier (2)');

        $this->put(route('photos.update', $grande), ['moment' => 'apres', 'legende' => 'Fini', 'dans_documents' => '1']);
        $this->assertTrue($grande->fresh()->dans_documents);
        $this->assertSame('apres', $grande->fresh()->moment);

        $this->delete(route('photos.destroy', $grande))->assertRedirect(route('photos.index', $this->client));
        Storage::disk('local')->assertMissing($grande->chemin);
    }

    public function test_dessiner_sur_une_photo_et_remettre_l_originale(): void
    {
        $this->actingAs($this->user)->post(route('photos.store', $this->client), ['photos' => [$this->jpeg(400, 300)], 'moment' => 'probleme']);
        $photo = Photo::firstOrFail();
        $this->assertSame('Problème', $photo->libelleMoment());
        $premier = $photo->chemin;

        $this->get(route('photos.annotation', $photo))->assertOk()->assertSee('toile-annotation');
        $this->post(route('photos.annoter', $photo), ['image' => $this->jpeg(400, 300)])->assertRedirect();
        $photo->refresh();
        $this->assertSame($premier, $photo->chemin_original);
        $this->assertNotSame($premier, $photo->chemin);
        Storage::disk('local')->assertExists([$photo->chemin_original, $photo->chemin]);

        // Deuxième dessin : l'originale reste la même.
        $this->post(route('photos.annoter', $photo), ['image' => $this->jpeg(400, 300)]);
        $this->assertSame($premier, $photo->fresh()->chemin_original);

        $this->post(route('photos.retablir', $photo));
        $photo->refresh();
        $this->assertNull($photo->chemin_original);
        Storage::disk('local')->assertMissing($premier);
        Storage::disk('local')->assertExists($photo->chemin);
        $this->assertCount(2, Storage::disk('local')->files('photos/'.$this->client->id));
    }

    public function test_photos_refusees(): void
    {
        $this->actingAs($this->user)->post(route('photos.store', $this->client), [
            'photos' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')], 'moment' => 'avant',
        ])->assertSessionHasErrors('photos.0');
        $this->post(route('photos.store', $this->client), ['moment' => 'demain'])->assertSessionHasErrors(['photos', 'moment']);
        $this->assertSame(0, Photo::count());
    }

    public function test_photos_depuis_le_planning(): void
    {
        $rdv = RendezVous::create(['titre' => 'Réparation', 'debut' => now(), 'fin' => now()->addHour(), 'client_id' => $this->client->id, 'fait' => true]);

        $this->actingAs($this->user)->get(route('planning.show', $rdv))->assertOk()->assertSee('Ajouter des photos')->assertSee('Faire le rapport');
        $this->post(route('photos.store', $this->client), ['photos' => [$this->jpeg(200, 100)], 'moment' => 'apres', 'rendez_vous_id' => $rdv->id, 'retour' => 'planning'])
            ->assertRedirect(route('planning.show', $rdv));
        $this->assertSame($rdv->id, Photo::first()->rendez_vous_id);

        // Le rapport reprend les photos du passage.
        $this->get(route('rapports.create', ['rdv' => $rdv->id]))->assertOk()->assertSee('value="Réparation"', false)->assertSee('checked', false);
    }

    public function test_rapport_envoye_et_vu_par_le_client(): void
    {
        Mail::fake();
        app(Reglages::class)->set('identite.nom_commercial', 'Entreprise Fictive');
        $this->actingAs($this->user)->post(route('photos.store', $this->client), ['photos' => [$this->jpeg(300, 200)], 'moment' => 'apres']);
        $photo = Photo::first();
        $autre = Photo::create(['client_id' => Client::factory()->create()->id, 'chemin' => 'x.jpg', 'miniature' => 'x-mini.jpg', 'largeur' => 1, 'hauteur' => 1, 'taille' => 1]);

        // Une photo d'un autre client est refusée.
        $this->post(route('rapports.store'), ['client_id' => $this->client->id, 'titre' => 'X', 'date_intervention' => '2026-10-01', 'photos' => [$autre->id]])
            ->assertSessionHasErrors('photos.0');

        $this->post(route('rapports.store'), [
            'client_id' => $this->client->id, 'titre' => 'Recherche de fuite', 'date_intervention' => '2026-10-01',
            'travaux' => 'Tuiles remplacées', 'constats' => 'Gel', 'photos' => [$photo->id],
        ]);
        $rapport = Rapport::firstOrFail();
        $this->get(route('rapports.show', $rapport))->assertOk()->assertSee('Tuiles remplacées');

        $pdf = $this->get(route('rapports.pdf', $rapport))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('envoi.create', ['rapport', $rapport->id]))->assertOk()->assertSee('Rapport d\'intervention');
        $this->post(route('envoi.store', ['rapport', $rapport->id]), [
            'destinataire' => 'client@exemple.test', 'modele' => 'rapport', 'sujet' => 'Votre rapport', 'corps' => 'Voici : {lien}', 'joindre_pdf' => '1',
        ])->assertRedirect(route('rapports.show', $rapport));
        Mail::assertSent(EmailDocument::class, fn (EmailDocument $m) => $m->nomPdf === 'rapport-intervention-2026-10-01.pdf');
        $this->assertNotNull($rapport->fresh()->envoye_at);

        // Le client voit le rapport et ses photos, pas les autres.
        $jeton = LienClient::pour($rapport)->jeton();
        auth()->logout();
        $this->get('/c/'.$jeton)->assertOk()->assertSee('Recherche de fuite')->assertSee('Gel');
        $this->get('/c/'.$jeton.'/photo/'.$photo->id)->assertOk();
        $this->get('/c/'.$jeton.'/photo/'.$autre->id)->assertNotFound();
        $this->get('/c/'.$jeton.'/pdf')->assertOk();
    }

    public function test_photos_en_annexe_du_devis(): void
    {
        $this->actingAs($this->user)->post(route('photos.store', $this->client), ['photos' => [$this->jpeg(300, 200), $this->jpeg(300, 200)], 'moment' => 'avant']);
        $devis = Devis::create(['client_id' => $this->client->id, 'objet' => 'Toiture', 'created_by' => $this->user->id]);

        $this->assertSame([], PhotosAnnexe::pour($devis), 'Rien sans la case « Mettre dans les devis ».');

        Photo::query()->update(['dans_documents' => true]);
        $pages = PhotosAnnexe::pour($devis);
        $this->assertCount(1, $pages);
        $this->assertStringContainsString('Annexe : photos', $pages[0]);
        $this->assertStringStartsWith('%PDF', app(PdfDevis::class)->generer($devis));
    }

    public function test_rapport_a_la_corbeille(): void
    {
        $rapport = Rapport::create(['client_id' => $this->client->id, 'titre' => 'Visite', 'date_intervention' => '2026-10-01']);
        $this->actingAs($this->user)->delete(route('rapports.destroy', $rapport));
        $this->assertSoftDeleted($rapport);
        $this->get(route('corbeille'))->assertSee('Visite (01/10/2026)');
    }
}
