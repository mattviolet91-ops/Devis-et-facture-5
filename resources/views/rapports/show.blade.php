@extends('layouts.app')

@section('titre', 'Rapport d\'intervention')
@section('parent', route('clients.show', $rapport->client))

@section('contenu')
    <section class="carte">
        <h2>{{ $rapport->titre }}</h2>
        <p class="texte-doux">{{ $rapport->client->nomComplet() }} · {{ $rapport->date_intervention->format('d/m/Y') }}
            @if ($rapport->envoye_at)
                <span class="badge badge-succes">Envoyé</span>
            @endif
        </p>
        @foreach (['travaux' => 'Travaux réalisés', 'constats' => 'Constat', 'conseils' => 'Préconisations'] as $champ => $titre)
            @if (trim((string) $rapport->{$champ}) !== '')
                <h3>{{ $titre }}</h3>
                <p class="texte-pre">{{ $rapport->{$champ} }}</p>
            @endif
        @endforeach
        @if ($photos->isNotEmpty())
            <h3>Photos ({{ $photos->count() }})</h3>
            <ul class="galerie">
                @foreach ($photos as $photo)
                    <li><img src="{{ route('photos.miniature', $photo) }}" alt="{{ $photo->texteAlternatif() }}" loading="lazy"><small>{{ $photo->libelleMoment() }}{{ $photo->legende ? ' — '.$photo->legende : '' }}</small></li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="actions-ligne">
        <a class="bouton" href="{{ route('envoi.create', ['rapport', $rapport->id]) }}">Envoyer au client</a>
        <a class="bouton bouton-secondaire" href="{{ route('visionneuse', ['f' => '/rapports/'.$rapport->id.'/pdf', 'titre' => 'Rapport d\'intervention']) }}">Voir le PDF</a>
        <a class="bouton bouton-secondaire" href="{{ route('rapports.edit', $rapport) }}">Modifier</a>
    </div>
@endsection
