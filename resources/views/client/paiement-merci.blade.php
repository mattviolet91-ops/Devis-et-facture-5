@extends('layouts.client')

@section('titre', 'Merci')

@section('contenu')
    <div class="carte">
        <h1>Merci pour votre paiement</h1>
        <p>Votre paiement pour la {{ mb_strtolower($facture->libelleType()) }} {{ $facture->numero }} est en cours de confirmation par myPOS. Vous recevrez une confirmation de notre part.</p>
        @if ($lien)
            <a class="bouton bouton-secondaire bouton-large" href="{{ route('client.document', $lien->jeton()) }}">Revenir à la facture</a>
        @endif
    </div>
@endsection
