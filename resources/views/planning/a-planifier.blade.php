@extends('layouts.app')

@section('titre', 'À planifier')
@section('parent', route('planning.index'))

@section('contenu')
    @if ($devis->isEmpty())
        <div class="carte vide">
            <h2>Rien à planifier</h2>
            <p>Les devis acceptés dont le chantier n'est pas encore au planning apparaîtront ici.</p>
        </div>
    @else
        <p class="texte-doux">Devis acceptés dont le chantier n'a pas encore de date.</p>
        <ul class="liste carte">
            @foreach ($devis as $d)
                <li>
                    <a class="liste-lien" href="{{ route('planning.create', ['devis' => $d->id]) }}">
                        <span class="libelle">
                            <strong>{{ $d->client->nomComplet() }}</strong>
                            <small class="bloc texte-doux">{{ $d->reference() }}{{ $d->objet ? ' · '.$d->objet : '' }} · {{ \App\Support\Montant::formater($d->total_ttc) }}</small>
                            <small class="bloc">Accepté le {{ $d->accepte_at?->format('d/m/Y') }} — choisir une date</small>
                        </span>
                        <x-icone nom="planning" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
