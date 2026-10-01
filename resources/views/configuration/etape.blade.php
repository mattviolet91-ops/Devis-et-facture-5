@extends('layouts.configuration')

@section('titre', 'Configuration')
@if ($precedente)
    @section('parent', route('configuration.etape', $precedente))
@endif

@section('contenu')
    <div class="progression">
        <p class="progression-texte" id="progression-texte">Étape {{ $numero }} sur {{ $total }} : <strong>{{ $definition['titre'] }}</strong></p>
        <progress max="{{ $total }}" value="{{ $numero }}" aria-labelledby="progression-texte"></progress>
        <ol class="progression-etapes" aria-label="Écrans de la configuration">
            @foreach (\App\Support\Configuration::ETAPES as $cle => $info)
                <li @class(['faite' => \App\Support\Configuration::estFaite($cle), 'actuelle' => $cle === $etape])>
                    <a href="{{ route('configuration.etape', $cle) }}" @if ($cle === $etape) aria-current="step" @endif title="{{ $info['titre'] }}">
                        <span class="visuellement-cache">{{ $info['titre'] }}{{ \App\Support\Configuration::estFaite($cle) ? ' (fait)' : '' }}</span>
                        <span aria-hidden="true">{{ \App\Support\Configuration::numero($cle) }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>

    @if ($etape === 'metier' && ! \App\Support\Configuration::estFaite('metier'))
        <div class="message message-info">
            Bienvenue ! Ces quelques écrans préparent vos devis et factures. Chaque écran est enregistré :
            vous pouvez vous arrêter et reprendre plus tard. Les champs marqués <strong>(obligatoire)</strong> sont nécessaires.
        </div>
    @endif

    @if ($errors->any() && ! $errors->has('test') && ! $errors->has('terminer'))
        <div class="message message-erreur" role="alert">
            Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge ci-dessous.
        </div>
    @endif

    @if ($etape === 'recapitulatif')
        @include('configuration.recapitulatif')
    @else
        @if ($etape === 'apparence')
            <div class="carte apercu" id="apercu" aria-label="Aperçu des couleurs">
                <p class="apercu-titre">Aperçu</p>
                <div class="apercu-entete">Devis</div>
                <p><span class="apercu-bouton">Envoyer</span> <span class="apercu-rond">+</span></p>
            </div>
        @endif

        <form method="post" action="{{ route('configuration.enregistrer', $etape) }}" enctype="multipart/form-data" class="carte" novalidate>
            @csrf
            @method('put')

            @if ($etape === 'metier')
                @include('configuration.metier')
            @else
                @if ($etape === 'cgv')
                    <div class="message message-info">
                        Voici des conditions générales de départ pour votre métier. Lisez-les, adaptez-les à votre entreprise
                        et faites-les valider par un professionnel du droit.
                    </div>
                @endif
                @if ($etape === 'documents')
                    <p class="aide">Vous aviez déjà des devis ou factures numérotés ? Indiquez le numéro qui suit le dernier, pour continuer la suite sans trou.</p>
                @endif

                @foreach ($champs as $champ)
                    @include('reglages.champ', ['champ' => $champ, 'valeur' => $valeurs[$champ['cle']] ?? null])
                @endforeach

                @if ($etape === 'cgv')
                    <div class="champ">
                        <label class="case">
                            <input type="checkbox" name="cgv_relues" value="1" @error('cgv_relues') aria-invalid="true" aria-describedby="cgv-relues-erreur" @enderror>
                            <span>J'ai relu mes CGV</span>
                        </label>
                        @error('cgv_relues')
                            <p class="erreur-champ" id="cgv-relues-erreur">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            @endif

            <button type="submit" class="bouton bouton-large">Enregistrer et continuer</button>
        </form>

        @if ($etape === 'emails')
            <form method="post" action="{{ route('configuration.test-email') }}" class="carte">
                @csrf
                <p>Après avoir enregistré, vérifiez l'envoi : un email part vers <strong>{{ auth()->user()->email }}</strong>.</p>
                @error('test')
                    <p class="erreur-champ" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="bouton bouton-secondaire bouton-large">Envoyer un email de test</button>
            </form>
        @endif

        @if ($definition['facultatif'])
            <form method="post" action="{{ route('configuration.plus-tard', $etape) }}">
                @csrf
                <button type="submit" class="bouton bouton-secondaire bouton-large">Plus tard</button>
            </form>
        @endif
    @endif

    <div class="navigation-configuration">
        @if ($precedente)
            <a href="{{ route('configuration.etape', $precedente) }}">‹ Écran précédent</a>
        @endif
        <form method="post" action="{{ route('configuration.passer') }}">
            @csrf
            <button type="submit" class="bouton-lien">Passer la configuration pour l'instant</button>
        </form>
    </div>
@endsection
