@extends('layouts.app')

@section('titre', $devis->reference())
@section('parent', route('devis.index'))

@section('contenu')
    @if ($errors->has('devis'))
        <div class="message message-erreur" role="alert">{{ $errors->first('devis') }}</div>
    @endif

    <section class="carte">
        <p>
            <span @class(['badge', 'statut-'.$devis->statut])>{{ $devis->libelleStatut() }}</span>
            @if ($devis->version > 1) <span class="badge">Version {{ $devis->version }}</span> @endif
        </p>
        <dl class="details">
            <dt>Client</dt><dd><a href="{{ route('clients.show', $devis->client) }}">{{ $devis->client->nomComplet() }}</a></dd>
            @if ($devis->chantier)
                <dt>Chantier</dt><dd>{{ $devis->chantier->titre() }}</dd>
            @endif
            @if ($devis->objet)
                <dt>Objet</dt><dd>{{ $devis->objet }}</dd>
            @endif
            @if ($devis->date_devis)
                <dt>Date</dt><dd>{{ $devis->date_devis->format('d/m/Y') }}</dd>
                <dt>Valable jusqu'au</dt><dd>{{ $devis->dateValidite()?->format('d/m/Y') }}</dd>
            @else
                <dt>Validité</dt><dd>{{ $devis->validite_jours }} jours</dd>
            @endif
            @if ($devis->acompte_pourcentage)
                <dt>Acompte</dt><dd>{{ $devis->acompte_pourcentage }} %</dd>
            @endif
            @if ($devis->date_debut_travaux)
                <dt>Début des travaux</dt><dd>{{ $devis->date_debut_travaux->format('d/m/Y') }}{{ $devis->duree_travaux ? ' · '.$devis->duree_travaux : '' }}</dd>
            @endif
            @if ($devis->motif_refus)
                <dt>Motif du refus</dt><dd>{{ $devis->motif_refus }}</dd>
            @endif
        </dl>
    </section>

    <section class="carte" aria-labelledby="titre-lignes">
        <h2 id="titre-lignes">Détail</h2>
        @if ($devis->lignes->isEmpty())
            <p class="texte-doux">Aucune ligne.</p>
        @else
            <ul class="liste lignes-lecture">
                @foreach ($devis->lignes as $i => $ligne)
                    @if ($ligne->type === 'section')
                        <li class="section-lecture"><strong>{{ $ligne->designation }}</strong><span>{{ \App\Support\Montant::formater($detail['sections'][$i] ?? 0) }}</span></li>
                    @elseif ($ligne->type === 'texte')
                        <li class="texte-pre texte-doux">{{ $ligne->designation }}</li>
                    @else
                        <li class="ligne">
                            <div>
                                {{ $ligne->designation }} @if ($ligne->option)<span class="badge">Option</span>@endif
                                <small>{{ $ligne->quantiteAffichee() }} {{ $ligne->unite }} × {{ $ligne->prixAffiche() }}@unless (\App\Support\Tva::estFranchise()) · TVA {{ \App\Support\Tva::formater($ligne->taux_tva) }}@endunless</small>
                                @if ($ligne->description)
                                    <small class="texte-pre">{{ $ligne->description }}</small>
                                @endif
                            </div>
                            <strong>{{ $ligne->totalAffiche() }}</strong>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
        <dl class="totaux">
            @if ($devis->total_remise)
                <dt>Total brut HT</dt><dd>{{ \App\Support\Montant::formater($detail['total_brut_ht']) }}</dd>
                <dt>Remise</dt><dd>-{{ \App\Support\Montant::formater($devis->total_remise) }}</dd>
            @endif
            <dt>Total HT</dt><dd>{{ \App\Support\Montant::formater($devis->total_ht) }}</dd>
            @if (\App\Support\Tva::estFranchise())
                <dt class="ligne-mention">TVA non applicable, art. 293 B du CGI</dt>
            @else
                @foreach ($detail['tva'] as $taux => $tva)
                    <dt>TVA {{ \App\Support\Tva::formater($taux) }}</dt><dd>{{ \App\Support\Montant::formater($tva['montant']) }}</dd>
                @endforeach
            @endif
            <dt class="total-final">Total {{ \App\Support\Tva::estFranchise() ? 'à payer' : 'TTC' }}</dt><dd class="total-final">{{ \App\Support\Montant::formater($devis->total_ttc) }}</dd>
            @if ($devis->total_options_ht)
                <dt>Options (non comprises)</dt><dd>{{ \App\Support\Montant::formater($devis->total_options_ht) }} HT</dd>
            @endif
        </dl>
    </section>

    @if ($devis->signature)
        <section class="carte">
            <h2>Signature</h2>
            <p>Signé par <strong>{{ $devis->signature->nom }}</strong> le {{ $devis->signature->signe_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}
                ({{ $devis->signature->sur_place ? 'sur place' : 'en ligne' }}, adresse IP {{ $devis->signature->ip_address }}).</p>
            @if ($devis->signature->execution_immediate)
                <p class="message message-info">Le client a demandé que les travaux commencent avant la fin du délai de rétractation.</p>
            @endif
            @if ($devis->signature->pdf_sha256)
                <p class="aide">PDF signé · empreinte SHA-256 : <code class="empreinte">{{ $devis->signature->pdf_sha256 }}</code></p>
            @endif
        </section>
    @endif

    @if ($devis->demandesModification->isNotEmpty())
        <section class="carte">
            <h2>Demandes de modification du client</h2>
            <ul class="liste">
                @foreach ($devis->demandesModification as $demande)
                    <li class="ligne"><div>{{ $demande->message }}<small>{{ $demande->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</small></div></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($devis->statut !== 'brouillon')
        <section class="carte" id="lien-client">
            <h2>Lien pour le client</h2>
            @php($lienClient = \App\Models\LienClient::where('document_type', $devis->getMorphClass())->where('document_id', $devis->id)->whereNull('revoque_at')->first())
            @if ($lienClient)
                <p class="lien-client" id="adresse-lien">{{ $lienClient->url() }}</p>
                @include('_partage', [
                    'document' => $devis,
                    'message' => \App\Support\MessagesPrets::court(app(\App\Services\EnvoiEmail::class)->rediger($devis, 'devis')['corps']),
                    'routeEmail' => route('envoi.create', ['devis', $devis->id]),
                ])
                @unless (\App\Support\Configuration::accesClientsActif())
                    <p class="message message-info">Le lien marchera quand la configuration sera terminée.</p>
                @endunless
            @else
                <form method="post" action="{{ route('devis.lien', $devis) }}">
                    @csrf
                    <button type="submit" class="bouton bouton-secondaire bouton-large">Créer le lien du client</button>
                </form>
            @endif
        </section>
    @endif

    @if ($versions->count() > 1)
        <section class="carte">
            <h2>Versions</h2>
            <ul class="liste">
                @foreach ($versions as $v)
                    <li><a class="liste-lien" href="{{ route('devis.show', $v) }}" @if ($v->id === $devis->id) aria-current="page" @endif><span class="libelle">Version {{ $v->version }} · {{ $v->reference() }}</span><span class="badge">{{ $v->libelleStatut() }}</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($devis->statut === 'accepte' && auth()->user()->estGerant())
        <section class="carte" id="facturer">
            <h2>Facturer ce devis</h2>
            @if ($devis->factures->isNotEmpty())
                <ul class="liste">
                    @foreach ($devis->factures as $f)
                        <li><a class="liste-lien" href="{{ route('factures.show', $f) }}"><span class="libelle">{{ $f->libelleType() }} {{ $f->reference() }}<small class="bloc texte-doux">{{ \App\Support\Montant::formater($f->total_ttc) }} · {{ $f->libelleStatut() }}</small></span></a></li>
                    @endforeach
                </ul>
            @endif
            <form method="post" action="{{ route('factures.depuis-devis', $devis) }}" novalidate>
                @csrf
                <fieldset class="segments segments-2">
                    <legend>Type de facture</legend>
                    <label><input type="radio" name="type" value="facture" checked><span>Complète</span></label>
                    <label><input type="radio" name="type" value="acompte"><span>Acompte</span></label>
                    <label><input type="radio" name="type" value="situation"><span>Situation</span></label>
                    <label><input type="radio" name="type" value="solde"><span>Solde</span></label>
                </fieldset>
                <x-champ nom="pourcentage" libelle="Pourcentage (acompte ou avancement cumulé)" :valeur="$devis->acompte_pourcentage ?: null" inputmode="decimal" aide="Acompte : part du devis. Situation : avancement total des travaux." />
                <button type="submit" class="bouton bouton-large">Préparer la facture</button>
            </form>
        </section>
    @endif

    <section class="carte actions-devis" aria-label="Actions">
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('visionneuse', ['f' => '/devis/'.$devis->id.'/pdf', 'titre' => 'Devis '.$devis->reference()]) }}">Voir le PDF</a>
        @if ($devis->pdf_sha256)
            <p class="aide">PDF figé le {{ $devis->pdf_fige_at?->timezone(config('app.timezone'))->format('d/m/Y à H:i') }} · empreinte SHA-256 : <code class="empreinte">{{ $devis->pdf_sha256 }}</code></p>
        @endif
        @if (in_array($devis->statut, ['brouillon', 'envoye'], true))
            <a class="bouton bouton-large" href="{{ route('envoi.create', ['devis', $devis->id]) }}">{{ $devis->statut === 'brouillon' ? 'Envoyer au client' : 'Renvoyer par email' }}</a>
        @endif
        @if ($devis->estModifiable())
            <a class="bouton bouton-secondaire bouton-large" href="{{ route('devis.edit', $devis) }}">Modifier</a>
            <form method="post" action="{{ route('devis.envoyer', $devis) }}">
                @csrf
                <button type="submit" class="bouton bouton-secondaire bouton-large" data-confirmer="Le devis recevra son numéro et ne pourra plus être modifié. Continuer ?">Marquer comme envoyé</button>
            </form>
        @endif
        @if ($devis->peutEtreSigne())
            <a class="bouton bouton-large" href="{{ route('devis.signer', $devis) }}">Faire signer sur place</a>
        @endif
        @if (in_array($devis->statut, ['envoye', 'expire'], true))
            <form method="post" action="{{ route('devis.accepter', $devis) }}">
                @csrf
                <button type="submit" class="bouton bouton-large">Accepté par le client</button>
            </form>
            <details>
                <summary class="bouton bouton-secondaire bouton-large">Refusé par le client</summary>
                <form method="post" action="{{ route('devis.refuser', $devis) }}">
                    @csrf
                    <x-champ-texte-long nom="motif" libelle="Motif (facultatif)" :lignes="2" />
                    <button type="submit" class="bouton bouton-secondaire bouton-large">Confirmer le refus</button>
                </form>
            </details>
        @endif
        @if (in_array($devis->statut, ['envoye', 'refuse', 'expire'], true))
            <form method="post" action="{{ route('devis.version', $devis) }}">
                @csrf
                <button type="submit" class="bouton bouton-secondaire bouton-large">Faire une nouvelle version</button>
            </form>
        @endif
        <form method="post" action="{{ route('devis.dupliquer', $devis) }}">
            @csrf
            <button type="submit" class="bouton bouton-secondaire bouton-large">Dupliquer</button>
        </form>
        @if ($devis->estModifiable())
            <form method="post" action="{{ route('devis.destroy', $devis) }}">
                @csrf
                @method('delete')
                <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Mettre ce brouillon à la corbeille ?">Mettre le brouillon à la corbeille</button>
            </form>
        @endif
    </section>

    @include('_barre-etape', ['actions' => \App\Support\BarreEtape::devis($devis, auth()->user())])
@endsection
