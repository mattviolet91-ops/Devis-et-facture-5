@extends('layouts.app')

@section('titre', 'Devis')

@section('contenu')
    <div class="actions-ligne">
        <a class="bouton" href="{{ route('devis.create') }}"><x-icone nom="plus" /> Nouveau devis</a>
        <a class="bouton bouton-secondaire" href="{{ route('devis.express') }}">Devis express</a>
    </div>

    <nav class="onglets" aria-label="Filtrer les devis">
        <a href="{{ route('devis.index') }}" @if (! $statut) aria-current="page" @endif>Tous ({{ $total }})</a>
        @foreach (['brouillon' => 'Brouillons', 'envoye' => 'Envoyés', 'accepte' => 'Acceptés', 'refuse' => 'Refusés', 'expire' => 'Expirés'] as $cle => $libelle)
            <a href="{{ route('devis.index', ['statut' => $cle]) }}" @if ($statut === $cle) aria-current="page" @endif>{{ $libelle }} ({{ $compteurs[$cle] ?? 0 }})</a>
        @endforeach
    </nav>

    @if ($total === 0)
        <div class="carte vide">
            <h2>Aucun devis pour le moment</h2>
            <p>Créez votre premier devis, ou dictez-le en une phrase avec le devis express.</p>
            <a class="bouton bouton-large" href="{{ route('devis.create') }}">Créer mon premier devis</a>
        </div>
    @elseif ($devis->isEmpty())
        <div class="carte vide"><p>Aucun devis dans cette liste.</p></div>
    @else
        <ul class="liste carte">
            @foreach ($devis as $d)
                <li>
                    <a class="liste-lien" href="{{ route('devis.show', $d) }}">
                        <span class="libelle">
                            <strong>{{ $d->client->nomComplet() }}</strong>
                            <span @class(['badge', 'statut-'.$d->statut])>{{ $d->libelleStatut() }}</span>
                            <small class="bloc texte-doux">{{ $d->reference() }}{{ $d->objet ? ' · '.$d->objet : '' }} · {{ \App\Support\Montant::formater($d->total_ttc) }}</small>
                        </span>
                        <x-icone nom="fleche" />
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($devis->hasPages())
            <nav class="pagination" aria-label="Pages">
                @if ($devis->previousPageUrl())<a class="bouton bouton-secondaire" href="{{ $devis->previousPageUrl() }}">‹ Précédents</a>@else<span></span>@endif
                @if ($devis->nextPageUrl())<a class="bouton bouton-secondaire" href="{{ $devis->nextPageUrl() }}">Suivants ›</a>@endif
            </nav>
        @endif
    @endif
@endsection
