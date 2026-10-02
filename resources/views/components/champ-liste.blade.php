@props(['nom', 'libelle', 'options', 'valeur' => null, 'vide' => '— Choisir —', 'aide' => null, 'parCle' => null])
@php
    $id = $attributes->get('id', $nom);
    $actuel = old($nom, $valeur);
    // Une simple liste (« Bouche-à-oreille », « Autre »…) envoie le texte ;
    // un tableau clé → libellé (identifiants, taux, codes) envoie la clé.
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $liste = $parCle === null ? array_is_list($options) : ! $parCle;
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
            <option value="{{ $liste ? $texte : $cle }}" @selected((string) $actuel === (string) ($liste ? $texte : $cle))>{{ $texte }}</option>
        @endforeach
    </select>
    @if ($aide)
        <p class="aide" id="{{ $id }}-aide">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="erreur-champ" id="{{ $id }}-erreur">{{ $message }}</p>
    @enderror
</div>
