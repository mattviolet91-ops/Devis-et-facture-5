@extends('layouts.app')

@section('titre', 'Importer des clients')
@section('parent', route('clients.index'))

@section('contenu')
    <div class="carte">
        <h2>Comment faire ?</h2>
        <ol class="etapes">
            <li>Dans Excel ou Google Sheets, ouvrez votre liste de clients.</li>
            <li>La première ligne doit contenir les titres des colonnes : <strong>Nom</strong>, Prénom, Société, Téléphone, Email, Adresse, Code postal, Ville…</li>
            <li>Enregistrez au format <strong>CSV</strong> (Fichier → Enregistrer sous → CSV).</li>
            <li>Envoyez le fichier ci-dessous : vous verrez un aperçu <strong>avant</strong> tout import.</li>
        </ol>
        <p class="aide">Les clients déjà présents (même téléphone ou email) sont signalés et ne sont pas importés en double.</p>
    </div>

    <form method="post" action="{{ route('clients.import') }}" enctype="multipart/form-data" class="carte" novalidate>
        @csrf
        <div class="champ">
            <label for="fichier">Fichier CSV</label>
            <input type="file" id="fichier" name="fichier" accept=".csv,text/csv,text/plain"
                @error('fichier') aria-invalid="true" aria-describedby="fichier-erreur" @enderror>
            @error('fichier')
                <p class="erreur-champ" id="fichier-erreur">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="bouton bouton-large">Voir l'aperçu</button>
    </form>
@endsection
