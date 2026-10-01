@extends('layouts.app')

@section('titre', $client->nomComplet())
@section('parent', route('clients.index'))

@section('contenu')
    <section class="carte" aria-labelledby="titre-coordonnees">
        <h2 id="titre-coordonnees" class="visuellement-cache">Coordonnées</h2>
        @if ($client->estProfessionnel())
            <p><span class="badge">Professionnel</span> @if ($client->contact()) Contact : {{ $client->contact() }} @endif</p>
        @endif

        <div class="actions-rapides">
            @if ($client->telephone)
                <a class="bouton" href="{{ \App\Support\Telephone::lien($client->telephone) }}">Appeler</a>
                <a class="bouton bouton-secondaire" href="{{ \App\Support\Telephone::lien($client->telephone, 'sms') }}">SMS</a>
            @endif
            @if ($client->email)
                <a class="bouton bouton-secondaire" href="mailto:{{ $client->email }}">Email</a>
            @endif
        </div>

        <dl class="details">
            @if ($client->telephone)
                <dt>Téléphone</dt><dd>{{ \App\Support\Telephone::formater($client->telephone) }}</dd>
            @endif
            @if ($client->telephone2)
                <dt>Autre téléphone</dt><dd><a href="{{ \App\Support\Telephone::lien($client->telephone2) }}">{{ \App\Support\Telephone::formater($client->telephone2) }}</a></dd>
            @endif
            @if ($client->email)
                <dt>Email</dt><dd>{{ $client->email }}</dd>
            @endif
            @if ($client->adresseComplete())
                <dt>Adresse</dt><dd>{{ $client->adresseComplete() }}</dd>
            @endif
            @if ($client->siret)
                <dt>SIRET</dt><dd>{{ $client->siret }}</dd>
            @endif
            @if ($client->provenance)
                <dt>Nous a connus par</dt><dd>{{ $client->provenance }}{{ $client->provenance_detail ? ' — '.$client->provenance_detail : '' }}</dd>
            @endif
            <dt>Client depuis le</dt><dd>{{ $client->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}</dd>
        </dl>

        <a class="bouton bouton-secondaire bouton-large" href="{{ route('clients.edit', $client) }}">Modifier</a>
    </section>

    <section class="carte" aria-labelledby="titre-chantiers">
        <h2 id="titre-chantiers">Adresses de chantier</h2>
        @forelse ($client->chantiers as $chantier)
            <article class="chantier">
                <h3>{{ $chantier->titre() }}</h3>
                @if ($chantier->libelle && $chantier->adresseComplete())
                    <p>{{ $chantier->adresseComplete() }}</p>
                @endif
                <p class="texte-doux">
                    {{ implode(' · ', array_filter([$chantier->type_toiture, $chantier->surfaceAffichee(), $chantier->pente !== null ? 'pente '.$chantier->pente.'°' : null])) }}
                </p>
                @if ($chantier->acces)
                    <p><strong>Accès :</strong> {{ $chantier->acces }}</p>
                @endif
                @if ($chantier->notes)
                    <p class="texte-pre">{{ $chantier->notes }}</p>
                @endif
                <div class="actions-ligne">
                    @if ($chantier->lienItineraire())
                        <a class="bouton bouton-secondaire" href="{{ $chantier->lienItineraire() }}" target="_blank" rel="noopener">Itinéraire</a>
                    @endif
                    <a class="bouton bouton-secondaire" href="{{ route('chantiers.edit', $chantier) }}">Modifier</a>
                </div>
            </article>
        @empty
            <p class="texte-doux">Aucune adresse de chantier.</p>
        @endforelse
        <a class="bouton bouton-large" href="{{ route('chantiers.create', $client) }}"><x-icone nom="plus" /> Ajouter une adresse de chantier</a>
    </section>

    <section class="carte" id="notes" aria-labelledby="titre-notes">
        <h2 id="titre-notes">Notes</h2>
        <form method="post" action="{{ route('notes.store', $client) }}" novalidate>
            @csrf
            <x-champ-texte-long nom="texte" libelle="Nouvelle note" :lignes="3" />
            <button type="submit" class="bouton bouton-secondaire">Ajouter la note</button>
        </form>
        <ul class="liste">
            @foreach ($client->notes as $note)
                <li class="ligne">
                    <div>
                        <p class="texte-pre">{{ $note->texte }}</p>
                        <small>{{ $note->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}{{ $note->user ? ' · '.$note->user->nomAffiche() : '' }}</small>
                    </div>
                    @if (auth()->user()->estGerant() || $note->user_id === auth()->id())
                        <form method="post" action="{{ route('notes.destroy', $note) }}">
                            @csrf
                            @method('delete')
                            <button type="submit" class="bouton-lien texte-danger">Supprimer<span class="visuellement-cache"> la note</span></button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="carte" id="photos" aria-labelledby="titre-photos">
        <h2 id="titre-photos">Photos et rapports</h2>
        <ul class="liste">
            <li><a class="liste-lien" href="{{ route('photos.index', $client) }}"><span class="libelle">Photos du chantier ({{ $client->photos_count }})</span><x-icone nom="fleche" /></a></li>
            @foreach ($client->rapports as $rapport)
                <li><a class="liste-lien" href="{{ route('rapports.show', $rapport) }}"><span class="libelle">{{ $rapport->titre }}<small class="bloc texte-doux">Rapport du {{ $rapport->date_intervention->format('d/m/Y') }}{{ $rapport->envoye_at ? ' · envoyé' : '' }}</small></span><x-icone nom="fleche" /></a></li>
            @endforeach
        </ul>
        <a class="bouton bouton-secondaire" href="{{ route('rapports.create', ['client' => $client->id]) }}">Nouveau rapport d'intervention</a>
    </section>

    <section class="carte" id="pieces" aria-labelledby="titre-pieces">
        <h2 id="titre-pieces">Pièces jointes</h2>
        <ul class="liste">
            @forelse ($client->piecesJointes as $piece)
                <li class="ligne">
                    <div>
                        <a href="{{ route('pieces.show', $piece) }}">{{ $piece->nom }}</a>
                        <small>{{ $piece->tailleAffichee() }} · {{ $piece->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}</small>
                    </div>
                    <form method="post" action="{{ route('pieces.destroy', $piece) }}">
                        @csrf
                        @method('delete')
                        <button type="submit" class="bouton-lien texte-danger">Supprimer<span class="visuellement-cache"> {{ $piece->nom }}</span></button>
                    </form>
                </li>
            @empty
                <li class="texte-doux">Aucun fichier.</li>
            @endforelse
        </ul>
        <form method="post" action="{{ route('pieces.store', $client) }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="champ">
                <label for="fichier">Ajouter un fichier (photo, PDF…)</label>
                <input type="file" id="fichier" name="fichier" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.csv"
                    @error('fichier') aria-invalid="true" aria-describedby="fichier-erreur" @enderror>
                @error('fichier')
                    <p class="erreur-champ" id="fichier-erreur">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="bouton bouton-secondaire">Envoyer le fichier</button>
        </form>
    </section>

    <form method="post" action="{{ route('clients.destroy', $client) }}" class="zone-suppression">
        @csrf
        @method('delete')
        <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Mettre ce client à la corbeille ?">Mettre le client à la corbeille</button>
    </form>
@endsection
