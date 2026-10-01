@extends('layouts.app')

@section('titre', $rdv->estChantier() ? 'Chantier' : 'Rendez-vous')
@section('parent', route('planning.index', ['date' => $rdv->debut->toDateString()]))

@section('contenu')
    <section class="carte">
        <h2 @class(['barre' => $rdv->fait])>{{ $rdv->titre }}</h2>
        <p>
            <span @class(['badge', 'badge-succes' => $rdv->estChantier()])>{{ $rdv->estChantier() ? 'Chantier' : 'Rendez-vous' }}</span>
            @if ($rdv->fait)
                <span class="badge badge-succes">Fait</span>
            @endif
        </p>
        <dl class="details">
            <dt>Quand</dt>
            <dd>{{ ucfirst($rdv->debut->translatedFormat('l j F Y')) }} · {{ $rdv->horaire() }}</dd>
            @if ($rdv->client)
                <dt>Client</dt>
                <dd><a href="{{ route('clients.show', $rdv->client) }}">{{ $rdv->client->nomComplet() }}</a>
                    @if ($rdv->client->telephone)
                        · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $rdv->client->telephone) }}">{{ $rdv->client->telephone }}</a>
                    @endif
                </dd>
            @endif
            @if ($rdv->adresse())
                <dt>Adresse</dt>
                <dd>{{ $rdv->adresse() }} · <a href="{{ $rdv->lienItineraire() }}" target="_blank" rel="noopener">Itinéraire</a></dd>
            @endif
            @if ($rdv->chantier?->acces)
                <dt>Accès</dt><dd>{{ $rdv->chantier->acces }}</dd>
            @endif
            @if ($rdv->user)
                <dt>Qui y va</dt><dd>{{ $rdv->user->email }}</dd>
            @endif
            @if ($rdv->devis)
                <dt>Devis</dt><dd><a href="{{ route('devis.show', $rdv->devis) }}">{{ $rdv->devis->reference() }}</a></dd>
            @endif
            @if ($rdv->rappel_client_jours)
                <dt>Rappel au client</dt><dd>Email {{ $rdv->rappel_client_jours === 1 ? 'la veille' : '2 jours avant' }}{{ $rdv->rappelEnvoye('client') ? ' (envoyé)' : '' }}</dd>
            @endif
            @if ($rdv->notes)
                <dt>Remarques</dt><dd class="texte-pre">{{ $rdv->notes }}</dd>
            @endif
        </dl>
    </section>

    @if ($previsions->isNotEmpty())
        <section class="carte" aria-labelledby="titre-meteo">
            <h2 id="titre-meteo">Météo prévue</h2>
            <ul class="liste">
                @foreach ($previsions as $jour => $prevision)
                    <li class="ligne">
                        <span>{{ ucfirst($prevision->date()->translatedFormat('D j')) }} : {{ $prevision->resume() }}</span>
                        @foreach ($prevision->alertes() as $alerte)
                            <span class="badge badge-danger">{{ $alerte }}</span>
                        @endforeach
                    </li>
                @endforeach
            </ul>
            <p class="aide">Source : MET Norway (yr.no). Mise à jour chaque heure.</p>
        </section>
    @endif

    <div class="actions-ligne">
        <a class="bouton" href="{{ route('planning.edit', $rdv) }}">Modifier</a>
        <form method="post" action="{{ route('planning.fait', $rdv) }}">
            @csrf
            <button type="submit" class="bouton bouton-secondaire">{{ $rdv->fait ? 'Remettre à faire' : 'Marquer comme fait' }}</button>
        </form>
        <a class="bouton bouton-secondaire" href="{{ route('planning.ics', $rdv) }}">Ajouter à mon agenda</a>
    </div>

    @if ($rdv->client)
        <form method="post" action="{{ route('planning.provenance', $rdv) }}" class="carte">
            @csrf
            <h2>Comment ce client nous a connus</h2>
            <x-champ-liste nom="provenance" libelle="Provenance" :options="(array) reglage('clients.provenances')" :valeur="$rdv->client->provenance" />
            <x-champ nom="provenance_detail" libelle="Précision (facultatif)" :valeur="$rdv->client->provenance_detail" />
            <button type="submit" class="bouton bouton-secondaire">Enregistrer la provenance</button>
        </form>
    @endif
@endsection
