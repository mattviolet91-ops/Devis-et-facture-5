@extends('layouts.client')

@section('titre', 'Demander un devis')

@section('contenu')
    <section class="carte">
        <h1>Demander un devis</h1>
        <p>Décrivez vos travaux : nous vous recontactons rapidement.</p>

        @if ($errors->any())
            <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}).</div>
        @endif

        <form method="post" action="{{ route('demande.store') }}" novalidate>
            @csrf
            <input type="hidden" name="horodatage" value="{{ $horodatage }}">
            <div class="piege" aria-hidden="true">
                <label for="site_web">Ne pas remplir</label>
                <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
            </div>

            <x-champ nom="nom" libelle="Nom et prénom" autocomplete="name" />
            <x-champ nom="telephone" libelle="Téléphone" type="tel" autocomplete="tel" inputmode="tel" />
            <x-champ nom="email" libelle="Email" type="email" autocomplete="email" inputmode="email" />
            <x-champ nom="ville" libelle="Ville des travaux" autocomplete="address-level2" />
            <x-champ-texte-long nom="message" libelle="Vos travaux" :lignes="5" aide="Par exemple : fuite au-dessus de la cuisine, toiture en tuiles d'environ 100 m²." />

            <label class="case">
                <input type="checkbox" name="accord" value="1" @checked(old('accord'))>
                <span>J'accepte que mes informations servent uniquement à me recontacter pour cette demande.</span>
            </label>
            @error('accord')
                <p class="erreur-champ">{{ $message }}</p>
            @enderror

            <button type="submit" class="bouton bouton-large">Envoyer ma demande</button>
        </form>
    </section>
@endsection
