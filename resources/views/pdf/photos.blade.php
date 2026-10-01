<html>
<head>@include('pdf._styles', ['couleur' => (string) reglage('apparence.couleur_principale')])</head>
<body>
<h2>{{ $titre }}</h2>
<table>
    @foreach ($photos->chunk(2) as $ligne)
        <tr>
            @foreach ($ligne as $photo)
                <td width="50%" style="padding: 4px; vertical-align: top; text-align: center;">
                    <img src="{{ $photo->cheminComplet() }}" style="max-width: 85mm; max-height: 68mm;">
                    <div class="petit"><strong>{{ $photo->libelleMoment() }}</strong>{{ $photo->legende ? ' — '.$photo->legende : '' }}</div>
                </td>
            @endforeach
            @if ($ligne->count() === 1)
                <td width="50%"></td>
            @endif
        </tr>
    @endforeach
</table>
</body>
</html>
