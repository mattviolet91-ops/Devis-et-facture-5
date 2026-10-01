@extends('layouts.app')

@section('titre', 'Site internet')
@section('parent', route('statistiques'))

@section('contenu')
    @if (! $actif)
        <section class="carte">
            <h2>Compteur de visites</h2>
            <p>Le compteur n'est pas activé. Il suffit d'ajouter une ligne à votre site.</p>
            <p class="aide">Aucun cookie, aucune adresse IP enregistrée, données gardées 13 mois : pas besoin de bandeau cookies.</p>
            <a class="bouton bouton-large" href="{{ route('reglages.edit', 'site') }}">Activer dans les réglages</a>
        </section>
    @else
        <section class="carte">
            <h2>30 derniers jours</h2>
            <dl class="chiffres-cles">
                <div><dt>Visites</dt><dd>{{ $visites }}</dd></div>
                <div><dt>Pages vues</dt><dd>{{ $vues }}</dd></div>
                <div><dt>Contacts (appel, email, devis…)</dt><dd>{{ $contacts }}</dd></div>
                <div><dt>Taux de contact</dt><dd>{{ $visites ? number_format(100 * $contacts / $visites, 1, ',', '') : '0' }} %</dd></div>
            </dl>
            @php($max = max(1, ...array_column($jours, 'visites')))
            <svg class="graphe-mois" viewBox="0 0 300 120" role="img" aria-label="Visites par jour sur 30 jours">
                @foreach ($jours as $i => $j)
                    @php($h = (int) round(100 * $j['visites'] / $max))
                    <rect x="{{ $i * 10 + 1 }}" y="{{ 104 - $h }}" width="8" height="{{ max(1, $h) }}" rx="2" class="barre-graphe"><title>{{ $j['jour']->format('d/m') }} : {{ $j['visites'] }} visite(s)</title></rect>
                @endforeach
                <text x="1" y="117" class="texte-graphe">{{ $jours[0]['jour']->format('d/m') }}</text>
                <text x="299" y="117" text-anchor="end" class="texte-graphe">{{ end($jours)['jour']->format('d/m') }}</text>
            </svg>
        </section>

        @foreach (['boutons' => 'Boutons cliqués', 'sources' => 'Provenance', 'pages' => 'Pages les plus vues', 'appareils' => 'Appareils'] as $cle => $titre)
            <section class="carte">
                <h2>{{ $titre }}</h2>
                @if ($$cle->isEmpty())
                    <p class="texte-doux">Pas encore de données.</p>
                @else
                    <ul class="liste">
                        @foreach ($$cle as $ligne)
                            <li class="ligne"><span>{{ $cle === 'boutons' ? (\App\Models\VisiteSite::EVENEMENTS[$ligne->nom] ?? $ligne->nom) : ($ligne->nom ?: '—') }}</span><strong>{{ $ligne->total }}</strong></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        <section class="carte">
            <h2>Ligne à coller sur votre site</h2>
            <p class="aide">Dans l'en-tête de toutes les pages (WordPress : extension « WPCode » ou réglages du thème → Code d'en-tête).</p>
            <p><code class="lien-client" id="balise-compteur">&lt;script src="{{ route('compteur.script') }}" defer&gt;&lt;/script&gt;</code></p>
            <button type="button" class="bouton bouton-secondaire" data-copier="balise-compteur" hidden>Copier</button>
            @if ($site === '')
                <p class="message message-erreur">Indiquez l'adresse de votre site dans <a href="{{ route('reglages.edit', 'entreprise') }}">Réglages → Entreprise</a> : seules les visites de ce site sont comptées.</p>
            @endif
        </section>
    @endif

    <section class="carte" aria-labelledby="titre-jetpack">
        <h2 id="titre-jetpack">Statistiques Jetpack (WordPress.com)</h2>
        @if ($jetpackConnecte)
            @if (! empty($jetpack['jours']))
                @php($totalVues = array_sum(array_column($jetpack['jours'], 'vues')))
                <p>30 derniers jours : <strong>{{ array_sum(array_column($jetpack['jours'], 'visiteurs')) }}</strong> visiteurs, <strong>{{ $totalVues }}</strong> pages vues.</p>
                @foreach (['pages' => 'Pages les plus vues', 'sources' => 'Provenance', 'clics' => 'Liens cliqués'] as $cle => $titre)
                    @if (! empty($jetpack[$cle]))
                        <h3>{{ $titre }}</h3>
                        <ul class="liste">
                            @foreach ($jetpack[$cle] as $ligne)
                                <li class="ligne"><span>{{ $ligne['nom'] }}</span><strong>{{ $ligne['total'] }}</strong></li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
                <p class="aide">Mis à jour chaque nuit. Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse($jetpack['maj'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}.</p>
            @else
                <p class="texte-doux">Connecté. Les statistiques arrivent cette nuit.</p>
            @endif
            <form method="post" action="{{ route('statistiques.jetpack.deconnecter') }}">
                @csrf
                <button type="submit" class="bouton-lien texte-danger">Déconnecter Jetpack</button>
            </form>
        @elseif ($jetpackReglable)
            <p>Connectez votre compte WordPress.com (lecture seule des statistiques).</p>
            <form method="post" action="{{ route('statistiques.jetpack.connecter') }}">
                @csrf
                <button type="submit" class="bouton bouton-large">Connecter WordPress.com</button>
            </form>
        @else
            <p class="texte-doux">Seulement si votre site est sur WordPress.com. Renseignez l'application dans <a href="{{ route('reglages.edit', 'site') }}">Réglages → Site internet</a>.</p>
        @endif
    </section>
@endsection
