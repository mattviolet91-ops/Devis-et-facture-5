<?php

namespace App\Http\Controllers;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\PieceJointe;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceJointeController extends Controller
{
    public const TYPES = 'pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx,odt,ods,txt,csv';

    public function store(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:'.self::TYPES],
            'chantier_id' => ['nullable', 'integer'],
        ], [
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.mimes' => 'Ce type de fichier n\'est pas accepté (PDF, photo, Word, Excel ou texte).',
            'fichier.required' => 'Choisissez un fichier.',
        ]);

        $cible = $request->filled('chantier_id')
            ? $client->chantiers()->findOrFail($request->integer('chantier_id'))
            : $client;

        $fichier = $request->file('fichier');
        $extension = strtolower($fichier->getClientOriginalExtension() ?: $fichier->guessExtension() ?: 'bin');
        $chemin = $fichier->storeAs('clients/'.$client->id, Str::uuid().'.'.$extension, 'local');

        $piece = $cible->piecesJointes()->create([
            'nom' => Str::limit($fichier->getClientOriginalName(), 190, ''),
            'chemin' => $chemin,
            'mime' => (string) $fichier->getMimeType(),
            'taille' => (int) $fichier->getSize(),
            'user_id' => $request->user()->id,
        ]);

        Journal::ecrire('piece_jointe.ajout', 'Pièce jointe ajoutée pour '.$client->nomComplet().' : '.$piece->nom, $client);

        return redirect()->to(route('clients.show', $client).'#pieces')->with('statut', 'Fichier ajouté.');
    }

    public function show(PieceJointe $piece): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($piece->chemin), 404);

        return Storage::disk('local')->response($piece->chemin, $piece->nom, [
            'Content-Type' => $piece->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ], $piece->estImage() || $piece->mime === 'application/pdf' ? 'inline' : 'attachment');
    }

    public function destroy(PieceJointe $piece): RedirectResponse
    {
        $client = $piece->attachable instanceof Chantier ? $piece->attachable->client : $piece->attachable;
        $piece->delete();

        return redirect()->to(route('clients.show', $client).'#pieces')->with('statut', 'Fichier supprimé.');
    }
}
