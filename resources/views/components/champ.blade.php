@props(['nom', 'libelle', 'type' => 'text', 'valeur' => null, 'aide' => null])
@php($id = $attributes->get('id', $nom))
<div class="champ">
    <label for="{{ $id }}">{{ $libelle }}</label>
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $nom }}" value="{{ old($nom, $valeur) }}"
           {{ $attributes->except('id') }}
           @if ($aide || $errors->has($nom)) aria-describedby="{{ trim(($aide ? $id.'-aide ' : '').($errors->has($nom) ? $id.'-erreur' : '')) }}" @endif
           @error($nom) aria-invalid="true" @enderror>
    @if ($aide)
        <p class="aide" id="{{ $id }}-aide">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="erreur-champ" id="{{ $id }}-erreur">{{ $message }}</p>
    @enderror
</div>
