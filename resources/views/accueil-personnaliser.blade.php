@extends('layouts.app')

@section('titre', 'Personnaliser')
@section('parent', route('accueil'))

@section('contenu')
    @if ($errors->any())
        <div class="message message-erreur" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('accueil.enregistrer') }}" class="carte" novalidate>
        @csrf
        <h2>Blocs de l'accueil</h2>
        <p class="aide">« Aujourd'hui » reste toujours en haut. Cochez les blocs à afficher et choisissez leur ordre.</p>
        <ul class="liste">
            @foreach (\App\Support\Personnalisation::BLOCS as $cle => $libelle)
                @php($position = array_search($cle, $blocs, true))
                <li class="ligne">
                    <label class="case case-compacte">
                        <input type="checkbox" name="blocs[]" value="{{ $cle }}" @checked($position !== false)>
                        <span>{{ $libelle }}</span>
                    </label>
                    <label class="champ-compact">
                        <span class="visuellement-cache">Position de « {{ $libelle }} »</span>
                        <select name="ordre[{{ $cle }}]">
                            @for ($i = 1; $i <= count(\App\Support\Personnalisation::BLOCS); $i++)
                                <option value="{{ $i }}" @selected(($position === false ? $loop->iteration : $position + 1) === $i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </label>
                </li>
            @endforeach
        </ul>

        <h2>Barre du bas</h2>
        <p class="aide">Accueil, Nouveau et Plus restent à leur place. Choisissez les deux autres boutons.</p>
        <x-champ-liste nom="bouton_1" libelle="Deuxième bouton" :options="collect($permis)->map(fn ($b) => $b['libelle'])->all()" :valeur="$boutons[0]" :vide="false" />
        <x-champ-liste nom="bouton_2" libelle="Quatrième bouton" :options="collect($permis)->map(fn ($b) => $b['libelle'])->all()" :valeur="$boutons[1]" :vide="false" />

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>
@endsection
