@extends('layouts.app')

@section('titre', $devis->reference())
@section('parent', route('devis.show', $devis))

@push('scripts')
    <script src="{{ asset('js/devis.js') }}?v={{ filemtime(public_path('js/devis.js')) }}" defer></script>
@endpush

@php
    $lignesAffichees = old('lignes') ? collect(old('lignes'))->map(fn ($l) => [
        'type' => $l['type'] ?? 'ligne', 'designation' => $l['designation'] ?? '', 'description' => $l['description'] ?? '',
        'quantite_texte' => $l['quantite'] ?? '', 'unite' => $l['unite'] ?? 'u', 'prix_texte' => $l['prix'] ?? '',
        'taux_tva' => $l['taux_tva'] ?? null, 'option' => ! empty($l['option']), 'prestation_id' => $l['prestation_id'] ?? null,
    ])->values() : $devis->lignes->map(fn ($l) => [
        'type' => $l->type, 'designation' => $l->designation, 'description' => $l->description,
        'quantite_texte' => \App\Support\Quantite::formater($l->quantite), 'unite' => $l->unite,
        'prix_texte' => number_format($l->prix_unitaire_ht / 100, 2, ',', ''), 'taux_tva' => $l->taux_tva,
        'option' => $l->option, 'prestation_id' => $l->prestation_id, 'total_texte' => $l->type === 'ligne' ? $l->totalAffiche() : '',
    ]);
@endphp

