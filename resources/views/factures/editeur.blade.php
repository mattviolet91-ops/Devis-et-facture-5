@extends('layouts.app')

@section('titre', $facture->libelleType().' · '.$facture->reference())
@section('parent', route('factures.show', $facture))

@push('scripts')
    <script src="{{ asset('js/devis.js') }}?v={{ filemtime(public_path('js/devis.js')) }}" defer></script>
@endpush

@php
    $lignesAffichees = $facture->lignes->map(fn ($l) => [
        'type' => $l->type, 'designation' => $l->designation, 'description' => $l->description,
        'quantite_texte' => \App\Support\Quantite::formater(abs($l->quantite)), 'unite' => $l->unite,
        'prix_texte' => number_format($l->prix_unitaire_ht / 100, 2, ',', ''), 'taux_tva' => $l->taux_tva,
        'option' => false, 'prestation_id' => $l->prestation_id, 'total_texte' => $l->type === 'ligne' ? $l->totalAffiche() : '',
    ]);
@endphp

@section('contenu')
    <p class="texte-doux">Client : <strong>{{ $facture->client->nomComplet() }}</strong></p>

    @if ($errors->any())
        <div class="message message-erreur" role="alert">
            <ul>
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('factures.update', $facture) }}" id="editeur-devis" novalidate
          data-franchise="{{ $franchise ? '1' : '0' }}" data-taux-defaut="{{ (int) reglage('tva.taux_defaut') }}" data-recherche="{{ route('catalogue.recherche') }}">
        @csrf
        @method('put')

        <details class="carte">
            <summary><strong>Informations de la facture</strong></summary>
            <x-champ nom="objet" libelle="Objet" :valeur="$facture->objet" />
            <div class="grille-2">
                <x-champ nom="date_prestation" libelle="Date de la prestation" type="date" :valeur="$facture->date_prestation?->format('Y-m-d')" />
                <x-champ nom="delai_paiement_jours" libelle="Délai de paiement (jours)" type="number" :valeur="$facture->delai_paiement_jours" min="0" max="60" />
            </div>
            <x-champ-texte-long nom="conditions" libelle="Remarques" :valeur="$facture->conditions" :lignes="2" />
        </details>

        <ol class="lignes-devis" id="lignes" aria-label="Lignes de la facture">
            @foreach ($lignesAffichees as $i => $ligne)
                @include('devis._ligne', ['i' => $i, 'ligne' => $ligne, 'sansOption' => true])
            @endforeach
        </ol>
        <p class="texte-doux" id="aucune-ligne" @if ($lignesAffichees->isNotEmpty()) hidden @endif>Aucune ligne.</p>

        <div class="ajouts" data-si-js hidden>
            <button type="button" class="bouton" data-ajouter="ligne">+ Ligne</button>
            <button type="button" class="bouton bouton-secondaire" data-ouvrir="dialogue-catalogue">Catalogue</button>
            <button type="button" class="bouton bouton-secondaire" data-ajouter="section">+ Section</button>
            <button type="button" class="bouton bouton-secondaire" data-ajouter="texte">+ Texte</button>
        </div>

        <div class="carte">
            <div class="grille-2">
                <x-champ-liste nom="remise_type" libelle="Remise" :options="['pourcentage' => 'En %', 'montant' => 'En euros']" :valeur="$facture->remise_type" vide="Aucune" />
                <x-champ nom="remise" libelle="Valeur de la remise" :valeur="$facture->remise_type === 'pourcentage' ? \App\Support\Tva::formater($facture->remise_valeur, false) : ($facture->remise_type === 'montant' ? number_format($facture->remise_valeur / 100, 2, ',', '') : '')" inputmode="decimal" />
            </div>
            <dl class="totaux" aria-live="polite">
                <dt>Total HT</dt><dd data-total-ht>{{ \App\Support\Montant::formater($facture->total_ht) }}</dd>
                <dt>Remise</dt><dd data-total-remise>{{ \App\Support\Montant::formater($facture->total_remise) }}</dd>
                @if ($franchise)
                    <dt class="ligne-mention">TVA non applicable, art. 293 B du CGI</dt>
                @else
                    <dt>TVA</dt><dd data-total-tva>{{ \App\Support\Montant::formater($facture->total_tva) }}</dd>
                @endif
                <dt class="total-final">Total {{ $franchise ? 'à payer' : 'TTC' }}</dt><dd class="total-final" data-total-ttc>{{ \App\Support\Montant::formater($facture->total_ttc) }}</dd>
                <dt class="visuellement-cache">Options</dt><dd class="visuellement-cache" data-total-options></dd>
            </dl>
        </div>

        <div class="barre-actions">
            <button type="submit" class="bouton bouton-large">Enregistrer</button>
        </div>
    </form>

    @foreach (['ligne', 'section', 'texte'] as $type)
        <template id="modele-{{ $type }}">
            @include('devis._ligne', ['i' => '__I__', 'sansOption' => true, 'ligne' => ['type' => $type, 'quantite_texte' => '1', 'unite' => 'u', 'taux_tva' => (int) reglage('tva.taux_defaut')]])
        </template>
    @endforeach

    <dialog id="dialogue-catalogue" class="dialogue" aria-labelledby="titre-catalogue">
        <h2 id="titre-catalogue">Ajouter depuis le catalogue</h2>
        <label for="recherche-catalogue" class="visuellement-cache">Chercher une prestation</label>
        <input type="search" id="recherche-catalogue" placeholder="Démoussage, gouttière…" autocomplete="off">
        <ul class="liste" id="resultats-catalogue" aria-live="polite"></ul>
        <button type="button" class="bouton bouton-secondaire bouton-large" data-fermer>Fermer</button>
    </dialog>
@endsection
