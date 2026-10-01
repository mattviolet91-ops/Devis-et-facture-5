<html>
<head>@include('pdf._styles')</head>
<body>
    <div style="text-align: center; margin-top: 50mm;">
        @if ($logo)
            <img src="{{ $logo }}" style="max-height: 40mm; max-width: 120mm;"><br><br>
        @endif
        <h1 style="font-size: 28pt;">{{ reglage('identite.nom_commercial') }}</h1>
        <p style="font-size: 16pt; margin-top: 20mm;">Devis {{ $devis->numero ?? '(brouillon)' }}</p>
        @if ($devis->objet)
            <p style="font-size: 13pt;">{{ $devis->objet }}</p>
        @endif
        <p style="font-size: 12pt; margin-top: 15mm;">Pour {{ $devis->client->nomComplet() }}</p>
        <p style="font-size: 11pt;">{{ $devis->chantier?->adresseComplete() ?: $devis->client->adresseComplete() }}</p>
        <p style="font-size: 10pt; color: #555; margin-top: 30mm;">{{ reglage('identite.adresse') }} · {{ reglage('identite.code_postal') }} {{ reglage('identite.ville') }} · {{ reglage('identite.telephone') }}</p>
    </div>
</body>
</html>
