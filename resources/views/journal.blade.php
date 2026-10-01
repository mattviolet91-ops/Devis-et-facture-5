@extends('layouts.app')

@section('titre', 'Journal d\'activité')
@section('parent', route('plus'))

@section('contenu')
    <div class="carte">
        @if ($activites->isEmpty())
            <p>Aucune activité pour le moment.</p>
        @else
            <ul class="liste">
                @foreach ($activites as $activite)
                    <li class="ligne">
                        <div>
                            {{ $activite->description }}
                            <small>
                                {{ $activite->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}
                                @if ($activite->user) · {{ $activite->user->nomAffiche() }} @endif
                            </small>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($activites->hasPages())
                <nav class="pagination" aria-label="Pages du journal">
                    @if ($activites->previousPageUrl())
                        <a class="bouton bouton-secondaire" href="{{ $activites->previousPageUrl() }}">‹ Plus récent</a>
                    @else
                        <span></span>
                    @endif
                    @if ($activites->nextPageUrl())
                        <a class="bouton bouton-secondaire" href="{{ $activites->nextPageUrl() }}">Plus ancien ›</a>
                    @endif
                </nav>
            @endif
        @endif
    </div>
@endsection
