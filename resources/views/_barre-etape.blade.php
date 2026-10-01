{{-- Barre d'actions fixe en bas de l'écran : les actions de l'étape en cours. --}}
@if (! empty($actions))
    <div class="espace-barre-etape" aria-hidden="true"></div>
    <nav class="barre-etape" aria-label="Actions de l'étape">
        @foreach ($actions as $action)
            @if (isset($action['post']))
                <form method="post" action="{{ $action['post'] }}">
                    @csrf
                    <button type="submit" @class(['bouton', 'bouton-secondaire' => ! $loop->first]) @isset($action['confirmer']) data-confirmer="{{ $action['confirmer'] }}" @endisset>{{ $action['libelle'] }}</button>
                </form>
            @else
                <a href="{{ $action['url'] }}" @class(['bouton', 'bouton-secondaire' => ! $loop->first])>{{ $action['libelle'] }}</a>
            @endif
        @endforeach
    </nav>
@endif
