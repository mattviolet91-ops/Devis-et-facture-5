<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Photo;
use App\Models\RendezVous;
use App\Services\Photos;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    public function index(Client $client): View
    {
        return view('photos.index', [
            'client' => $client,
            'photos' => $client->photos()->with('chantier')->get()->groupBy('moment'),
            'chantiers' => $client->chantiers()->get(),
        ]);
    }

    public function store(Request $request, Client $client, Photos $photos): RedirectResponse
    {
        $donnees = $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp'],
            'moment' => ['required', Rule::in(array_keys(Photo::MOMENTS))],
            'chantier_id' => ['nullable', 'integer', Rule::exists('chantiers', 'id')->where('client_id', $client->id)],
            'rendez_vous_id' => ['nullable', 'integer', 'exists:rendez_vous,id'],
            'legende' => ['nullable', 'string', 'max:200'],
        ], [
            'photos.required' => 'Choisissez au moins une photo.',
            'photos.max' => '20 photos au plus à la fois.',
            'photos.*.max' => 'Une photo ne doit pas dépasser 15 Mo.',
            'photos.*.mimetypes' => 'Seules les photos JPEG, PNG ou WebP sont acceptées. Sur iPhone : Réglages → Appareil photo → Formats → « Le plus compatible ».',
        ]);

        $rdv = isset($donnees['rendez_vous_id']) ? RendezVous::find($donnees['rendez_vous_id']) : null;
        $total = 0;
        foreach ($request->file('photos') as $fichier) {
            try {
                $photos->enregistrer($fichier, [
                    'client_id' => $client->id,
                    'chantier_id' => $donnees['chantier_id'] ?? $rdv?->chantier_id,
                    'rendez_vous_id' => $rdv?->id,
                    'moment' => $donnees['moment'],
                    'legende' => $donnees['legende'] ?? null,
                    'user_id' => $request->user()->id,
                ]);
                $total++;
            } catch (\RuntimeException) {
                return back()->withErrors(['photos' => 'Une des photos est illisible. Réessayez avec une autre photo.']);
            }
        }

        Journal::ecrire('photo.ajout', $total.' photo(s) ajoutée(s) pour '.$client->nomComplet(), $client);

        return redirect()->to($request->input('retour') === 'planning' && $rdv ? route('planning.show', $rdv) : route('photos.index', $client))
            ->with('statut', $total > 1 ? $total.' photos ajoutées.' : 'Photo ajoutée.');
    }

    public function show(Photo $photo): StreamedResponse
    {
        return $this->fichier($photo, false);
    }

    public function miniature(Photo $photo): StreamedResponse
    {
        return $this->fichier($photo, true);
    }

    public function update(Request $request, Photo $photo): RedirectResponse
    {
        $photo->update($request->validate([
            'moment' => ['required', Rule::in(array_keys(Photo::MOMENTS))],
            'legende' => ['nullable', 'string', 'max:200'],
            'dans_documents' => ['nullable', 'boolean'],
        ]) + ['dans_documents' => $request->boolean('dans_documents')]);

        return redirect()->to(route('photos.index', $photo->client_id).'#photo-'.$photo->id)->with('statut', 'Photo modifiée.');
    }

    public function annotation(Photo $photo): View
    {
        return view('photos.annoter', ['photo' => $photo]);
    }

    /**
     * Enregistre la photo avec les traits dessinés (flèches, cercles…).
     */
    public function annoter(Request $request, Photo $photo, Photos $photos): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:15360', 'mimetypes:image/jpeg,image/png']]);

        try {
            $photos->annoter($photo, $request->file('image'));
        } catch (\RuntimeException) {
            return back()->withErrors(['image' => 'Le dessin n\'a pas pu être enregistré.']);
        }

        return redirect()->to(route('photos.index', $photo->client_id).'#photo-'.$photo->id)->with('statut', 'Dessin enregistré sur la photo.');
    }

    public function retablir(Photo $photo, Photos $photos): RedirectResponse
    {
        $photos->retablir($photo);

        return redirect()->to(route('photos.index', $photo->client_id).'#photo-'.$photo->id)->with('statut', 'Photo d\'origine remise.');
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        $client = $photo->client_id;
        $photo->delete();

        return redirect()->route('photos.index', $client)->with('statut', 'Photo supprimée.');
    }

    private function fichier(Photo $photo, bool $miniature): StreamedResponse
    {
        $chemin = $miniature ? $photo->miniature : $photo->chemin;
        abort_unless(Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, 'photo-'.$photo->id.'.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
