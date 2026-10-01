@extends('layouts.app')

@section('titre', 'Photos')
@section('parent', route('clients.show', $client))

@section('contenu')
    <p class="texte-doux">Client : <strong>{{ $client->nomComplet() }}</strong></p>

    @include('photos._ajout', ['client' => $client, 'chantiers' => $chantiers, 'rdv' => null])

    @foreach (\App\Models\Photo::MOMENTS as $moment => $libelle)
        @if (isset($photos[$moment]))
            <section class="carte" aria-labelledby="titre-{{ $moment }}">
                <h2 id="titre-{{ $moment }}">{{ $libelle }} ({{ $photos[$moment]->count() }})</h2>
                <ul class="galerie">
                    @foreach ($photos[$moment] as $photo)
                        <li id="photo-{{ $photo->id }}">
                            <a href="{{ route('photos.show', $photo) }}" target="_blank" rel="noopener">
                                <img src="{{ route('photos.miniature', $photo) }}" alt="{{ $photo->texteAlternatif() }}" width="200" height="{{ (int) round(200 * $photo->hauteur / max(1, $photo->largeur)) }}" loading="lazy">
                            </a>
                            <details>
                                <summary>{{ $photo->legende ?: 'Modifier' }}@if ($photo->dans_documents) <span class="badge">Devis</span>@endif</summary>
                                <form method="post" action="{{ route('photos.update', $photo) }}">
                                    @csrf
                                    @method('put')
                                    <x-champ-liste nom="moment" :id="'moment-'.$photo->id" libelle="Moment" :options="\App\Models\Photo::MOMENTS" :valeur="$photo->moment" :vide="false" />
                                    <x-champ nom="legende" :id="'legende-'.$photo->id" libelle="Légende" :valeur="$photo->legende" />
                                    <label class="case case-compacte">
                                        <input type="checkbox" name="dans_documents" value="1" @checked($photo->dans_documents)>
                                        <span>Mettre dans les devis et factures</span>
                                    </label>
                                    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
                                </form>
                                <a class="bouton bouton-secondaire" href="{{ route('photos.annotation', $photo) }}">Dessiner sur la photo</a>
                                @if ($photo->original)
                                    <form method="post" action="{{ route('photos.retablir', $photo) }}">
                                        @csrf
                                        <button type="submit" class="bouton-lien">Remettre la photo d'origine</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('photos.destroy', $photo) }}">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="bouton-lien texte-danger" data-confirmer="Supprimer cette photo ?">Supprimer la photo</button>
                                </form>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if ($photos->isEmpty())
        <div class="carte vide"><p>Aucune photo pour ce client.</p></div>
    @endif
@endsection
