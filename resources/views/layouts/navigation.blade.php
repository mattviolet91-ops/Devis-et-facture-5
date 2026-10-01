@php
    $boutons = \App\Support\Personnalisation::boutons(auth()->user());
    $choix = fn (string $cle) => \App\Support\Personnalisation::BOUTONS[$cle];
    $onglets = [
        ['route' => 'accueil', 'url' => route('accueil'), 'libelle' => 'Accueil', 'icone' => 'accueil', 'actif' => request()->routeIs('accueil*')],
        ['route' => $boutons[0], 'url' => route($choix($boutons[0])['route']), 'libelle' => $choix($boutons[0])['libelle'], 'icone' => $choix($boutons[0])['icone'], 'actif' => request()->routeIs(...$choix($boutons[0])['actif'])],
        ['route' => 'nouveau', 'url' => route('nouveau'), 'libelle' => 'Nouveau', 'icone' => 'plus', 'actif' => request()->routeIs('nouveau')],
        ['route' => $boutons[1], 'url' => route($choix($boutons[1])['route']), 'libelle' => $choix($boutons[1])['libelle'], 'icone' => $choix($boutons[1])['icone'], 'actif' => request()->routeIs(...$choix($boutons[1])['actif'])],
        ['route' => 'plus', 'url' => route('plus'), 'libelle' => 'Plus', 'icone' => 'menu', 'actif' => false],
    ];
    $dejaActif = collect($onglets)->contains('actif', true);
    $onglets[4]['actif'] = ! $dejaActif && ! request()->routeIs('nouveau');
@endphp
<nav class="barre-bas" aria-label="Menu principal">
    @foreach ($onglets as $onglet)
        <a href="{{ $onglet['url'] }}"
           @class(['nouveau' => $onglet['route'] === 'nouveau'])
           @if ($onglet['actif']) aria-current="page" @endif>
            @if ($onglet['route'] === 'nouveau')
                <span class="rond"><x-icone nom="plus" /></span>
            @else
                <x-icone :nom="$onglet['icone']" />
            @endif
            <span>{{ $onglet['libelle'] }}</span>
        </a>
    @endforeach
</nav>
