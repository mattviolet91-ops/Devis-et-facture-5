@extends('layouts.app')

@section('titre', 'Planning')

@section('contenu')
    <div class="actions-ligne">
        <a class="bouton" href="{{ route('planning.create', ['date' => $vue === 'semaine' && $date->isPast() ? null : $date->toDateString()]) }}"><x-icone nom="plus" /> Ajouter</a>
        <a class="bouton bouton-secondaire" href="{{ route('planning.a-planifier') }}">À planifier @if ($aPlanifier)<span class="badge">{{ $aPlanifier }}</span>@endif</a>
    </div>

    <nav class="onglets" aria-label="Affichage">
        <a href="{{ route('planning.index', ['vue' => 'semaine', 'date' => $date->toDateString()]) }}" @if ($vue === 'semaine') aria-current="page" @endif>Semaine</a>
        <a href="{{ route('planning.index', ['vue' => 'mois', 'date' => $date->toDateString()]) }}" @if ($vue === 'mois') aria-current="page" @endif>Mois</a>
    </nav>

    <nav class="navigation-periode" aria-label="Changer de période">
        <a class="bouton bouton-secondaire bouton-icone" href="{{ route('planning.index', ['vue' => $vue, 'date' => $precedent->toDateString()]) }}" aria-label="{{ $vue === 'mois' ? 'Mois précédent' : 'Semaine précédente' }}">‹</a>
        <h2>
            @if ($vue === 'mois')
                {{ ucfirst($date->translatedFormat('F Y')) }}
            @else
                Semaine du {{ \Illuminate\Support\Arr::first($jours)['date']->translatedFormat('j F') }}
            @endif
        </h2>
        <a class="bouton bouton-secondaire bouton-icone" href="{{ route('planning.index', ['vue' => $vue, 'date' => $suivant->toDateString()]) }}" aria-label="{{ $vue === 'mois' ? 'Mois suivant' : 'Semaine suivante' }}">›</a>
    </nav>
    <p class="centre"><a href="{{ route('planning.index', ['vue' => $vue]) }}">Aujourd'hui</a></p>

    @if ($vue === 'mois')
        <div class="calendrier-mois" role="grid" aria-label="Mois">
            <div class="calendrier-entete" role="row">
                @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $j)
                    <span role="columnheader">{{ $j }}</span>
                @endforeach
            </div>
            @foreach (array_chunk($jours, 7, true) as $semaine)
                <div class="calendrier-semaine" role="row">
                    @foreach ($semaine as $cle => $jour)
                        <a role="gridcell" href="{{ route('planning.index', ['vue' => 'semaine', 'date' => $cle]) }}#jour-{{ $cle }}"
                           @class(['calendrier-jour', 'hors-mois' => $jour['date']->month !== $date->month, 'aujourdhui' => $jour['date']->isToday()])
                           aria-label="{{ $jour['date']->translatedFormat('l j F') }} : {{ $jour['rdv']->count() }} élément(s)">
                            <span class="numero">{{ $jour['date']->day }}</span>
                            @foreach ($jour['rdv']->take(3) as $rdv)
                                <span @class(['pastille', 'pastille-chantier' => $rdv->estChantier(), 'pastille-fait' => $rdv->fait])>{{ \Illuminate\Support\Str::limit($rdv->titre, 14) }}</span>
                            @endforeach
                            @if ($jour['rdv']->count() > 3)
                                <span class="texte-doux">+{{ $jour['rdv']->count() - 3 }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        @foreach ($jours as $cle => $jour)
            <section class="carte jour-planning @if ($jour['date']->isToday()) aujourdhui @endif" id="jour-{{ $cle }}" aria-labelledby="titre-{{ $cle }}">
                <h3 id="titre-{{ $cle }}">{{ ucfirst($jour['date']->translatedFormat('l j F')) }} @if ($jour['date']->isToday())<span class="badge">Aujourd'hui</span>@endif</h3>
                @if ($jour['rdv']->isEmpty())
                    <p class="texte-doux"><a href="{{ route('planning.create', ['date' => $cle]) }}">Rien de prévu — ajouter</a></p>
                @else
                    <ul class="liste">
                        @foreach ($jour['rdv'] as $rdv)
                            @php($prevision = ($previsions[$rdv->id] ?? collect())->get($cle))
                            <li data-glisser>
                                <form method="post" action="{{ route('planning.fait', $rdv) }}" class="action-glisser">
                                    @csrf
                                    <button type="submit" tabindex="-1">{{ $rdv->fait ? 'À faire' : 'Fait' }}<span class="visuellement-cache"> : {{ $rdv->titre }}</span></button>
                                </form>
                                <a class="liste-lien contenu-glisser" href="{{ route('planning.show', $rdv) }}">
                                    <span class="libelle">
                                        <strong @class(['barre' => $rdv->fait])>{{ $rdv->titre }}</strong>
                                        <span @class(['badge', 'badge-succes' => $rdv->estChantier()])>{{ $rdv->estChantier() ? 'Chantier' : 'Rendez-vous' }}</span>
                                        <small class="bloc texte-doux">{{ $rdv->horaire() }}{{ $rdv->client ? ' · '.$rdv->client->nomComplet() : '' }}{{ $rdv->user ? ' · '.$rdv->user->email : '' }}</small>
                                        @if ($prevision)
                                            <small class="bloc">{{ $prevision->resume() }}
                                                @foreach ($prevision->alertes() as $alerte)
                                                    <span class="badge badge-danger">{{ $alerte }}</span>
                                                @endforeach
                                            </small>
                                        @endif
                                    </span>
                                    <x-icone nom="fleche" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    @endif

    <p class="aide centre">
        <a href="{{ route('planning.exporter') }}">Mettre le planning dans l'agenda du téléphone (.ics)</a><br>
        Météo : MET Norway (yr.no), mise à jour chaque heure.
    </p>
@endsection
