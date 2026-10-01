{{-- Une ligne de l'éditeur. Variables : $i (index ou __I__), $ligne (tableau), $unites, $tauxOptions, $franchise --}}
@php
    $type = $ligne['type'] ?? 'ligne';
@endphp
<li class="ligne-devis" data-type="{{ $type }}">
    <input type="hidden" name="lignes[{{ $i }}][type]" value="{{ $type }}">
    <input type="hidden" name="lignes[{{ $i }}][prestation_id]" value="{{ $ligne['prestation_id'] ?? '' }}" data-champ="prestation_id">

    <div class="ligne-entete">
        <span class="ligne-type">{{ ['ligne' => 'Ligne', 'section' => 'Section', 'texte' => 'Texte'][$type] }}</span>
        <span class="ligne-boutons">
            <button type="button" class="bouton-icone" data-action="monter" aria-label="Monter">↑</button>
            <button type="button" class="bouton-icone" data-action="descendre" aria-label="Descendre">↓</button>
            <button type="button" class="bouton-icone texte-danger" data-action="supprimer" aria-label="Supprimer la ligne">✕</button>
        </span>
    </div>

    @if ($type === 'texte')
        <label class="visuellement-cache" for="l{{ $i }}-designation">Texte</label>
        <textarea id="l{{ $i }}-designation" name="lignes[{{ $i }}][designation]" rows="2" placeholder="Texte libre (remarque, condition…)">{{ $ligne['designation'] ?? '' }}</textarea>
    @else
        <div class="champ champ-compact">
            <label for="l{{ $i }}-designation">{{ $type === 'section' ? 'Titre de la section' : 'Désignation' }}</label>
            <input type="text" id="l{{ $i }}-designation" name="lignes[{{ $i }}][designation]" value="{{ $ligne['designation'] ?? '' }}" data-champ="designation" autocomplete="off">
        </div>
    @endif

    @if ($type === 'ligne')
        <div class="champ champ-compact">
            <label for="l{{ $i }}-description">Détail (facultatif)</label>
            <textarea id="l{{ $i }}-description" name="lignes[{{ $i }}][description]" rows="1" data-champ="description">{{ $ligne['description'] ?? '' }}</textarea>
        </div>
        <div class="grille-ligne">
            <div class="champ champ-compact">
                <label for="l{{ $i }}-quantite">Quantité</label>
                <input type="text" id="l{{ $i }}-quantite" name="lignes[{{ $i }}][quantite]" value="{{ $ligne['quantite_texte'] ?? '1' }}" inputmode="decimal" data-champ="quantite" autocomplete="off">
            </div>
            <div class="champ champ-compact">
                <label for="l{{ $i }}-unite">Unité</label>
                <select id="l{{ $i }}-unite" name="lignes[{{ $i }}][unite]" data-champ="unite">
                    @foreach (array_unique(array_merge($unites, [$ligne['unite'] ?? 'u'])) as $unite)
                        <option value="{{ $unite }}" @selected(($ligne['unite'] ?? 'u') === $unite)>{{ $unite }}</option>
                    @endforeach
                </select>
            </div>
            <div class="champ champ-compact">
                <label for="l{{ $i }}-prix">Prix unit. HT</label>
                <input type="text" id="l{{ $i }}-prix" name="lignes[{{ $i }}][prix]" value="{{ $ligne['prix_texte'] ?? '' }}" inputmode="decimal" data-champ="prix" autocomplete="off">
            </div>
            @unless ($franchise)
                <div class="champ champ-compact">
                    <label for="l{{ $i }}-tva">TVA</label>
                    <select id="l{{ $i }}-tva" name="lignes[{{ $i }}][taux_tva]" data-champ="taux_tva">
                        @foreach ($tauxOptions as $taux => $libelle)
                            <option value="{{ $taux }}" @selected((int) ($ligne['taux_tva'] ?? reglage('tva.taux_defaut')) === (int) $taux)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
        </div>
        <div class="ligne-pied">
            @if (empty($sansOption))
                <label class="case case-compacte">
                    <input type="checkbox" name="lignes[{{ $i }}][option]" value="1" data-champ="option" @checked(! empty($ligne['option']))>
                    <span>En option</span>
                </label>
            @else
                <span></span>
            @endif
            <output class="ligne-total" data-total>{{ $ligne['total_texte'] ?? '' }}</output>
        </div>
    @endif
</li>
