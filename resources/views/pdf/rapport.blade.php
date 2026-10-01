@php
    $r = fn ($c) => (string) reglage($c);
    $client = $rapport->client;
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
            {{ $r('identite.adresse') }}<br>
            {{ $r('identite.code_postal') }} {{ $r('identite.ville') }}<br>
            Tél. {{ $r('identite.telephone') }} · {{ $r('identite.email') }}
        </td>
        <td width="44%" class="droite">
            <h1 style="font-size: 15pt;">RAPPORT D'INTERVENTION</h1>
            <p>Date : <strong>{{ $rapport->date_intervention->format('d/m/Y') }}</strong></p>
        </td>
    </tr>
</table>

<table style="margin-top: 8px;">
    <tr>
        <td width="50%" class="client">
            <strong>{{ $client->nomComplet() }}</strong><br>
            {{ $client->adresse }}<br>{{ $client->code_postal }} {{ $client->ville }}
        </td>
        <td width="50%" style="padding-left: 8px; vertical-align: top;">
            @if ($rapport->chantier)
                <strong>Adresse des travaux</strong><br>{{ $rapport->chantier->adresseComplete() }}
            @endif
        </td>
    </tr>
</table>

<h2>{{ $rapport->titre }}</h2>
@foreach (['travaux' => 'Travaux réalisés', 'constats' => 'Constat', 'conseils' => 'Préconisations'] as $champ => $titre)
    @if (trim((string) $rapport->{$champ}) !== '')
        <h2>{{ $titre }}</h2>
        <p>{!! nl2br(e($rapport->{$champ})) !!}</p>
    @endif
@endforeach

</body>
</html>
