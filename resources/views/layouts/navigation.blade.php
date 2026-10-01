@php
    $onglets = [
        ['route' => 'accueil', 'url' => route('accueil'), 'libelle' => 'Accueil', 'icone' => 'accueil', 'actif' => request()->routeIs('accueil')],
        ['route' => 'clients', 'url' => route('clients.index'), 'libelle' => 'Clients', 'icone' => 'clients', 'actif' => request()->routeIs('clients.*', 'chantiers.*')],
        ['route' => 'nouveau', 'url' => route('nouveau'), 'libelle' => 'Nouveau', 'icone' => 'plus', 'actif' => request()->routeIs('nouveau')],
        ['route' => 'devis', 'url' => route('devis.index'), 'libelle' => 'Devis', 'icone' => 'devis', 'actif' => request()->routeIs('devis.*')],
        ['route' => 'plus', 'url' => route('plus'), 'libelle' => 'Plus', 'icone' => 'menu', 'actif' => request()->routeIs('plus', 'journal', 'corbeille', 'reglages*', 'catalogue.*', 'factures.*', 'planning.*', 'suivi*')],
    ];
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
