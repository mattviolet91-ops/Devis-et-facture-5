@php
    $couleur = reglage('apparence.couleur_principale');
    $r = fn ($c) => (string) reglage($c);
@endphp
<html>
<head>
<style>
    body { font-family: dejavusans; font-size: 9.5pt; color: #1b1d21; }
    .pied { font-size: 7.5pt; color: #666; text-align: center; }
    h1 { color: {{ $couleur }}; font-size: 18pt; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    .entete td { vertical-align: top; }
    .lignes th { background: {{ $couleur }}; color: #fff; padding: 6px; text-align: left; font-size: 8.5pt; }
    .lignes td { padding: 6px; border-bottom: 0.3mm solid #ddd; }
    .droite { text-align: right; }
    .totaux td { padding: 4px 6px; }
    .total { font-weight: bold; font-size: 11pt; color: {{ $couleur }}; }
    .cadre { border: 0.3mm solid #bbb; padding: 8px; }
    .mentions { font-size: 7.5pt; color: #444; }
    .exemple { background: #fff3cd; padding: 6px; font-size: 8pt; }
</style>
</head>
<body>
<p class="exemple">Document d'exemple : le client, les quantités et les prix sont fictifs. Il sert seulement à vérifier la présentation.</p>

<table class="entete">
    <tr>
        <td width="55%">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 22mm; max-width: 60mm;"><br>
            @endif
            <strong>{{ $r('identite.nom_commercial') }}</strong>
            @if (reglage('identite.forme_juridique'))
                — {{ $r('identite.forme_juridique') }}
            @endif
            @if (reglage('identite.capital'))
                au capital de {{ $r('identite.capital') }}
            @endif
            <br>
            {{ $r('identite.adresse') }}<br>
            {{ $r('identite.code_postal') }} {{ $r('identite.ville') }}<br>
            Tél. {{ $r('identite.telephone') }} · {{ $r('identite.email') }}<br>
            SIRET {{ $r('identite.siret') }}
            @if (reglage('identite.code_ape'))
                · APE {{ $r('identite.code_ape') }}
            @endif
            @if (reglage('identite.rcs_rm'))
                <br>{{ $r('identite.rcs_rm') }}
            @endif
            @if (! $franchise && reglage('identite.tva_intracom'))
                <br>TVA intracommunautaire {{ $r('identite.tva_intracom') }}
            @endif
        </td>
        <td width="45%" class="droite">
            <h1>DEVIS</h1>
            N° {{ $numero }} (exemple)<br>
            Date : {{ now()->format('d/m/Y') }}<br>
            Valable {{ (int) reglage('documents.validite_devis_jours') }} jours<br><br>
            <div class="cadre" style="text-align: left;">
                <strong>Client exemple</strong><br>
                1 rue Fictive<br>
                00000 Ville-Exemple
            </div>
        </td>
    </tr>
</table>

<p><strong>Adresse des travaux :</strong> identique (exemple)</p>

<table class="lignes">
    <thead>
        <tr><th width="46%">Désignation</th><th class="droite">Qté</th><th>Unité</th><th class="droite">Prix unit. HT</th><th class="droite">Total HT</th></tr>
    </thead>
    <tbody>
        @foreach ($lignes as $ligne)
            <tr>
                <td>{{ $ligne['designation'] }}</td>
                <td class="droite">{{ $ligne['quantite'] }}</td>
                <td>{{ $ligne['unite'] }}</td>
                <td class="droite">{{ $ligne['prix'] }}</td>
                <td class="droite">{{ $ligne['total'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totaux" style="margin-top: 8px;">
    <tr><td width="60%"></td><td>Total HT</td><td class="droite">{{ $totalHt }}</td></tr>
    @if ($franchise)
        <tr><td></td><td colspan="2">TVA non applicable, art. 293 B du CGI</td></tr>
        <tr><td></td><td class="total">Total à payer</td><td class="droite total">{{ $totalHt }}</td></tr>
    @else
        <tr><td></td><td>TVA {{ $taux }}</td><td class="droite">{{ $totalTva }}</td></tr>
        <tr><td></td><td class="total">Total TTC</td><td class="droite total">{{ $totalTtc }}</td></tr>
    @endif
    @if ((int) reglage('documents.acompte_pourcentage') > 0)
        <tr><td></td><td>Acompte à la commande ({{ (int) reglage('documents.acompte_pourcentage') }} %)</td><td class="droite">{{ $acompte }}</td></tr>
    @endif
</table>

@if (reglage('documents.mention_dechets'))
    <p class="mentions"><strong>Déchets :</strong> {{ $r('documents.mention_dechets') }}</p>
@endif

@php
    $mentions = ['Paiement à '.(int) reglage('documents.delai_paiement_jours').' jours.'];
    if (reglage('documents.iban')) {
        $mentions[] = 'Virement : IBAN '.\App\Rules\Iban::formater((string) reglage('documents.iban')).' — BIC '.reglage('documents.bic').'.';
    }
    $mentions[] = 'En cas de retard, pénalités au taux de la BCE majoré de 10 points ; pour les professionnels, indemnité forfaitaire de recouvrement de 40 €.';
    $assurance = '';
    if (reglage('assurance.assureur')) {
        $assurance = 'Assurance décennale : '.reglage('assurance.assureur').', contrat n° '.reglage('assurance.numero_contrat');
        if (reglage('assurance.date_fin')) {
            $assurance .= ', valable jusqu\'au '.\Carbon\Carbon::parse(reglage('assurance.date_fin'))->format('d/m/Y');
        }
        if (reglage('assurance.zone')) {
            $assurance .= ', zone : '.reglage('assurance.zone');
        }
        $assurance .= '.';
    }
    $mediateur = '';
    if (reglage('identite.mediateur_nom')) {
        $mediateur = 'Médiateur de la consommation : '.reglage('identite.mediateur_nom').(reglage('identite.mediateur_site') ? ' — '.reglage('identite.mediateur_site') : '').'.';
    }
@endphp
<p class="mentions">
    {{ implode(' ', $mentions) }}
    @if ($assurance)
        <br>{{ $assurance }}
    @endif
    @if ($mediateur)
        <br>{{ $mediateur }}
    @endif
</p>

<table style="margin-top: 10px;">
    <tr>
        <td width="50%"></td>
        <td class="cadre" width="50%">
            <strong>Bon pour accord</strong><br>
            Date et signature du client, précédées de la mention « Bon pour accord » :<br><br><br><br>
        </td>
    </tr>
</table>
</body>
</html>
