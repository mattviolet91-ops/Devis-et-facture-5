@extends('layouts.app')

@section('titre', $section['titre'])
@section('parent', route('reglages'))

@section('contenu')
    @if ($errors->any() && ! $errors->has('test'))
        <div class="message message-erreur" role="alert">
            Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge ci-dessous.
        </div>
    @endif

    @if ($cle === 'apparence')
        <div class="carte apercu" id="apercu" aria-label="Aperçu des couleurs">
            <p class="apercu-titre">Aperçu</p>
            <div class="apercu-entete">Devis</div>
            <p><span class="apercu-bouton">Envoyer</span> <span class="apercu-rond">+</span></p>
        </div>
    @endif

    <form method="post" action="{{ route('reglages.update', $cle) }}" enctype="multipart/form-data" class="carte" novalidate>
        @csrf
        @method('put')

        @foreach ($section['champs'] as $champ)
            @include('reglages.champ', ['champ' => $champ, 'valeur' => $valeurs[$champ['cle']] ?? null])
        @endforeach

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>

    @if ($cle === 'emails')
        <form method="post" action="{{ route('reglages.emails.test') }}" class="carte">
            @csrf
            <h2>Vérifier l'envoi</h2>
            <p>Un email de test part vers <strong>{{ auth()->user()->email }}</strong>.</p>
            @error('test')
                <p class="erreur-champ" role="alert">{{ $message }}</p>
            @enderror
            <button type="submit" class="bouton bouton-secondaire bouton-large">Envoyer un email de test</button>
        </form>
    @endif
@endsection
