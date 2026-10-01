@props(['nom' => 'password', 'id' => 'password', 'libelle' => 'Mot de passe', 'autocomplete' => 'current-password', 'aide' => null])
<div class="champ">
    <label for="{{ $id }}">{{ $libelle }}</label>
    <div class="mot-de-passe">
        <input type="password" id="{{ $id }}" name="{{ $nom }}" required autocomplete="{{ $autocomplete }}"
               @if ($aide || $errors->has($nom)) aria-describedby="{{ trim(($aide ? $id.'-aide ' : '').($errors->has($nom) ? $id.'-erreur' : '')) }}" @endif
               @error($nom) aria-invalid="true" @enderror>
        <button type="button" class="bouton-afficher" data-afficher-mot-de-passe aria-controls="{{ $id }}" aria-pressed="false" hidden>Afficher</button>
    </div>
    @if ($aide)
        <p class="aide" id="{{ $id }}-aide">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="erreur-champ" id="{{ $id }}-erreur">{{ $message }}</p>
    @enderror
</div>
