@extends('layouts.app')

@section('titre', 'Corbeille')
@section('parent', route('plus'))

@section('contenu')
    <div class="message message-info">
        Ce qui est supprimé reste ici {{ \App\Support\Corbeille::JOURS_CONSERVATION }} jours, puis disparaît définitivement.
    </div>

    <div class="carte">
        @if ($elements->isEmpty())
            <p>La corbeille est vide.</p>
        @else
            <ul class="liste">
                @foreach ($elements as $element)
                    @php($restant = max(0, \App\Support\Corbeille::JOURS_CONSERVATION - (int) $element['modele']->deleted_at->diffInDays(now())))
                    <li class="ligne">
                        <div>
                            <strong>{{ $element['type'] }}</strong> — {{ \App\Support\Corbeille::libelle($element['modele']) }}
                            <small>Supprimé le {{ $element['modele']->deleted_at->timezone(config('app.timezone'))->format('d/m/Y') }} · encore {{ $restant }} jour(s)</small>
                        </div>
                        <form method="post" action="{{ route('corbeille.restaurer', [$element['cle'], $element['modele']->getKey()]) }}">
                            @csrf
                            <button type="submit" class="bouton bouton-secondaire">Remettre</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
