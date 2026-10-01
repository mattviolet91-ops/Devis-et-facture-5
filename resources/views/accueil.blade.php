@extends('layouts.app')

@section('titre', 'Accueil')

@section('contenu')
    @if ($alertes->isNotEmpty())
        <section class="carte" aria-labelledby="titre-alertes">
            <h2 id="titre-alertes">Alertes</h2>
            <ul class="liste">
                @foreach ($alertes as $alerte)
                    <li class="ligne">
                        <div>
                            <strong>{{ $alerte->data['titre'] ?? 'Alerte' }}</strong>
                            <small>{{ $alerte->data['texte'] ?? '' }}</small>
                            @if (! empty($alerte->data['lien']))
                                <small><a href="{{ $alerte->data['lien'] }}">Voir</a></small>
                            @endif
                        </div>
                        <form method="post" action="{{ route('alertes.lue', $alerte->id) }}">
                            @csrf
                            <button type="submit" class="bouton bouton-secondaire">Vu</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="carte" aria-labelledby="titre-aujourdhui">
        <h2 id="titre-aujourdhui">Aujourd'hui <small class="texte-doux">{{ ucfirst(now()->translatedFormat('l j F')) }}</small></h2>
        @if ($aujourdhui->isEmpty())
            <p class="texte-doux">Rien au planning aujourd'hui. <a href="{{ route('planning.create', ['date' => today()->toDateString()]) }}">Ajouter un rendez-vous</a></p>
        @else
            <ul class="liste">
                @foreach ($aujourdhui as $rdv)
                    @php($prevision = $previsions[$rdv->id] ?? null)
                    <li class="rdv-jour">
                        <a href="{{ route('planning.show', $rdv) }}">
                            <strong @class(['barre' => $rdv->fait])>{{ $rdv->horaire() }} · {{ $rdv->titre }}</strong>
                            <small class="bloc texte-doux">{{ $rdv->client?->nomComplet() }}{{ $rdv->adresse() ? ' · '.$rdv->adresse() : '' }}</small>
                        </a>
                        @if ($prevision)
                            <small class="bloc">{{ $prevision->resume() }}
                                @foreach ($prevision->alertes() as $alerte)
                                    <span class="badge badge-danger">{{ $alerte }}</span>
                                @endforeach
                            </small>
                        @endif
                        <span class="ligne-boutons">
                            @if ($rdv->lienItineraire())
                                <a class="bouton bouton-secondaire" href="{{ $rdv->lienItineraire() }}" target="_blank" rel="noopener">Itinéraire</a>
                            @endif
                            @if ($rdv->client?->telephone)
                                <a class="bouton bouton-secondaire" href="{{ \App\Support\Telephone::lien($rdv->client->telephone) }}">Appeler</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($appels->isNotEmpty())
            <h3>Appels à passer</h3>
            <ul class="liste">
                @foreach ($appels as $appel)
                    <li class="ligne">
                        <div>
                            <a href="{{ $appel['lien'] }}"><strong>{{ $appel['nom'] }}</strong></a>
                            <small>{{ $appel['raison'] }}</small>
                        </div>
                        <a class="bouton bouton-secondaire" href="{{ \App\Support\Telephone::lien($appel['telephone']) }}">Appeler</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @foreach ($blocs as $bloc)
        @switch($bloc)
            @case('taches')
                <section class="carte" aria-labelledby="titre-taches">
                    <h2 id="titre-taches">Tâches à faire</h2>
                    @if (empty($taches))
                        <p class="texte-doux">Tout est à jour. Bravo !</p>
                    @else
                        <ul class="liste">
                            @foreach ($taches as $tache)
                                <li><a class="liste-lien" href="{{ $tache['lien'] }}"><span class="libelle">{{ $tache['texte'] }}</span><span class="badge">{{ $tache['nombre'] }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
                @break
            @case('chiffres')
                <section class="carte" aria-labelledby="titre-chiffres">
                    <h2 id="titre-chiffres">Chiffres clés</h2>
                    <dl class="chiffres-cles">
                        @foreach ($chiffres as $chiffre)
                            <div><dt>{{ $chiffre['libelle'] }}</dt><dd>{{ $chiffre['valeur'] }}</dd></div>
                        @endforeach
                    </dl>
                    @if (auth()->user()->estGerant())
                        <a href="{{ route('statistiques') }}">Voir les statistiques</a>
                    @endif
                </section>
                @break
            @case('activite')
                <section class="carte" aria-labelledby="titre-activite">
                    <h2 id="titre-activite">Activité récente</h2>
                    @if ($activite->isEmpty())
                        <p class="texte-doux">Pas encore d'activité.</p>
                    @else
                        <ul class="liste">
                            @foreach ($activite as $ligne)
                                <li class="ligne"><div>{{ $ligne->description }}<small>{{ $ligne->created_at->timezone(config('app.timezone'))->format('d/m H:i') }}{{ $ligne->user ? ' · '.$ligne->user->email : '' }}</small></div></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
                @break
            @case('astuce')
                @php($astuce = \App\Support\Astuces::duJour())
                <section class="carte astuce" aria-labelledby="titre-astuce">
                    <h2 id="titre-astuce">Astuce du jour</h2>
                    <p>{{ $astuce['texte'] }}</p>
                    <a href="{{ route($astuce['route']) }}">Essayer</a>
                </section>
                @break
        @endswitch
    @endforeach

    <p class="centre"><a href="{{ route('accueil.personnaliser') }}">Personnaliser l'accueil et la barre du bas</a></p>
@endsection
