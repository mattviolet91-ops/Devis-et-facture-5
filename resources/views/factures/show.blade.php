@extends('layouts.app')

@section('titre', $facture->libelleType().' '.$facture->reference())
@section('parent', route('factures.index'))

@section('contenu')
    @if ($errors->any())
        <div class="message message-erreur" role="alert">
            @foreach ($errors->all() as $erreur)
                <p>{{ $erreur }}</p>
            @endforeach
        </div>
    @endif
    @if (session('rappel') || $rappel)
        <div class="message message-info" role="status"><strong>Rappel :</strong> {{ session('rappel') ?? $rappel }}</div>
    @endif

    <section class="carte">
        <p>
            <span @class(['badge', 'statut-'.$facture->statut, 'en-retard' => $facture->estEnRetard()])>{{ $facture->libelleStatut() }}</span>
            <span class="badge">{{ $facture->libelleType() }}</span>
        </p>
        <dl class="details">
            <dt>Client</dt><dd><a href="{{ route('clients.show', $facture->client) }}">{{ $facture->client->nomComplet() }}</a></dd>
            @if ($facture->devis)
                <dt>Devis</dt><dd><a href="{{ route('devis.show', $facture->devis) }}">{{ $facture->devis->reference() }}</a></dd>
            @endif
            @if ($facture->origine)
                <dt>Facture d'origine</dt><dd><a href="{{ route('factures.show', $facture->origine) }}">{{ $facture->origine->reference() }}</a></dd>
            @endif
            @if ($facture->motif)
                <dt>Motif</dt><dd>{{ $facture->motif }}</dd>
            @endif
            @if ($facture->date_facture)
                <dt>Date</dt><dd>{{ $facture->date_facture->format('d/m/Y') }}</dd>
            @endif
            @if ($facture->date_echeance && ! $facture->estAvoir())
                <dt>Échéance</dt><dd>{{ $facture->date_echeance->format('d/m/Y') }}</dd>
            @endif
            <dt>Total</dt><dd><strong>{{ \App\Support\Montant::formater($facture->total_ttc) }}</strong></dd>
            @if (! $facture->estAvoir() && $facture->statut !== 'brouillon')
                @if ($facture->totalAvoirs())
                    <dt>Avoirs</dt><dd>-{{ \App\Support\Montant::formater($facture->totalAvoirs()) }}</dd>
                @endif
                <dt>Reste à payer</dt><dd><strong @class(['texte-danger' => $facture->estEnRetard()])>{{ \App\Support\Montant::formater($facture->resteAPayer()) }}</strong></dd>
            @endif
        </dl>
    </section>

    <section class="carte" aria-labelledby="titre-lignes">
        <h2 id="titre-lignes">Détail</h2>
        <ul class="liste lignes-lecture">
            @foreach ($facture->lignes as $ligne)
                @if ($ligne->type === 'section')
                    <li class="section-lecture"><strong>{{ $ligne->designation }}</strong></li>
                @elseif ($ligne->type === 'texte')
                    <li class="texte-pre texte-doux">{{ $ligne->designation }}</li>
                @else
                    <li class="ligne">
                        <div>{{ $ligne->designation }}<small>{{ $ligne->quantiteAffichee() }} {{ $ligne->unite }} × {{ $ligne->prixAffiche() }}</small></div>
                        <strong>{{ $ligne->totalAffiche() }}</strong>
                    </li>
                @endif
            @endforeach
        </ul>
        <dl class="totaux">
            <dt>Total HT</dt><dd>{{ \App\Support\Montant::formater($facture->total_ht) }}</dd>
            @if (\App\Support\Tva::estFranchise())
                <dt class="ligne-mention">TVA non applicable, art. 293 B du CGI</dt>
            @else
                @foreach ($detail['tva'] as $taux => $tva)
                    <dt>TVA {{ \App\Support\Tva::formater($taux) }}</dt><dd>{{ \App\Support\Montant::formater($tva['montant']) }}</dd>
                @endforeach
            @endif
            <dt class="total-final">Total</dt><dd class="total-final">{{ \App\Support\Montant::formater($facture->total_ttc) }}</dd>
        </dl>
    </section>

    <section class="carte actions-devis" aria-label="Actions">
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('visionneuse', ['f' => '/factures/'.$facture->id.'/pdf', 'titre' => $facture->libelleType().' '.$facture->reference()]) }}">Voir le PDF</a>
        @if ($facture->estModifiable())
            <a class="bouton bouton-large" href="{{ route('factures.edit', $facture) }}">Modifier</a>
            <form method="post" action="{{ route('factures.emettre', $facture) }}">
                @csrf
                <button type="submit" class="bouton bouton-secondaire bouton-large" data-confirmer="La facture recevra son numéro et ne pourra plus être modifiée (seulement corrigée par un avoir). Continuer ?">Émettre {{ $facture->estAvoir() ? 'l\'avoir' : 'la facture' }}</button>
            </form>
            <form method="post" action="{{ route('factures.destroy', $facture) }}">
                @csrf
                @method('delete')
                <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Supprimer ce brouillon ?">Supprimer le brouillon</button>
            </form>
        @endif
        @if ($facture->pdf_sha256)
            <p class="aide">PDF figé à l'émission · empreinte SHA-256 : <code class="empreinte">{{ $facture->pdf_sha256 }}</code></p>
        @endif
    </section>

    @if (! $facture->estAvoir() && $facture->resteAPayer() > 0 && $facture->statut === 'emise')
        <section class="carte" id="encaisser">
            <h2>Encaisser</h2>
            <form method="post" action="{{ route('paiements.store', $facture) }}" novalidate>
                @csrf
                <div class="grille-2">
                    <x-champ nom="montant" libelle="Montant (€)" :valeur="number_format($facture->resteAPayer() / 100, 2, ',', '')" inputmode="decimal" />
                    <x-champ nom="date_paiement" libelle="Date" type="date" :valeur="now()->toDateString()" />
                </div>
                <x-champ-liste nom="mode" libelle="Mode de paiement" :options="collect(\App\Models\Paiement::MODES)->except('carte_en_ligne')->all()" :vide="false" />
                <x-champ nom="reference" libelle="Référence (n° de chèque, virement…)" />
                <button type="submit" class="bouton bouton-large">Enregistrer l'encaissement</button>
            </form>
            @if (app(\App\Services\MyPos::class)->mode() === 'test')
                <form method="post" action="{{ route('paiements.essai', $facture) }}">
                    @csrf
                    <button type="submit" class="bouton bouton-secondaire bouton-large">Essayer le paiement par carte (mode test)</button>
                </form>
            @endif
        </section>
    @endif

    @if ($facture->paiements->isNotEmpty())
        <section class="carte">
            <h2>Encaissements</h2>
            <ul class="liste">
                @foreach ($facture->paiements as $paiement)
                    <li class="ligne">
                        <div>
                            <strong>{{ $paiement->montantAffiche() }}</strong> · {{ $paiement->libelleMode() }}
                            <small>{{ $paiement->date_paiement->format('d/m/Y') }}{{ $paiement->reference ? ' · '.$paiement->reference : '' }}{{ $paiement->notes ? ' · '.$paiement->notes : '' }}</small>
                        </div>
                        @if ($paiement->montant > 0 && ! $facture->paiements->contains('annule_paiement_id', $paiement->id))
                            <details>
                                <summary class="bouton-lien">Annuler</summary>
                                <form method="post" action="{{ route('paiements.annuler', $paiement) }}">
                                    @csrf
                                    <x-champ nom="motif" libelle="Motif" :id="'motif-'.$paiement->id" />
                                    <button type="submit" class="bouton bouton-secondaire">Confirmer</button>
                                </form>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="aide">Un encaissement ne s'efface jamais : une annulation ajoute une ligne inverse.</p>
        </section>
    @endif

    @if (! $facture->estAvoir() && $facture->statut === 'emise')
        @php
            $texteRelance = app(\App\Services\EnvoiEmail::class)->rediger($facture, $facture->estEnRetard() ? 'relance' : 'facture');
            $message = \App\Support\MessagesPrets::court($texteRelance['corps']);
        @endphp
        <section class="carte" id="lien-client">
            <h2>Lien de paiement et relances</h2>
            @if ($lien)
                <p class="lien-client" id="adresse-lien">{{ $lien->url() }}</p>
                <div class="actions-rapides">
                    <a class="bouton bouton-secondaire" href="{{ \App\Support\MessagesPrets::whatsapp($facture->client->telephone, $message) }}" target="_blank" rel="noopener">WhatsApp</a>
                    <a class="bouton bouton-secondaire" href="{{ \App\Support\MessagesPrets::sms($facture->client->telephone, $message) }}">SMS</a>
                    <button type="button" class="bouton bouton-secondaire" data-copier="message-pret" hidden>Copier</button>
                </div>
                <textarea id="message-pret" class="visuellement-cache" readonly>{{ $message }}</textarea>
            @else
                <form method="post" action="{{ route('factures.lien', $facture) }}">
                    @csrf
                    <button type="submit" class="bouton bouton-secondaire bouton-large">Créer le lien du client</button>
                </form>
            @endif
            <form method="post" action="{{ route('factures.relances', $facture) }}">
                @csrf
                <input type="hidden" name="relances_auto" value="0">
                <label class="case">
                    <input type="checkbox" name="relances_auto" value="1" @checked($facture->relances_auto) data-envoi-auto>
                    <span>Relances automatiques par email après l'échéance (3 au maximum, tous les 7 jours)</span>
                </label>
                <button type="submit" class="bouton bouton-secondaire">Enregistrer ce choix</button>
            </form>
            @if ($facture->relances)
                <p class="aide">{{ $facture->relances }} relance(s) automatique(s), la dernière le {{ $facture->derniere_relance_at?->format('d/m/Y') }}.</p>
            @endif
        </section>

        <details class="carte">
            <summary><strong>Corriger par un avoir</strong></summary>
            <form method="post" action="{{ route('factures.avoir', $facture) }}" novalidate>
                @csrf
                <fieldset class="segments segments-2">
                    <legend>Avoir</legend>
                    <label><input type="radio" name="mode" value="total" checked><span>Total</span></label>
                    <label><input type="radio" name="mode" value="partiel"><span>Partiel</span></label>
                </fieldset>
                <x-champ nom="montant" libelle="Montant TTC (avoir partiel)" inputmode="decimal" />
                <x-champ nom="motif" libelle="Motif" aide="Par exemple : erreur de quantité, geste commercial." />
                <button type="submit" class="bouton bouton-secondaire bouton-large">Préparer l'avoir</button>
            </form>
        </details>
    @endif

    @if ($facture->avoirs->isNotEmpty())
        <section class="carte">
            <h2>Avoirs</h2>
            <ul class="liste">
                @foreach ($facture->avoirs as $avoir)
                    <li><a class="liste-lien" href="{{ route('factures.show', $avoir) }}"><span class="libelle">{{ $avoir->numero }} · {{ \App\Support\Montant::formater($avoir->total_ttc) }}</span><x-icone nom="fleche" /></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($emails->isNotEmpty())
        <section class="carte">
            <h2>Emails envoyés</h2>
            <ul class="liste">
                @foreach ($emails as $email)
                    <li class="ligne"><div>{{ $email->sujet }}<small>{{ $email->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }} · {{ $email->destinataire }}{{ $email->automatique ? ' · automatique' : '' }}{{ $email->statut === 'erreur' ? ' · échec' : '' }}</small></div></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
