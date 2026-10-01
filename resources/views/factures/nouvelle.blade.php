@extends('layouts.app')

@section('titre', 'Nouvelle facture')
@section('parent', route('factures.index'))

@section('contenu')
    @if ($devisAFacturer->isNotEmpty())
        <div class="carte">
            <h2>Facturer un devis accepté</h2>
            <ul class="liste">
                @foreach ($devisAFacturer as $d)
                    <li><a class="liste-lien" href="{{ route('devis.show', $d) }}#facturer"><span class="libelle">{{ $d->client->nomComplet() }}<small class="bloc texte-doux">{{ $d->numero }} · {{ \App\Support\Montant::formater($d->total_ttc) }}</small></span><x-icone nom="fleche" /></a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="carte">
        <h2>Facture sans devis</h2>
        <form method="get" action="{{ route('factures.create') }}" class="recherche" role="search">
            <label for="q" class="visuellement-cache">Chercher un client</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom, ville…">
            <button type="submit" class="bouton">Chercher</button>
        </form>
        <ul class="liste">
            @foreach ($clients as $c)
                <li>
                    <form method="post" action="{{ route('factures.store') }}">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $c->id }}">
                        <button type="submit" class="liste-lien bouton-resultat"><span class="libelle">{{ $c->nomComplet() }}<small class="bloc texte-doux">{{ $c->ville }}</small></span><x-icone nom="fleche" /></button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
