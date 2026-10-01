@props(['nom'])
@php
    $chemins = [
        'accueil' => 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
        'clients' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6',
        'plus' => 'M12 5v14M5 12h14',
        'devis' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'factures' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
        'planning' => 'M4 6h16v15H4zM4 10h16M8 3v5M16 3v5',
        'reglages' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12l2-1-2-4-2 1-2-2V4h-4v2L9 8 7 7 5 11l2 1v0l-2 1 2 4 2-1 2 2v2h4v-2l2-2 2 1 2-4z',
        'journal' => 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',
        'corbeille' => 'M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14',
        'sortie' => 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
        'cloche' => 'M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0',
        'fleche' => 'M9 6l6 6-6 6',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'icone']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $chemins[$nom] ?? '' }}"/></svg>
