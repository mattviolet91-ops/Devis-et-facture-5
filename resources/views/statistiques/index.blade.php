@extends('layouts.app')

@section('titre', 'Statistiques')
@section('parent', route('plus'))

@section('contenu')
    <nav class="onglets" aria-label="Période">
        @foreach (\App\Http\Controllers\StatistiquesController::PERIODES as $cle => $libelle)
            <a href="{{ route('statistiques', ['periode' => $cle]) }}" @if ($periode === $cle) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </nav>

    <section class="carte">
        <dl class="chiffres-cles">
            <div><dt>Facturé (HT)</dt><dd>{{ \App\Support\Montant::formater($total) }}</dd></div>
            <div><dt>Encaissé</dt><dd>{{ \App\Support\Montant::formater($encaisse) }}</dd></div>
        </dl>
    </section>

    @php($max = max(1, ...array_column($mois, 'montant')))
    <section class="carte" aria-labelledby="titre-mois">
        <h2 id="titre-mois">Facturé HT par mois (12 derniers mois)</h2>
        <svg class="graphe-mois" viewBox="0 0 360 160" role="img" aria-labelledby="titre-mois">
            @foreach ($mois as $i => $m)
                @php($hauteur = (int) round(120 * max(0, $m['montant']) / $max))
                <rect x="{{ $i * 30 + 4 }}" y="{{ 130 - $hauteur }}" width="22" height="{{ max(1, $hauteur) }}" rx="3" class="barre-graphe"><title>{{ $m['titre'] }} : {{ \App\Support\Montant::formater($m['montant']) }}</title></rect>
                <text x="{{ $i * 30 + 15 }}" y="148" text-anchor="middle" class="texte-graphe">{{ mb_substr($m['libelle'], 0, 3) }}</text>
            @endforeach
        </svg>
        <details>
            <summary>Voir les montants</summary>
            <ul class="liste">
                @foreach ($mois as $m)
                    <li class="ligne"><span>{{ $m['titre'] }}</span><strong>{{ \App\Support\Montant::formater($m['montant']) }}</strong></li>
                @endforeach
            </ul>
        </details>
    </section>

    @php($maxProvenance = max(1, ...array_map(fn ($p) => max($p['facture_ht'], $p['signe_ht']), $provenances ?: [['facture_ht' => 0, 'signe_ht' => 0]])))
    <section class="carte" aria-labelledby="titre-provenance">
        <h2 id="titre-provenance">Par provenance</h2>
        <p class="aide">« Comment nous a-t-il connus ? » : à remplir sur la fiche client ou depuis un rendez-vous.</p>
        @if (empty($provenances))
            <p class="texte-doux">Pas encore de clients.</p>
        @else
            <ul class="liste">
                @foreach ($provenances as $p)
                    <li class="stat-ligne">
                        <strong>{{ $p['nom'] }}</strong>
                        <svg class="graphe-barre" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true">
                            <rect x="0" y="0" width="{{ round(100 * max(0, $p['facture_ht']) / $maxProvenance, 1) }}" height="8" class="barre-graphe" />
                        </svg>
                        <small class="bloc texte-doux">{{ $p['clients'] }} client(s) · {{ $p['devis'] }} devis envoyé(s) · {{ $p['signes'] }} signé(s) ({{ \App\Support\Montant::formater($p['signe_ht']) }} HT) · facturé {{ \App\Support\Montant::formater($p['facture_ht']) }} HT</small>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="carte" aria-labelledby="titre-comptes">
        <h2 id="titre-comptes">Par compte</h2>
        <ul class="liste">
            @foreach ($comptes as $c)
                <li class="ligne">
                    <div>
                        <strong>{{ $c['nom'] }}</strong> <span class="badge">{{ $c['role'] }}</span>
                        <small>{{ $c['devis'] }} devis envoyé(s) · {{ $c['signes'] }} signé(s) · {{ \App\Support\Montant::formater($c['signe_ht']) }} HT · {{ $c['rdv'] }} rendez-vous</small>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <p class="centre"><a href="{{ route('statistiques.site') }}">Statistiques du site internet</a></p>
@endsection
