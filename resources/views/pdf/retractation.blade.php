<html>
<head>@include('pdf._styles')</head>
<body>
    <h2>Formulaire de rétractation</h2>
    <p class="petit">(Veuillez compléter et renvoyer le présent formulaire uniquement si vous souhaitez vous rétracter du contrat.)</p>
    <div class="encadre">
        <p>À l'attention de {{ reglage('identite.nom_commercial') }}, {{ reglage('identite.adresse') }}, {{ reglage('identite.code_postal') }} {{ reglage('identite.ville') }}, {{ reglage('identite.email') }} :</p>
        <p>Je vous notifie par la présente ma rétractation du contrat portant sur la prestation de services ci-dessous :</p>
        <p>Devis n° {{ $devis->numero ?? '…………' }} — {{ $devis->objet ?? '' }}</p>
        <p>Commandé le : ……………………………………</p>
        <p>Nom du client : ……………………………………………………………</p>
        <p>Adresse du client : ………………………………………………………………………………………………</p>
        <p>Signature du client (uniquement en cas de notification sur papier) :</p>
        <br><br>
        <p>Date : ……………………………</p>
    </div>
    <p class="petit">Délai : 14 jours à compter de la signature du devis. Si vous avez demandé que les travaux commencent avant la fin de ce délai, vous paierez la part des travaux déjà réalisés.</p>
</body>
</html>
