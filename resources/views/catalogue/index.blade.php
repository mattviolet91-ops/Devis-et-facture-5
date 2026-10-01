@extends('layouts.app')

@section('titre', 'Catalogue')
@section('parent', route('plus'))

@section('contenu')
    <form method="get" action="{{ route('catalogue.index') }}" class="recherche" role="search">
        <label for="q" class="visuellement-cache">Chercher une prestation</label>
        <input type="search" id="q" name="q" value="{{ $recherche }}" placeholder="Démoussage, gouttière…" autocomplete="off" enterkeyhint="search">
        <button type="submit" class="bouton">Chercher</button>
    </form>

    @if (auth()->user()->estGerant())
        <div class="actions-ligne">
            <a class="bouton" href="{{ route('catalogue.create') }}"><x-icone nom="plus" /> Nouvelle prestation</a>
        </div>
        @if ($sansPrix > 0)
            <div class="message message-info">{{ $sansPrix }} prestation(s) sans prix : touchez-les pour indiquer votre tarif.</div>
        @endif
    @endif

    @if ($total === 0)
        <div class="carte vide">
            <h2>Votre catalogue est vide</h2>
            <p>Ajoutez les prestations que vous proposez le plus souvent : elles s'ajoutent en un geste dans vos devis.</p>
            @if (auth()->user()->estGerant())
                @if ($departEnAttente)
                    <form method="post" action="{{ route('catalogue.depart') }}">
                        @csrf
                        <button type="submit" class="bouton bouton-large">Charger le catalogue de départ de mon métier</button>
                    </form>
                @endif
                <a class="bouton bouton-secondaire bouton-large" href="{{ route('catalogue.create') }}">Créer ma première prestation</a>
            @endif
        </div>
    @elseif ($groupes->isEmpty())
        <div class="carte vide"><p>Aucune prestation ne correspond à « {{ $recherche }} ».</p></div>
    @else
        @foreach ($groupes as $categorie => $prestations)
            <section class="carte" aria-label="{{ $categorie }}">
                <h2>{{ $categorie }}</h2>
                <ul class="liste">
                    @foreach ($prestations as $prestation)
                        <li>
                            @if (auth()->user()->estGerant())
                                <a class="liste-lien" href="{{ route('catalogue.edit', $prestation) }}">
                            @else
                                <div class="liste-lien">
                            @endif
                                <span class="libelle">
                                    {{ $prestation->nom }}
                                    <small @class(['bloc', 'texte-doux' => $prestation->prix_ht !== null, 'texte-danger' => $prestation->prix_ht === null])>
                                        {{ $prestation->prixAffiche() }}
                                        @if ($prestation->taux_tva !== null && ! \App\Support\Tva::estFranchise())
                                            · TVA {{ \App\Support\Tva::formater($prestation->taux_tva) }}
                                        @endif
                                    </small>
                                </span>
                            @if (auth()->user()->estGerant())
                                    <x-icone nom="fleche" />
                                </a>
                            @else
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
