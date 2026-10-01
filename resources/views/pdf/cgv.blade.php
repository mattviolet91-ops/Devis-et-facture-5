<html>
<head>@include('pdf._styles')</head>
<body>
    <h2>Conditions générales de vente</h2>
    <div class="mentions" style="font-size: 7.8pt;">{!! nl2br(e((string) reglage('documents.cgv'))) !!}</div>
</body>
</html>
