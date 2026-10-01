@php
    $r = fn ($c) => (string) reglage($c);
    $client = $devis->client;
    $chantier = $devis->chantier;
    $particulier = ! $client->estProfessionnel();
    $plusieursTaux = count($detail['tva']) > 1;
@endphp
<html>
<head>@include('pdf._styles')</head>
<body>

<table class="entete">
    <tr>
        <td width="56%" class="societe">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 20mm; max-width: 55mm;"><br>
            @endif
            <strong>{{ $r('identite.nom_commercial') }}</strong><br>
            {{ trim($r('identite.forme_juridique').($r('identite.capital') ? ' au capital de '.$r('identite.capital') : '')) }}<br>
            {{ $r('identite.adresse') }}<br>
            {{ $r('identite.code_postal') }} {{ $r('identite.ville') }}<br>
            Tél. {{ $r('identite.telephone') }} · {{ $r('identite.email') }}
            @if ($r('identite.site'))
                <br>{{ $r('identite.site') }}
            @endif
            <br>SIRET {{ $r('identite.siret') }}
            @if ($r('identite.code_ape'))
                · APE {{ $r('identite.code_ape') }}
            @endif
            @if ($r('identite.rcs_rm'))
                <br>{{ $r('identite.rcs_rm') }}
            @endif
            @if (! $franchise && $r('identite.tva_intracom'))
                <br>N° TVA intracommunautaire : {{ $r('identite.tva_intracom') }}
            @endif
            @if ($r('identite.agrements'))
                <br>{{ str_replace("\n", ' · ', $r('identite.agrements')) }}
            @endif
        </td>
        <td width="44%" class="droite">
            <h1>DEVIS</h1>
            <table class="infos">
                <tr><td>N°</td><td class="droite"><strong>{{ $devis->numero ?? 'Brouillon (non envoyé)' }}</strong></td></tr>
                <tr><td>Date</td><td class="droite">{{ ($devis->date_devis ?? now())->format('d/m/Y') }}</td></tr>
                <tr><td>Valable jusqu'au</td><td class="droite">{{ ($devis->dateValidite() ?? now()->addDays($devis->validite_jours))->format('d/m/Y') }}</td></tr>
            </table>
            <br>
            <table><tr><td class="client" style="text-align: left;">
                <strong>{{ $client->nomComplet() }}</strong><br>
                @if ($client->contact())
                    À l'attention de {{ $client->contact() }}<br>
                @endif
                {{ $client->adresse }}<br>
                {{ $client->code_postal }} {{ $client->ville }}
                @if ($client->siret)
                    <br>SIRET {{ $client->siret }}
                @endif
            </td></tr></table>
        </td>
    </tr>
</table>

<table class="infos" style="margin-top: 8px;">
    <tr>
        <td width="56%">
            <strong>Adresse des travaux :</strong>
            {{ $chantier?->adresseComplete() ?: ($client->adresseComplete() ?: 'à préciser') }}
        </td>
        <td width="44%" class="droite">
            @if ($devis->date_debut_travaux)
                Début prévu : {{ $devis->date_debut_travaux->format('d/m/Y') }}
            @else
                Début : à convenir ensemble
            @endif
            @if ($devis->duree_travaux)
                · Durée estimée : {{ $devis->duree_travaux }}
            @endif
        </td>
    </tr>
</table>
@if ($devis->objet)
    <p><strong>Objet :</strong> {{ $devis->objet }}</p>
@endif

