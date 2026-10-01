@props(['nom', 'libelle', 'options', 'valeur' => null, 'vide' => '— Choisir —', 'aide' => null])
@php
    $id = $attributes->get('id', $nom);
    $actuel = old($nom, $valeur);
@endphp
<div class="champ">
    <label for="{{ $id }}">{{ $libelle }}</label>
    <select id="{{ $id }}" name="{{ $nom }}" {{ $attributes->except('id') }}
        @if ($aide || $errors->has($nom)) aria-describedby="{{ trim(($aide ? $id.'-aide ' : '').($errors->has($nom) ? $id.'-erreur' : '')) }}" @endif
        @error($nom) aria-invalid="true" @enderror>
        @if ($vide !== false)
            <option value="">{{ $vide }}</option>
        @endif
        @foreach ($options as $cle => $texte)
            <option value="{{ is_int($cle) ? $texte : $cle }}" @selected((string) $actuel === (string) (is_int($cle) ? $texte : $cle))>{{ $texte }}</option>
        @endforeach
    </select>
    @if ($aide)
        <p class="aide" id="{{ $id }}-aide">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="erreur-champ" id="{{ $id }}-erreur">{{ $message }}</p>
    @enderror
</div>
