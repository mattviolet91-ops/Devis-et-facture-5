@extends('layouts.client')

@section('titre', 'Rapport d\'intervention')

@section('contenu')
    <section class="carte">
        <h1>Rapport d'intervention</h1>
        <p>Pour <strong>{{ $rapport->client->nomComplet() }}</strong> · {{ $rapport->date_intervention->format('d/m/Y') }}</p>
        <h2>{{ $rapport->titre }}</h2>
        @foreach (['travaux' => 'Travaux réalisés', 'constats' => 'Constat', 'conseils' => 'Préconisations'] as $champ => $titre)
            @if (trim((string) $rapport->{$champ}) !== '')
                <h3>{{ $titre }}</h3>
                <p class="texte-pre">{{ $rapport->{$champ} }}</p>
            @endif
        @endforeach
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('client.pdf', $lien->jeton()) }}">Télécharger (PDF)</a>
    </section>

    @if ($photos->isNotEmpty())
        <section class="carte">
            <h2>Photos</h2>
            <ul class="galerie">
                @foreach ($photos as $photo)
                    <li>
                        <a href="{{ route('client.photo', [$lien->jeton(), $photo->id]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('client.photo', [$lien->jeton(), $photo->id]) }}" alt="{{ $photo->texteAlternatif() }}" loading="lazy">
                        </a>
                        <small>{{ $photo->libelleMoment() }}{{ $photo->legende ? ' — '.$photo->legende : '' }}</small>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
