@extends('layouts.app')

@section('titre', 'Suivi commercial')

@section('contenu')
    <section class="carte" aria-labelledby="titre-demandes">
        <h2 id="titre-demandes">Nouvelles demandes ({{ $demandes->count() }})</h2>
        @if ($demandes->isEmpty())
            <p class="texte-doux">Aucune nouvelle demande.</p>
        @else
            <ul class="liste">
                @foreach ($demandes as $demande)
                    <li>
                        <a class="liste-lien" href="{{ route('suivi.demande', $demande) }}">
                            <span class="libelle">
                                <strong>{{ $demande->nom ?: 'Sans nom' }}</strong> <span class="badge">{{ $demande->libelleSource() }}</span>
                                <small class="bloc texte-doux">{{ $demande->recue_at->format('d/m/Y H:i') }}{{ $demande->ville ? ' · '.$demande->ville : '' }} · {{ \Illuminate\Support\Str::limit($demande->message, 60) }}</small>
                            </span>
                            <x-icone nom="fleche" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($lienFormulaire)
            <p class="aide">Lien du formulaire à mettre sur votre site :</p>
            <p><code class="lien-client" id="lien-formulaire">{{ $lienFormulaire }}</code> <button type="button" class="bouton-lien" data-copier="lien-formulaire" hidden>Copier</button></p>
        @endif
    </section>

    <section class="carte" aria-labelledby="titre-relances">
        <h2 id="titre-relances">Devis sans réponse ({{ $devis->count() }})</h2>
        @if ($devis->isEmpty())
            <p class="texte-doux">Aucun devis en attente depuis plus de 7 jours.</p>
        @else
            <p class="aide">{{ reglage('suivi.relances_devis') ? 'Relance automatique par email à 7 et 15 jours.' : 'Relances automatiques coupées (Réglages → Suivi commercial).' }}</p>
            <ul class="liste">
                @foreach ($devis as $d)
                    <li class="ligne">
                        <div>
                            <a href="{{ route('devis.show', $d) }}"><strong>{{ $d->client->nomComplet() }}</strong></a>
                            <small>{{ $d->reference() }} · {{ \App\Support\Montant::formater($d->total_ttc) }} · envoyé il y a {{ (int) $d->envoye_at->diffInDays(now()) }} jours{{ $d->relances ? ' · '.$d->relances.' relance(s)' : '' }}</small>
                        </div>
                        <span class="ligne-boutons">
                            @if ($d->client->telephone)
                                <a class="bouton bouton-secondaire" href="{{ \App\Support\Telephone::lien($d->client->telephone) }}">Appeler</a>
                            @endif
                            <a class="bouton bouton-secondaire" href="{{ route('envoi.create', ['devis', $d->id, 'modele' => 'relance_devis']) }}">Relancer</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (reglage('suivi.lien_avis'))
        <section class="carte" aria-labelledby="titre-avis">
            <h2 id="titre-avis">Demander un avis Google ({{ $avis->count() }})</h2>
            @if ($avis->isEmpty())
                <p class="texte-doux">Personne pour le moment.</p>
            @else
                <ul class="liste">
                    @foreach ($avis as $client)
                        <li class="ligne">
                            <a href="{{ route('clients.show', $client) }}">{{ $client->nomComplet() }}</a>
                            <form method="post" action="{{ route('suivi.avis', $client) }}">
                                @csrf
                                <button type="submit" class="bouton bouton-secondaire">Demander un avis</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <section class="carte">
            <h2>Avis Google</h2>
            <p class="texte-doux">Ajoutez votre lien d'avis Google dans <a href="{{ auth()->user()->estGerant() ? route('reglages.edit', 'suivi') : '#' }}">Réglages → Suivi commercial</a> pour le proposer aux clients satisfaits.</p>
        </section>
    @endif

    <section class="carte" aria-labelledby="titre-entretien">
        <h2 id="titre-entretien">Entretien à proposer ({{ $entretiens->count() }})</h2>
        @if ($entretiens->isEmpty())
            <p class="texte-doux">Aucun client à recontacter pour l'instant.</p>
        @else
            <ul class="liste">
                @foreach ($entretiens as $ligne)
                    <li class="ligne">
                        <div>
                            <a href="{{ route('clients.show', $ligne['client']) }}"><strong>{{ $ligne['client']->nomComplet() }}</strong></a>
                            <small>Dernier passage : {{ $ligne['dernier']->format('d/m/Y') }}</small>
                        </div>
                        <form method="post" action="{{ route('suivi.entretien', $ligne['client']) }}">
                            @csrf
                            @if ($ligne['client']->email)
                                <input type="hidden" name="email" value="1">
                                <button type="submit" class="bouton bouton-secondaire">Proposer par email</button>
                            @else
                                <button type="submit" class="bouton bouton-secondaire">Fait (appelé)</button>
                            @endif
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (auth()->user()->estGerant())
        <p class="centre"><a href="{{ route('suivi.emails') }}">Voir mes derniers emails</a></p>
    @endif
@endsection
