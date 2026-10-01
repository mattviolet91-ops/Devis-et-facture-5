@extends('layouts.app')

@section('titre', 'Factures')
@section('parent', route('plus'))

@section('contenu')
    <div class="carte chiffres">
        <div><span class="chiffre">{{ \App\Support\Montant::formater($resteTotal) }}</span><span class="texte-doux">à encaisser</span></div>
        <div><span @class(['chiffre', 'texte-danger' => $enRetard > 0])>{{ $enRetard }}</span><span class="texte-doux">en retard</span></div>
    </div>

    <div class="actions-ligne">
        <a class="bouton" href="{{ route('factures.create') }}"><x-icone nom="plus" /> Nouvelle facture</a>
    </div>

    <nav class="onglets" aria-label="Filtrer les factures">
        @foreach (['' => 'Toutes', 'brouillons' => 'Brouillons', 'a-encaisser' => 'À encaisser', 'retard' => 'En retard', 'payees' => 'Payées', 'avoirs' => 'Avoirs'] as $cle => $libelle)
            <a href="{{ route('factures.index', $cle ? ['filtre' => $cle] : []) }}" @if ($filtre === $cle) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </nav>

    @if ($total === 0)
        <div class="carte vide">
            <h2>Aucune facture pour le moment</h2>
            <p>Facturez un devis accepté en un geste, ou créez une facture directement.</p>
            <a class="bouton bouton-large" href="{{ route('factures.create') }}">Créer ma première facture</a>
        </div>
    @elseif ($factures->isEmpty())
        <div class="carte vide"><p>Aucune facture dans cette liste.</p></div>
    @else
        <ul class="liste carte">
            @foreach ($factures as $f)
                @php($encaisser = ! $f->estAvoir() && $f->statut === 'emise' && $f->resteAPayer() > 0)
                <li @if ($encaisser) data-glisser @endif>
                    @if ($encaisser)
                        <span class="action-glisser"><a href="{{ route('factures.show', $f) }}#encaisser" tabindex="-1">Encaisser<span class="visuellement-cache"> {{ $f->reference() }}</span></a></span>
                    @endif
                    <a class="liste-lien contenu-glisser" href="{{ route('factures.show', $f) }}">
                        <span class="libelle">
                            <strong>{{ $f->client->nomComplet() }}</strong>
                            <span @class(['badge', 'statut-'.$f->statut, 'en-retard' => $f->estEnRetard()])>{{ $f->libelleStatut() }}</span>
                            <small class="bloc texte-doux">{{ $f->libelleType() }} {{ $f->reference() }} · {{ \App\Support\Montant::formater($f->total_ttc) }}@if ($f->statut === 'emise' && ! $f->estAvoir()) · reste {{ \App\Support\Montant::formater($f->resteAPayer()) }}@endif</small>
                        </span>
                        <x-icone nom="fleche" />
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($factures->hasPages())
            <nav class="pagination" aria-label="Pages">
                @if ($factures->previousPageUrl())
                    <a class="bouton bouton-secondaire" href="{{ $factures->previousPageUrl() }}">‹ Précédentes</a>
                @else
                    <span></span>
                @endif
                @if ($factures->nextPageUrl())
                    <a class="bouton bouton-secondaire" href="{{ $factures->nextPageUrl() }}">Suivantes ›</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
