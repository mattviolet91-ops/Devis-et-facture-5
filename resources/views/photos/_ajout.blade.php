<form method="post" action="{{ route('photos.store', $client) }}" enctype="multipart/form-data" class="carte" novalidate>
    @csrf
    <h2>Ajouter des photos</h2>
    @if ($rdv)
        <input type="hidden" name="rendez_vous_id" value="{{ $rdv->id }}">
        <input type="hidden" name="retour" value="planning">
    @endif
    <div class="champ">
        <label for="photos">Photos</label>
        <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
            @error('photos') aria-invalid="true" aria-describedby="photos-erreur" @enderror>
        <p class="aide">Prenez la photo ou choisissez-en plusieurs. Elles sont réduites et la position GPS est retirée.</p>
        @foreach (['photos', 'photos.0', 'photos.1'] as $cle)
            @error($cle)
                <p class="erreur-champ" id="photos-erreur">{{ $message }}</p>
            @enderror
        @endforeach
    </div>
    <fieldset class="segments">
        <legend>Moment</legend>
        @foreach (\App\Models\Photo::MOMENTS as $cle => $libelle)
            <label><input type="radio" name="moment" value="{{ $cle }}" @checked(old('moment', $rdv && $rdv->fait ? 'apres' : 'avant') === $cle)><span>{{ $libelle }}</span></label>
        @endforeach
    </fieldset>
    @if (! $rdv && $chantiers->isNotEmpty())
        <x-champ-liste nom="chantier_id" libelle="Adresse de chantier" :options="$chantiers->mapWithKeys(fn ($c) => [$c->id => $c->titre()])" vide="— Aucune en particulier —" />
    @endif
    <x-champ nom="legende" libelle="Légende (facultatif)" aide="Par exemple : faîtage côté rue." />
    <button type="submit" class="bouton bouton-large">Ajouter</button>
</form>
