@extends('layouts.app')

@section('titre', 'Sauvegardes')
@section('parent', route('reglages'))

@section('contenu')
    <section class="carte">
        <p>La base de données est sauvegardée <strong>chaque nuit</strong>, les fichiers (photos, PDF) <strong>chaque mois</strong>. On garde les 30 dernières sauvegardes de la base et les 12 dernières des fichiers.</p>
        <div class="message message-info">
            <strong>Gardez une copie hors du serveur :</strong> une fois par mois, téléchargez la dernière sauvegarde de la base et celle des fichiers sur votre ordinateur ou une clé USB. Elles s'ajoutent aux sauvegardes de votre hébergeur.
        </div>
        <form method="post" action="{{ route('reglages.sauvegardes.maintenant') }}">
            @csrf
            <button type="submit" class="bouton bouton-large">Sauvegarder la base maintenant</button>
        </form>
    </section>

    <section class="carte">
        <h2>Sauvegardes disponibles</h2>
        @if (empty($sauvegardes))
            <p class="texte-doux">Aucune sauvegarde pour le moment.</p>
        @else
            <ul class="liste">
                @foreach ($sauvegardes as $s)
                    <li class="ligne">
                        <div>
                            <strong>{{ $s['type'] === 'base' ? 'Base de données' : 'Fichiers' }}</strong>
                            <small>{{ $s['date']->timezone(config('app.timezone'))->format('d/m/Y à H:i') }} · {{ number_format($s['taille'] / 1048576, 1, ',', ' ') }} Mo</small>
                        </div>
                        <a class="bouton bouton-secondaire" href="{{ route('reglages.sauvegardes.telecharger', $s['nom']) }}">Télécharger<span class="visuellement-cache"> {{ $s['nom'] }}</span></a>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="aide">Pour remettre une sauvegarde, voir le guide d'installation (commande « app:restaurer »). Les données actuelles sont d'abord sauvegardées.</p>
    </section>
@endsection
