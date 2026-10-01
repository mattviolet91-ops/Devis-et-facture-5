@props(['nom', 'libelle', 'valeur' => null, 'lignes' => 4, 'aide' => null])
@php($id = $attributes->get('id', $nom))
<div class="champ">
    <label for="{{ $id }}">{{ $libelle }}</label>
    <textarea id="{{ $id }}" name="{{ $nom }}" rows="{{ $lignes }}" {{ $attributes->except('id') }}
        @if ($aide || $errors->has($nom)) aria-describedby="{{ trim(($aide ? $id.'-aide ' : '').($errors->has($nom) ? $id.'-erreur' : '')) }}" @endif
        @error($nom) aria-invalid="true" @enderror>{{ old($nom, $valeur) }}</textarea>
    @if ($aide)
        <p class="aide" id="{{ $id }}-aide">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="erreur-champ" id="{{ $id }}-erreur">{{ $message }}</p>
    @enderror
</div>
