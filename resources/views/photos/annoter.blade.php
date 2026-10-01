@extends('layouts.app')

@section('titre', 'Dessiner sur la photo')
@section('parent', route('photos.index', $photo->client_id))

@push('scripts')
    <script src="{{ asset('js/annotation.js') }}?v={{ filemtime(public_path('js/annotation.js')) }}" defer></script>
@endpush

@section('contenu')
    <p class="texte-doux">Entourez ou fléchez ce qu'il faut montrer au client. Dessinez avec le doigt.</p>

    @error('image')
        <div class="message message-erreur" role="alert">{{ $message }}</div>
    @enderror

    <div class="zone-annotation">
        <canvas id="toile-annotation" data-image="{{ route('photos.show', $photo) }}" width="{{ $photo->largeur }}" height="{{ $photo->hauteur }}"
                aria-label="{{ $photo->texteAlternatif() }}, zone de dessin" role="img"></canvas>
    </div>

    <fieldset class="segments">
        <legend>Couleur</legend>
        <label><input type="radio" name="couleur" value="#e00000" checked><span>Rouge</span></label>
        <label><input type="radio" name="couleur" value="#ffd400"><span>Jaune</span></label>
        <label><input type="radio" name="couleur" value="#ffffff"><span>Blanc</span></label>
    </fieldset>

    <div class="actions-ligne">
        <button type="button" class="bouton bouton-secondaire" data-annuler-trait>Annuler le dernier trait</button>
        <button type="button" class="bouton bouton-secondaire" data-tout-effacer>Tout effacer</button>
    </div>

    <form method="post" action="{{ route('photos.annoter', $photo) }}" enctype="multipart/form-data" id="formulaire-annotation">
        @csrf
        <input type="file" name="image" accept="image/jpeg" hidden>
        <button type="submit" class="bouton bouton-large">Enregistrer le dessin</button>
    </form>
    <p class="aide">La photo d'origine est gardée : vous pourrez la remettre.</p>
@endsection