@section('contenu')
    <p class="texte-doux">Client : <a href="{{ route('clients.show', $devis->client) }}">{{ $devis->client->nomComplet() }}</a> · <span class="badge">{{ $devis->libelleStatut() }}</span></p>

    @if ($errors->any())
        <div class="message message-erreur" role="alert">
            <p>À corriger :</p>
            <ul>
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('devis.update', $devis) }}" id="editeur-devis" novalidate
          data-franchise="{{ $franchise ? '1' : '0' }}" data-taux-defaut="{{ (int) reglage('tva.taux_defaut') }}"
          data-recherche="{{ route('catalogue.recherche') }}">
        @csrf
        @method('put')

        <details class="carte" @if (! $devis->lignes->count()) open @endif>
            <summary><strong>Informations du devis</strong></summary>
            <x-champ nom="objet" libelle="Objet" :valeur="$devis->objet" aide="Par exemple : Réfection de la toiture côté nord." />
            @if ($devis->client->chantiers->isNotEmpty())
                <x-champ-liste nom="chantier_id" libelle="Adresse des travaux" :options="$devis->client->chantiers->mapWithKeys(fn ($c) => [$c->id => $c->titre().($c->libelle ? ' — '.$c->adresseComplete() : '')])->all()" :valeur="$devis->chantier_id" vide="Adresse du client" />
            @endif
            <div class="grille-2">
                <x-champ nom="validite_jours" libelle="Validité (jours)" type="number" :valeur="$devis->validite_jours" inputmode="numeric" min="1" />
                <x-champ nom="acompte_pourcentage" libelle="Acompte (%)" type="number" :valeur="$devis->acompte_pourcentage" inputmode="numeric" min="0" max="100" />
                <x-champ nom="date_debut_travaux" libelle="Début des travaux" type="date" :valeur="$devis->date_debut_travaux?->format('Y-m-d')" />
                <x-champ nom="duree_travaux" libelle="Durée des travaux" :valeur="$devis->duree_travaux" aide="Exemple : 3 jours" />
            </div>
            <x-champ-texte-long nom="dechets_estimation" libelle="Estimation des déchets" :valeur="$devis->dechets_estimation" :lignes="2" aide="Quantité estimée et nature (exemple : environ 2 m³ de tuiles et gravats, évacués en déchetterie professionnelle)." />
            <x-champ-texte-long nom="conditions" libelle="Conditions particulières" :valeur="$devis->conditions" :lignes="2" />
            <input type="hidden" name="hors_etablissement" value="0">
            <label class="case"><input type="checkbox" name="hors_etablissement" value="1" @checked(old('hors_etablissement', $devis->hors_etablissement))><span>Signé chez le client ou à distance (droit de rétractation de 14 jours)</span></label>
            <input type="hidden" name="urgence" value="0">
            <label class="case"><input type="checkbox" name="urgence" value="1" @checked(old('urgence', $devis->urgence))><span>Réparation urgente demandée par le client</span></label>
        </details>

        <ol class="lignes-devis" id="lignes" aria-label="Lignes du devis">
            @foreach ($lignesAffichees as $i => $ligne)
                @include('devis._ligne', ['i' => $i, 'ligne' => $ligne])
            @endforeach
        </ol>
        <p class="texte-doux vide-lignes" id="aucune-ligne" @if ($lignesAffichees->isNotEmpty()) hidden @endif>Aucune ligne : ajoutez-en une, ou cherchez dans le catalogue.</p>

        <div class="ajouts" data-si-js hidden>
            <button type="button" class="bouton" data-ajouter="ligne">+ Ligne</button>
            <button type="button" class="bouton bouton-secondaire" data-ouvrir="dialogue-catalogue">Catalogue</button>
            <button type="button" class="bouton bouton-secondaire" data-ajouter="section">+ Section</button>
            <button type="button" class="bouton bouton-secondaire" data-ajouter="texte">+ Texte</button>
            @if ($calculToiture)
                <button type="button" class="bouton bouton-secondaire" data-ouvrir="dialogue-toiture">Calcul toiture</button>
            @endif
        </div>

        <div class="carte">
            <div class="grille-2">
                <x-champ-liste nom="remise_type" libelle="Remise" :options="['pourcentage' => 'En %', 'montant' => 'En euros']" :valeur="$devis->remise_type" vide="Aucune" />
                <x-champ nom="remise" libelle="Valeur de la remise" :valeur="$devis->remise_type === 'pourcentage' ? \App\Support\Tva::formater($devis->remise_valeur, false) : ($devis->remise_type === 'montant' ? number_format($devis->remise_valeur / 100, 2, ',', '') : '')" inputmode="decimal" />
            </div>
            <dl class="totaux" aria-live="polite">
                <dt>Total HT</dt><dd data-total-ht>{{ \App\Support\Montant::formater($devis->total_ht) }}</dd>
                <dt>Remise</dt><dd data-total-remise>{{ \App\Support\Montant::formater($devis->total_remise) }}</dd>
                @if ($franchise)
                    <dt class="ligne-mention">TVA non applicable, art. 293 B du CGI</dt>
                @else
                    <dt>TVA</dt><dd data-total-tva>{{ \App\Support\Montant::formater($devis->total_tva) }}</dd>
                @endif
                <dt class="total-final">Total {{ $franchise ? 'à payer' : 'TTC' }}</dt><dd class="total-final" data-total-ttc>{{ \App\Support\Montant::formater($devis->total_ttc) }}</dd>
                <dt>Options (non comprises)</dt><dd data-total-options>{{ \App\Support\Montant::formater($devis->total_options_ht) }}</dd>
            </dl>
        </div>

        <div class="barre-actions">
            <button type="submit" class="bouton bouton-large">Enregistrer</button>
        </div>
    </form>

    {{-- Modèles pour les nouvelles lignes (inertes tant qu'ils ne sont pas copiés). --}}
    @foreach (['ligne', 'section', 'texte'] as $type)
        <template id="modele-{{ $type }}">
            @include('devis._ligne', ['i' => '__I__', 'ligne' => ['type' => $type, 'quantite_texte' => '1', 'unite' => 'u', 'taux_tva' => (int) reglage('tva.taux_defaut')]])
        </template>
    @endforeach

    <dialog id="dialogue-catalogue" class="dialogue" aria-labelledby="titre-catalogue">
        <h2 id="titre-catalogue">Ajouter depuis le catalogue</h2>
        <label for="recherche-catalogue" class="visuellement-cache">Chercher une prestation</label>
        <input type="search" id="recherche-catalogue" placeholder="Démoussage, gouttière…" autocomplete="off">
        <ul class="liste" id="resultats-catalogue" aria-live="polite"></ul>
        <button type="button" class="bouton bouton-secondaire bouton-large" data-fermer>Fermer</button>
    </dialog>

    @if ($calculToiture)
        <dialog id="dialogue-toiture" class="dialogue" aria-labelledby="titre-toiture">
            <h2 id="titre-toiture">Surface de toiture</h2>
            <p class="aide">La surface réelle du toit (rampant) est plus grande que la surface au sol.</p>
            <div class="champ"><label for="toiture-sol">Surface au sol (m²)</label><input type="text" id="toiture-sol" inputmode="decimal"></div>
            <div class="grille-2">
                <div class="champ"><label for="toiture-pente">Pente</label><input type="text" id="toiture-pente" inputmode="decimal"></div>
                <div class="champ"><label for="toiture-unite">En</label>
                    <select id="toiture-unite"><option value="degres">degrés (°)</option><option value="pourcentage">pourcentage (%)</option></select>
                </div>
            </div>
            <p class="resultat-toiture" id="toiture-resultat" aria-live="polite"></p>
            <button type="button" class="bouton bouton-large" id="toiture-utiliser" disabled>Ajouter une ligne avec cette surface</button>
            <button type="button" class="bouton bouton-secondaire bouton-large" data-fermer>Fermer</button>
        </dialog>
    @endif
@endsection
