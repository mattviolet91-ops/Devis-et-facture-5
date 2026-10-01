{{-- Un champ de réglage, selon son type. Variables : $champ, $valeur --}}
@php
    $nom = \App\Support\SectionsReglages::nomChamp($champ['cle']);
    $id = 'champ-'.$nom;
    $erreur = $errors->first($nom);
    $decrit = trim((! empty($champ['aide']) ? $id.'-aide ' : '').($erreur ? $id.'-erreur' : ''));
    $verrouille = ! empty($champ['verrouille']);
    $valeurAffichee = old($nom, $valeur);
@endphp

@if ($champ['type'] === 'case')
    <div class="champ">
        <input type="hidden" name="{{ $nom }}" value="0">
        <label class="case">
            <input type="checkbox" id="{{ $id }}" name="{{ $nom }}" value="1" @checked($valeurAffichee) @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
            <span>{{ $champ['libelle'] }}</span>
        </label>
@else
    <div class="champ">
        <label for="{{ $id }}">
            {{ $champ['libelle'] }}
            @if (! empty($champ['obligatoire']) && ! $verrouille) <span class="obligatoire">(obligatoire)</span> @endif
        </label>

        @switch($champ['type'])
            @case('textarea')
                <textarea id="{{ $id }}" name="{{ $nom }}" rows="{{ ! empty($champ['grand']) ? 14 : 4 }}"
                    @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>{{ $valeurAffichee }}</textarea>
                @break

            @case('lignes')
                <textarea id="{{ $id }}" name="{{ $nom }}" rows="6"
                    @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>{{ $valeurAffichee }}</textarea>
                @break

            @case('select')
            @case('taux_defaut')
                @php
                    $options = $champ['type'] === 'taux_defaut' ? \App\Support\Tva::options() : $champ['options'];
                @endphp
                <select id="{{ $id }}" name="{{ $nom }}" @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
                    @if (empty($champ['obligatoire']))
                        <option value="">— Choisir —</option>
                    @endif
                    @foreach ($options as $valeurOption => $libelleOption)
                        <option value="{{ $valeurOption }}" @selected((string) $valeurAffichee === (string) $valeurOption)>{{ $libelleOption }}</option>
                    @endforeach
                </select>
                @break

            @case('couleur')
                <input type="color" id="{{ $id }}" name="{{ $nom }}" value="{{ $valeurAffichee }}" class="champ-couleur" data-apercu="{{ $champ['cle'] }}"
                    @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
                @break

            @case('fichier')
                @if ($valeur)
                    <p class="fichier-actuel">
                        @if ($champ['cle'] === 'apparence.logo')
                            <img src="{{ route('fichiers.logo') }}?v={{ md5((string) $valeur) }}" alt="Logo actuel" class="apercu-logo">
                        @elseif ($champ['cle'] === 'apparence.icone')
                            <img src="{{ route('fichiers.icone', 192) }}?v={{ md5((string) $valeur) }}" alt="Icône actuelle" class="apercu-icone">
                        @elseif ($champ['cle'] === 'assurance.attestation')
                            <a href="{{ route('reglages.attestation') }}">Voir l'attestation enregistrée</a>
                        @endif
                    </p>
                    <label class="case">
                        <input type="checkbox" name="{{ $nom }}__effacer" value="1">
                        <span>Retirer ce fichier</span>
                    </label>
                @endif
                <input type="file" id="{{ $id }}" name="{{ $nom }}" accept="{{ $champ['accept'] ?? '' }}"
                    @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
                @break

            @case('secret')
                @if ($valeur)
                    <p class="badge badge-succes">Enregistré (caché)</p>
                @endif
                <div class="mot-de-passe">
                    <input type="password" id="{{ $id }}" name="{{ $nom }}" autocomplete="new-password" value=""
                        placeholder="{{ $valeur ? 'Laisser vide pour ne pas changer' : '' }}"
                        @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
                    <button type="button" class="bouton-afficher" data-afficher-mot-de-passe aria-controls="{{ $id }}" aria-pressed="false" hidden>Afficher</button>
                </div>
                @if ($valeur)
                    <label class="case">
                        <input type="checkbox" name="{{ $nom }}__effacer" value="1">
                        <span>Effacer le mot de passe enregistré</span>
                    </label>
                @endif
                @break

            @default
                @php
                    $type = match ($champ['type']) {
                        'email' => 'email', 'tel' => 'tel', 'url' => 'url', 'date' => 'date', 'entier' => 'number', default => 'text',
                    };
                @endphp
                <div @class(['avec-suffixe' => ! empty($champ['suffixe'])])>
                    <input type="{{ $type }}" id="{{ $id }}" name="{{ $nom }}" value="{{ $valeurAffichee }}"
                        @if ($champ['type'] === 'entier') inputmode="numeric" step="1" @endif
                        @if (! empty($champ['inputmode'])) inputmode="{{ $champ['inputmode'] }}" @endif
                        @if (! empty($champ['autocomplete'])) autocomplete="{{ $champ['autocomplete'] }}" @endif
                        @if ($verrouille) readonly aria-readonly="true" @endif
                        @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
                    @if (! empty($champ['suffixe']))
                        <span class="suffixe">{{ $champ['suffixe'] }}</span>
                    @endif
                </div>
        @endswitch
@endif

        @if (! empty($champ['aide']))
            <p class="aide" id="{{ $id }}-aide">{{ $champ['aide'] }}</p>
        @endif
        @if ($erreur)
            <p class="erreur-champ" id="{{ $id }}-erreur">{{ $erreur }}</p>
        @endif
    </div>