<table class="lignes" autosize="1">
    <thead>
        <tr>
            <th width="{{ $plusieursTaux ? 44 : 50 }}%">Désignation</th>
            <th class="droite">Qté</th>
            <th>Unité</th>
            <th class="droite">Prix unit. HT</th>
            @if ($plusieursTaux)
                <th class="droite">TVA</th>
            @endif
            <th class="droite">Total HT</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($devis->lignes as $i => $ligne)
            @if ($ligne->type === 'section')
                <tr class="section"><td colspan="{{ $plusieursTaux ? 5 : 4 }}">{{ $ligne->designation }}</td><td class="droite">{{ \App\Support\Montant::formater($detail['sections'][$i] ?? 0) }}</td></tr>
            @elseif ($ligne->type === 'texte')
                <tr class="texte"><td colspan="{{ $plusieursTaux ? 6 : 5 }}">{!! nl2br(e($ligne->designation)) !!}</td></tr>
            @else
                <tr @class(['option' => $ligne->option])>
                    <td>
                                {{ $ligne->designation }}{!! $ligne->option ? ' <strong>(option)</strong>' : '' !!}
                        @if ($ligne->description)
                            <br><span class="detail">{!! nl2br(e($ligne->description)) !!}</span>
                        @endif
                    </td>
                    <td class="droite">{{ $ligne->quantiteAffichee() }}</td>
                    <td>{{ $ligne->unite }}</td>
                    <td class="droite">{{ $ligne->prixAffiche() }}</td>
                    @if ($plusieursTaux)
                        <td class="droite">{{ \App\Support\Tva::formater($ligne->taux_tva) }}</td>
                    @endif
                    <td class="droite">{{ $ligne->totalAffiche() }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

<table class="totaux" style="margin-top: 6px;">
    @if ($devis->total_remise)
        <tr><td width="55%"></td><td>Total brut HT</td><td class="droite">{{ \App\Support\Montant::formater($detail['total_brut_ht']) }}</td></tr>
        <tr><td></td><td>Remise</td><td class="droite">-{{ \App\Support\Montant::formater($devis->total_remise) }}</td></tr>
    @endif
    <tr><td width="55%"></td><td>Total HT</td><td class="droite">{{ \App\Support\Montant::formater($devis->total_ht) }}</td></tr>
    @if ($franchise)
        <tr><td></td><td colspan="2">TVA non applicable, art. 293 B du CGI</td></tr>
        <tr><td></td><td class="total">Total à payer</td><td class="droite total">{{ \App\Support\Montant::formater($devis->total_ttc) }}</td></tr>
    @else
        @foreach ($detail['tva'] as $taux => $tva)
            <tr><td></td><td>TVA {{ \App\Support\Tva::formater($taux) }} sur {{ \App\Support\Montant::formater($tva['base']) }}</td><td class="droite">{{ \App\Support\Montant::formater($tva['montant']) }}</td></tr>
        @endforeach
        <tr><td></td><td class="total">Total TTC</td><td class="droite total">{{ \App\Support\Montant::formater($devis->total_ttc) }}</td></tr>
    @endif
    @if ($devis->acompte_pourcentage > 0)
        <tr><td></td><td>Acompte à la commande ({{ $devis->acompte_pourcentage }} %)</td><td class="droite">{{ \App\Support\Montant::formater(intdiv($devis->total_ttc * $devis->acompte_pourcentage + 50, 100)) }}</td></tr>
    @endif
    @if ($devis->total_options_ht)
        <tr><td></td><td class="petit">Options (non comprises dans le total)</td><td class="droite petit">{{ \App\Support\Montant::formater($devis->total_options_ht) }} HT</td></tr>
    @endif
</table>

<div class="mentions">
    @if ($r('documents.mention_dechets') || $devis->dechets_estimation)
        <h2>Gestion des déchets</h2>
        @if ($devis->dechets_estimation)
            <p><strong>Estimation :</strong> {{ $devis->dechets_estimation }}</p>
        @endif
        <p>{{ $r('documents.mention_dechets') }}</p>
    @endif

    @if ($devis->conditions)
        <h2>Conditions particulières</h2>
        <p>{!! nl2br(e($devis->conditions)) !!}</p>
    @endif

    <h2>Conditions de paiement</h2>
    <p>
        @if ($devis->acompte_pourcentage > 0)
            Acompte de {{ $devis->acompte_pourcentage }} % à la commande, solde à réception de la facture.
        @else
            Paiement à réception de la facture.
        @endif
        Règlement sous {{ (int) reglage('documents.delai_paiement_jours') }} jours, par virement, chèque, carte ou espèces dans la limite légale.
        @if ($r('documents.iban'))
            IBAN {{ \App\Rules\Iban::formater($r('documents.iban')) }} — BIC {{ $r('documents.bic') }}.
        @endif
        Pas d'escompte pour paiement anticipé. En cas de retard, pénalités au taux d'intérêt de la BCE majoré de 10 points{{ $particulier ? '' : ' et indemnité forfaitaire pour frais de recouvrement de 40 €' }}.
    </p>
    <p>Devis gratuit. Les travaux non prévus au présent devis feront l'objet d'un accord écrit préalable.</p>

    @if ($r('assurance.assureur'))
        @php
            $assurance = $r('assurance.assureur').', contrat n° '.$r('assurance.numero_contrat');
            if ($r('assurance.date_debut') && $r('assurance.date_fin')) {
                $assurance .= ', valable du '.\Carbon\Carbon::parse($r('assurance.date_debut'))->format('d/m/Y').' au '.\Carbon\Carbon::parse($r('assurance.date_fin'))->format('d/m/Y');
            }
            if ($r('assurance.zone')) {
                $assurance .= ' — couverture géographique : '.$r('assurance.zone');
            }
        @endphp
        <h2>Assurance décennale</h2>
        <p>{{ $assurance }}.</p>
    @endif

    @if ($particulier && $retractation)
        <h2>Droit de rétractation</h2>
        <p>Ce contrat est conclu hors établissement ou à distance : vous disposez d'un délai de 14 jours à compter de sa signature pour vous rétracter, sans avoir à vous justifier, avec le formulaire joint ou toute déclaration claire. Aucun paiement ne peut être exigé avant 7 jours à compter de la signature.</p>
        @if ($devis->urgence)
            <p>Travaux de réparation urgents demandés expressément par le client à son domicile : le droit de rétractation ne s'applique pas pour les travaux strictement nécessaires à l'urgence (art. L. 221-28 du Code de la consommation).</p>
        @endif
    @endif

    @if ($particulier && $r('identite.mediateur_nom'))
        <h2>Médiation de la consommation</h2>
        <p>En cas de litige, après une réclamation écrite restée sans solution, vous pouvez recourir gratuitement au médiateur : {{ $r('identite.mediateur_nom') }}{{ $r('identite.mediateur_site') ? ' — '.$r('identite.mediateur_site') : '' }}.</p>
    @endif

    @if (trim($r('documents.cgv')) !== '')
        <p>Nos conditions générales de vente sont jointes en annexe.</p>
    @endif
</div>

<table style="margin-top: 10px;" autosize="1">
    <tr>
        <td width="48%" class="petit" style="vertical-align: bottom;">
            Devis établi par {{ $r('identite.nom_commercial') }}.
        </td>
        <td width="52%" class="encadre">
            <strong>Bon pour accord</strong><br>
            <span class="petit">Date, nom et signature du client, précédés de la mention manuscrite « Bon pour accord » :</span>
            @if (! empty($signature))
                <br>{{ $signature['nom'] }} — signé le {{ $signature['date'] }}<br>
                <img src="{{ $signature['image'] }}" style="max-height: 22mm; max-width: 70mm;">
                <br><span class="petit">Signature électronique · adresse IP {{ $signature['ip'] }}</span>
            @else
                <br><br><br><br><br>
            @endif
        </td>
    </tr>
</table>

</body>
</html>
