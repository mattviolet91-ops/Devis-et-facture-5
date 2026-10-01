@extends('layouts.client')

@section('titre', $facture->libelleType().' '.$facture->reference())

@section('contenu')
    <section class="carte">
        <h1>{{ $facture->libelleType() }} {{ $facture->reference() }}</h1>
        <p>Pour <strong>{{ $facture->client->nomComplet() }}</strong></p>
        <dl class="details">
            <dt>Date</dt><dd>{{ $facture->date_facture?->format('d/m/Y') }}</dd>
            @unless ($facture->estAvoir())
                <dt>À régler avant le</dt><dd>{{ $facture->date_echeance?->format('d/m/Y') }}</dd>
            @endunless
            <dt>Montant</dt><dd><strong>{{ \App\Support\Montant::formater($facture->total_ttc) }}</strong></dd>
            @unless ($facture->estAvoir())
                <dt>Reste à payer</dt><dd><strong>{{ \App\Support\Montant::formater($facture->resteAPayer()) }}</strong></dd>
            @endunless
        </dl>
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('client.pdf', $lien->jeton()) }}">Télécharger (PDF)</a>
    </section>

    @if (! $facture->estAvoir() && $facture->resteAPayer() > 0)
        <section class="carte" id="payer">
            <h2>Régler la facture</h2>
            @includeIf('client._paiement', ['facture' => $facture, 'lien' => $lien])
            @if (reglage('documents.iban'))
                <p>Par virement : IBAN <strong>{{ \App\Rules\Iban::formater((string) reglage('documents.iban')) }}</strong> — BIC {{ reglage('documents.bic') }}<br>Référence à indiquer : <strong>{{ $facture->numero }}</strong></p>
            @endif
        </section>
    @elseif (! $facture->estAvoir())
        <div class="carte message-succes"><p>Cette facture est réglée. Merci !</p></div>
    @endif
@endsection
