@php
    $choisi = old('metier', reglage('entreprise.metier'));
    $modulesActifs = old('modules', (array) reglage('modules.actifs', []));
@endphp
<fieldset class="choix-metier">
    <legend class="etiquette">Quel est votre métier ? <span class="obligatoire">(obligatoire)</span></legend>
    <p class="aide" id="aide-metier">Il sert à préparer votre catalogue de prestations et vos CGV de départ. Les prix restent à saisir par vous.</p>
    @foreach ($metiers as $cle => $metier)
        <label class="case">
            <input type="radio" name="metier" value="{{ $cle }}" data-modules="{{ implode(',', $metier['modules']) }}" @checked($choisi === $cle) aria-describedby="aide-metier">
            <span>{{ $metier['libelle'] }}</span>
        </label>
    @endforeach
    @error('metier')
        <p class="erreur-champ" role="alert">{{ $message }}</p>
    @enderror
</fieldset>

<fieldset class="choix-metier">
    <legend class="etiquette">Modules utiles</legend>
    <p class="aide">Cochés selon votre métier. Vous pourrez les changer plus tard.</p>
    @foreach (\App\Support\Metiers::MODULES as $cle => $libelle)
        <label class="case">
            <input type="checkbox" name="modules[]" value="{{ $cle }}" data-module @checked(in_array($cle, $modulesActifs, true))>
            <span>{{ $libelle }}</span>
        </label>
    @endforeach
</fieldset>
