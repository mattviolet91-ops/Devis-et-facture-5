<?php

namespace Tests\Feature;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\User;
use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChantiersNotesFichiersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Reglages::class)->set('entreprise.metier', 'couvreur');
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create(['adresse' => '12 rue des Lilas', 'code_postal' => '00100', 'ville' => 'Ville-Test']);
    }

    public function test_plusieurs_adresses_de_chantier(): void
    {
        $this->actingAs($this->user);

        // La première adresse reprend celle du client.
        $this->get(route('chantiers.create', $this->client))->assertOk()->assertSee('value="12 rue des Lilas"', false)->assertSee('Type de toiture');

        $this->post(route('chantiers.store', $this->client), [
            'libelle' => 'Maison', 'adresse' => '12 rue des Lilas', 'code_postal' => '00100', 'ville' => 'Ville-Test',
            'type_toiture' => 'Ardoises naturelles', 'surface' => '95,5', 'pente' => '45', 'acces' => 'Nacelle nécessaire',
        ])->assertRedirect(route('clients.show', $this->client));

        $this->post(route('chantiers.store', $this->client), ['libelle' => 'Grange', 'adresse' => '5 chemin', 'ville' => 'Ville-Test']);

        $this->assertSame(2, $this->client->chantiers()->count());
        $chantier = $this->client->chantiers()->first();
        $this->assertSame('95.50', $chantier->surface);
        $this->assertSame(45, $chantier->pente);

        $this->get(route('clients.show', $this->client))
            ->assertSee('Maison')
            ->assertSee('Ardoises naturelles · 95,5 m² · pente 45°')
            ->assertSee('Nacelle nécessaire')
            ->assertSee('Itinéraire')
            ->assertSee('Grange');
    }

    public function test_controles_du_chantier(): void
    {
        $this->actingAs($this->user)->post(route('chantiers.store', $this->client), ['pente' => '120', 'surface' => 'beaucoup', 'type_toiture' => 'Paille'])
            ->assertSessionHasErrors(['adresse', 'ville', 'pente' => 'La pente se donne en degrés (entre 0 et 90°).', 'surface', 'type_toiture']);
    }

    public function test_modifier_et_supprimer_un_chantier(): void
    {
        $chantier = $this->client->chantiers()->create(['adresse' => '1 rue', 'ville' => 'Ville']);
        $this->actingAs($this->user);

        $this->put(route('chantiers.update', $chantier), ['adresse' => '2 rue', 'ville' => 'Ville', 'surface' => '10'])->assertRedirect(route('clients.show', $this->client));
        $this->assertSame('2 rue', $chantier->fresh()->adresse);

        $this->delete(route('chantiers.destroy', $chantier))->assertRedirect(route('clients.show', $this->client));
        $this->assertSoftDeleted($chantier);
    }

    public function test_champs_toiture_caches_pour_un_autre_metier(): void
    {
        app(Reglages::class)->set('entreprise.metier', 'plombier');
        app(Reglages::class)->set('modules.actifs', ['photos']);

        $this->actingAs($this->user)->get(route('chantiers.create', $this->client))->assertOk()->assertDontSee('Type de toiture');
    }

    public function test_notes(): void
    {
        $this->actingAs($this->user)->post(route('notes.store', $this->client), ['texte' => 'Rappeler après 18 h.'])
            ->assertRedirect(route('clients.show', $this->client).'#notes');
        $this->post(route('notes.store', $this->client), ['texte' => ''])->assertSessionHasErrors(['texte' => 'Remplissez « note ».']);

        $note = $this->client->notes()->first();
        $this->get(route('clients.show', $this->client))->assertSee('Rappeler après 18 h.');

        // Un autre commercial ne peut pas supprimer la note ; le gérant oui.
        $this->actingAs(User::factory()->create())->delete(route('notes.destroy', $note))->assertForbidden();
        $this->actingAs(User::factory()->gerant()->create())->delete(route('notes.destroy', $note))->assertRedirect();
        $this->assertDatabaseCount('notes_clients', 0);
    }

    public function test_pieces_jointes(): void
    {
        $this->actingAs($this->user)->post(route('pieces.store', $this->client), [
            'fichier' => UploadedFile::fake()->create('devis-ancien.pdf', 300, 'application/pdf'),
        ])->assertRedirect(route('clients.show', $this->client).'#pieces');

        $piece = $this->client->piecesJointes()->firstOrFail();
        Storage::disk('local')->assertExists($piece->chemin);
        $this->assertStringStartsWith('clients/'.$this->client->id.'/', $piece->chemin);

        $this->get(route('clients.show', $this->client))->assertSee('devis-ancien.pdf')->assertSee('300 Ko');
        $this->get(route('pieces.show', $piece))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Visiteur non connecté : pas d'accès.
        auth()->logout();
        $this->get(route('pieces.show', $piece))->assertRedirect('/connexion');

        $this->actingAs($this->user)->delete(route('pieces.destroy', $piece))->assertRedirect();
        Storage::disk('local')->assertMissing($piece->chemin);
    }

    public function test_piece_jointe_d_un_chantier(): void
    {
        $chantier = $this->client->chantiers()->create(['adresse' => '1 rue', 'ville' => 'Ville']);
        $autre = Client::factory()->create()->chantiers()->create(['adresse' => '2 rue', 'ville' => 'Ville']);

        $this->actingAs($this->user)->post(route('pieces.store', $this->client), [
            'fichier' => UploadedFile::fake()->image('toit.jpg'), 'chantier_id' => $chantier->id,
        ])->assertRedirect();
        $this->assertSame(1, $chantier->piecesJointes()->count());

        // Impossible de rattacher un fichier au chantier d'un autre client.
        $this->post(route('pieces.store', $this->client), [
            'fichier' => UploadedFile::fake()->image('toit.jpg'), 'chantier_id' => $autre->id,
        ])->assertNotFound();
    }

    public function test_fichiers_refuses(): void
    {
        $this->actingAs($this->user);

        $this->post(route('pieces.store', $this->client), ['fichier' => UploadedFile::fake()->create('page.html', 5, 'text/html')])
            ->assertSessionHasErrors(['fichier' => 'Ce type de fichier n\'est pas accepté (PDF, photo, Word, Excel ou texte).']);
        $this->post(route('pieces.store', $this->client), ['fichier' => UploadedFile::fake()->create('gros.pdf', 11000, 'application/pdf')])
            ->assertSessionHasErrors(['fichier' => 'Le fichier ne doit pas dépasser 10 Mo.']);

        $this->assertDatabaseCount('pieces_jointes', 0);
    }

    public function test_chantier_supprime_avec_son_client_reste_accessible_a_la_corbeille(): void
    {
        $chantier = $this->client->chantiers()->create(['adresse' => '1 rue', 'ville' => 'Ville']);
        $chantier->delete();

        $this->actingAs(User::factory()->gerant()->create())->get('/corbeille')->assertSee('Adresse de chantier');
        $this->assertNotNull(Chantier::onlyTrashed()->find($chantier->id)->client);
    }
}
