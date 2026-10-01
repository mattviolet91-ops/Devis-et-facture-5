@extends('layouts.client')

@section('titre', 'Devis '.$devis->reference())

@push('scripts')
    <script src="{{ asset('js/signature.js') }}?v={{ filemtime(public_path('js/signature.js')) }}" defer></script>
@endpush

@section('contenu')
    <section class="carte">
        <h1>Devis {{ $devis->reference() }}</h1>
        <p>Pour <strong>{{ $devis->client->nomComplet() }}</strong>@if ($devis->objet) — {{ $devis->objet }}@endif</p>
        @if ($devis->dateValidite())
            <p class="texte-doux">Établi le {{ $devis->date_devis?->format('d/m/Y') }}, valable jusqu'au {{ $devis->dateValidite()->format('d/m/Y') }}.</p>
        @endif
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('client.pdf', $lien->jeton()) }}">Télécharger le devis (PDF)</a>
    </section>

    <section class="carte" aria-labelledby="titre-detail">
        <h2 id="titre-detail">Détail</h2>
        <ul class="liste lignes-lecture">
            @foreach ($devis->lignes as $i => $ligne)
                @if ($ligne->type === 'section')
                    <li class="section-lecture"><strong>{{ $ligne->designation }}</strong></li>
                @elseif ($ligne->type === 'texte')
                    <li class="texte-pre texte-doux">{{ $ligne->designation }}</li>
                @else
                    <li class="ligne">
                        <div>
                            {{ $ligne->designation }} @if ($ligne->option)<span class="badge">Option</span>@endif
                            <small>{{ $ligne->quantiteAffichee() }} {{ $ligne->unite }} × {{ $ligne->prixAffiche() }} HT</small>
                        </div>
                        <strong>{{ $ligne->totalAffiche() }}</strong>
                    </li>
                @endif
            @endforeach
        </ul>
        <dl class="totaux">
            <dt>Total HT</dt><dd>{{ \App\Support\Montant::formater($devis->total_ht) }}</dd>
            @if (\App\Support\Tva::estFranchise())
                <dt class="ligne-mention">TVA non applicable, art. 293 B du CGI</dt>
            @else
                <dt>TVA</dt><dd>{{ \App\Support\Montant::formater($devis->total_tva) }}</dd>
            @endif
            <dt class="total-final">Total {{ \App\Support\Tva::estFranchise() ? 'à payer' : 'TTC' }}</dt><dd class="total-final">{{ \App\Support\Montant::formater($devis->total_ttc) }}</dd>
            @if ($devis->acompte_pourcentage)
                <dt>Acompte à la commande ({{ $devis->acompte_pourcentage }} %)</dt><dd>{{ \App\Support\Montant::formater(intdiv($devis->total_ttc * $devis->acompte_pourcentage + 50, 100)) }}</dd>
            @endif
        </dl>
    </section>

    @if ($devis->statut === 'accepte')
        <div class="carte message-succes">
            <h2>Devis signé</h2>
            <p>Signé par {{ $devis->signature?->nom }} le {{ $devis->signature?->signe_at?->timezone(config('app.timezone'))->format('d/m/Y à H:i') ?? $devis->accepte_at?->format('d/m/Y') }}. Merci de votre confiance !</p>
        </div>
    @elseif ($devis->statut === 'refuse')
        <div class="carte"><p>Vous avez refusé ce devis. N'hésitez pas à nous recontacter.</p></div>
    @elseif ($devis->peutEtreSigne())
        @include('_signature', ['action' => route('client.signer', $lien->jeton())])

        <details class="carte">
            <summary><strong>Demander une modification</strong></summary>
            <form method="post" action="{{ route('client.modification', $lien->jeton()) }}" novalidate>
                @csrf
                <x-champ-texte-long nom="message" libelle="Que souhaitez-vous changer ?" :lignes="3" />
                <button type="submit" class="bouton bouton-secondaire bouton-large">Envoyer ma demande</button>
            </form>
        </details>

        <details class="carte">
            <summary><strong>Refuser le devis</strong></summary>
            <form method="post" action="{{ route('client.refuser', $lien->jeton()) }}" novalidate>
                @csrf
                <x-champ-texte-long nom="motif" libelle="Pourquoi ? (facultatif)" :lignes="2" />
                <button type="submit" class="bouton bouton-secondaire bouton-large">Refuser le devis</button>
            </form>
        </details>
    @else
        <div class="carte"><p>Ce devis n'est plus valable. Contactez-nous pour un devis à jour.</p></div>
    @endif
@endsection
