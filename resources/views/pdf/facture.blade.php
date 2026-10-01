@php
    $r = fn ($c) => (string) reglage($c);
    $client = $facture->client;
    $chantier = $facture->chantier;
    $particulier = ! $client->estProfessionnel();
    $plusieursTaux = count($detail['tva']) > 1;
    $titre = $facture->estAvoir() ? 'AVOIR' : mb_strtoupper($facture->libelleType());
@endphp
<html>
<head>@include('pdf._styles', ['couleur' => $couleur])</head>
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
        </td>
        <td width="44%" class="droite">
            <h1 style="font-size: 16pt;">{{ $titre }}</h1>
            <table class="infos">
                <tr><td>N°</td><td class="droite"><strong>{{ $facture->numero ?? 'Brouillon (non émise)' }}</strong></td></tr>
                <tr><td>Date</td><td class="droite">{{ ($facture->date_facture ?? now())->format('d/m/Y') }}</td></tr>
                @if (! $facture->estAvoir())
                    <tr><td>Date de la prestation</td><td class="droite">{{ ($facture->date_prestation ?? $facture->date_facture ?? now())->format('d/m/Y') }}</td></tr>
                    <tr><td>Échéance</td><td class="droite"><strong>{{ ($facture->date_echeance ?? now()->addDays($facture->delai_paiement_jours))->format('d/m/Y') }}</strong></td></tr>
                @endif
                @if ($facture->devis?->numero)
                    <tr><td>Devis</td><td class="droite">{{ $facture->devis->numero }}</td></tr>
                @endif
                @if ($facture->origine)
                    <tr><td>Facture d'origine</td><td class="droite">{{ $facture->origine->numero }}</td></tr>
                @endif
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
                    <br>SIREN {{ substr($client->siret, 0, 9) }}
                @endif
                @if ($client->tva_intracom)
                    <br>N° TVA : {{ $client->tva_intracom }}
                @endif
            </td></tr></table>
        </td>
    </tr>
</table>

<table class="infos" style="margin-top: 8px;">
    <tr>
        <td><strong>Adresse de réalisation des travaux :</strong> {{ $chantier?->adresseComplete() ?: ($client->adresseComplete() ?: 'à l\'adresse du client') }}</td>
    </tr>
    <tr><td>Catégorie de l'opération : prestation de services</td></tr>
</table>
@if ($facture->objet)
    <p><strong>Objet :</strong> {{ $facture->objet }}</p>
@endif
@if ($facture->estAvoir() && $facture->motif)
    <p><strong>Motif de l'avoir :</strong> {{ $facture->motif }}</p>
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
        @foreach ($facture->lignes as $i => $ligne)
            @if ($ligne->type === 'section')
                <tr class="section"><td colspan="{{ $plusieursTaux ? 5 : 4 }}">{{ $ligne->designation }}</td><td class="droite">{{ \App\Support\Montant::formater($detail['sections'][$i] ?? 0) }}</td></tr>
            @elseif ($ligne->type === 'texte')
                <tr class="texte"><td colspan="{{ $plusieursTaux ? 6 : 5 }}">{!! nl2br(e($ligne->designation)) !!}</td></tr>
            @else
                <tr>
                    <td>
                        {{ $ligne->designation }}
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
    @if ($facture->total_remise)
        <tr><td width="55%"></td><td>Total brut HT</td><td class="droite">{{ \App\Support\Montant::formater($detail['total_brut_ht']) }}</td></tr>
        <tr><td></td><td>Remise</td><td class="droite">-{{ \App\Support\Montant::formater($facture->total_remise) }}</td></tr>
    @endif
    <tr><td width="55%"></td><td>Total HT</td><td class="droite">{{ \App\Support\Montant::formater($facture->total_ht) }}</td></tr>
    @if ($franchise)
        <tr><td></td><td colspan="2">TVA non applicable, art. 293 B du CGI</td></tr>
    @else
        @foreach ($detail['tva'] as $taux => $tva)
            <tr><td></td><td>TVA {{ \App\Support\Tva::formater($taux) }} sur {{ \App\Support\Montant::formater($tva['base']) }}</td><td class="droite">{{ \App\Support\Montant::formater($tva['montant']) }}</td></tr>
        @endforeach
    @endif
    <tr><td></td><td class="total">{{ $facture->estAvoir() ? 'Montant de l\'avoir' : ($franchise ? 'Total à payer' : 'Total TTC') }}</td><td class="droite total">{{ \App\Support\Montant::formater($facture->total_ttc) }}</td></tr>
</table>

<div class="mentions">
    @if (! $facture->estAvoir())
        <h2>Paiement</h2>
        <p>
            À régler au plus tard le {{ ($facture->date_echeance ?? now()->addDays($facture->delai_paiement_jours))->format('d/m/Y') }}.
            @if ($r('documents.iban'))
                Virement : IBAN {{ \App\Rules\Iban::formater($r('documents.iban')) }} — BIC {{ $r('documents.bic') }} — référence {{ $facture->numero ?? '' }}.
            @endif
            Pas d'escompte pour paiement anticipé.
            En cas de retard, pénalités au taux d'intérêt de la BCE majoré de 10 points{{ $particulier ? '' : ', et indemnité forfaitaire pour frais de recouvrement de 40 € (art. L. 441-10 du Code de commerce)' }}.
        </p>
    @else
        <p>Cet avoir vient en déduction de la facture {{ $facture->origine?->numero }}.</p>
    @endif

    @if ($r('assurance.assureur'))
        @php
            $assurance = $r('assurance.assureur').', contrat n° '.$r('assurance.numero_contrat');
            if ($r('assurance.zone')) {
                $assurance .= ' — couverture géographique : '.$r('assurance.zone');
            }
        @endphp
        <h2>Assurance décennale</h2>
        <p>{{ $assurance }}.</p>
    @endif

    @if ($particulier && $r('identite.mediateur_nom'))
        <h2>Médiation de la consommation</h2>
        <p>En cas de litige, vous pouvez recourir gratuitement au médiateur : {{ $r('identite.mediateur_nom') }}{{ $r('identite.mediateur_site') ? ' — '.$r('identite.mediateur_site') : '' }}.</p>
    @endif
</div>

</body>
</html>
