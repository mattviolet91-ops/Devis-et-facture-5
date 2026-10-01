@extends('layouts.client')

@section('titre', 'Paiement sécurisé')

@push('scripts')
    <script src="{{ asset('js/envoi-auto.js') }}" defer></script>
@endpush

@section('contenu')
    <form method="post" action="{{ $url }}" class="carte" id="formulaire-mypos" data-envoi-auto>
        @foreach ($champs as $nom => $valeur)
            <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
        @endforeach
        <h1>Paiement sécurisé</h1>
        <p>Vous allez être dirigé vers la page de paiement sécurisée de myPOS. Vos données de carte ne passent pas par notre application.</p>
        <p>Montant : <strong>{{ str_replace('.', ',', $champs['Amount']) }} €</strong></p>
        @if (str_contains($url, 'checkout-test'))
            <p class="message message-info">Mode test : aucun argent réel ne sera prélevé.</p>
        @endif
        <button type="submit" class="bouton bouton-large">Continuer vers le paiement</button>
    </form>
@endsection
