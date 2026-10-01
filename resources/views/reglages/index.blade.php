@extends('layouts.app')

@section('titre', 'Réglages')
@section('parent', route('plus'))

@section('contenu')
    <nav class="carte" aria-label="Rubriques des réglages">
        <ul class="liste">
            @foreach ($sections as $cle => $section)
                <li>
                    <a class="liste-lien" href="{{ route('reglages.edit', $cle) }}">
                        <x-icone :nom="$section['icone']" />
                        <span class="libelle">{{ $section['titre'] }}<small class="texte-doux bloc">{{ $section['description'] }}</small></span>
                        <x-icone nom="fleche" />
                    </a>
                </li>
            @endforeach
            <li>
                <a class="liste-lien" href="{{ route('reglages.textes') }}">
                    <x-icone nom="journal" />
                    <span class="libelle">Textes types<small class="texte-doux bloc">Phrases toutes prêtes pour vos devis et emails.</small></span>
                    <x-icone nom="fleche" />
                </a>
            </li>
            <li>
                <a class="liste-lien" href="{{ route('reglages.claude') }}">
                    <x-icone nom="reglages" />
                    <span class="libelle">Accès Claude<small class="texte-doux bloc">Clés pour préparer des devis avec Claude.</small></span>
                    <x-icone nom="fleche" />
                </a>
            </li>
            <li>
                <a class="liste-lien" href="{{ route('journal') }}">
                    <x-icone nom="journal" />
                    <span class="libelle">Journal d'activité<small class="texte-doux bloc">Qui a fait quoi, et quand.</small></span>
                    <x-icone nom="fleche" />
                </a>
            </li>
        </ul>
    </nav>

    @php($derniere = app(\App\Services\Sauvegardes::class)->derniere('base'))
    <div class="carte">
        <h2>Sauvegardes</h2>
        <p class="texte-doux">{{ $derniere ? 'Dernière sauvegarde : '.$derniere->timezone(config('app.timezone'))->format('d/m/Y à H:i').'.' : 'Aucune sauvegarde pour le moment.' }}</p>
        <a class="bouton bouton-secondaire" href="{{ route('reglages.sauvegardes') }}">Voir les sauvegardes</a>
    </div>
@endsection
