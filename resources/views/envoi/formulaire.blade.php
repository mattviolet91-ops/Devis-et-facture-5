@extends('layouts.app')

@section('titre', 'Envoyer par email')
@section('parent', match (true) {
    $document instanceof \App\Models\Devis => route('devis.show', $document),
    $document instanceof \App\Models\Rapport => route('rapports.show', $document),
    default => route('factures.show', $document),
})

@push('scripts')
    <script src="{{ asset('js/envoi.js') }}?v={{ filemtime(public_path('js/envoi.js')) }}" defer></script>
@endpush

@section('contenu')
    @unless ($configure)
        <div class="message message-erreur" role="alert">
            Le compte Gmail n'est pas encore réglé : l'email ne pourra pas partir.
            @if (auth()->user()->estGerant())
                <a href="{{ route('reglages.edit', 'emails') }}">Régler les emails</a>
            @endif
        </div>
    @endunless
    @if ($document instanceof \App\Models\Devis && $document->statut === 'brouillon')
        <div class="message message-info">Ce devis est un brouillon : il recevra son numéro et ne sera plus modifiable une fois envoyé.</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur" role="alert">
            @foreach ($errors->all() as $erreur)
                <p>{{ $erreur }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('envoi.store', [$type, $document->getKey()]) }}" class="carte" id="formulaire-envoi" novalidate
          data-textes="{{ json_encode($textes, JSON_UNESCAPED_UNICODE) }}">
        @csrf
        <p>{{ match (true) { $document instanceof \App\Models\Devis => 'Devis', $document instanceof \App\Models\Rapport => 'Rapport d\'intervention', default => $document->libelleType() } }} <strong>{{ $document->reference() }}</strong> · {{ $document->client->nomComplet() }}</p>
        <x-champ nom="destinataire" libelle="Email du client" type="email" :valeur="$document->client->email" inputmode="email" />
        @if (count($modeles) > 1)
            <x-champ-liste nom="modele" libelle="Modèle" :options="collect($modeles)->mapWithKeys(fn ($m) => [$m => ['devis' => 'Envoi du devis', 'relance_devis' => 'Relance du devis', 'facture' => 'Envoi de la facture', 'relance' => 'Relance', 'rapport' => 'Rapport'][$m] ?? $m])->all()" :valeur="$modele" :vide="false" data-choix-modele />
        @else
            <input type="hidden" name="modele" value="{{ $modele }}">
        @endif
        <x-champ nom="sujet" libelle="Objet" :valeur="$textes[$modele]['sujet']" />
        <x-champ-texte-long nom="corps" libelle="Message" :valeur="$textes[$modele]['corps']" :lignes="10" />
        <input type="hidden" name="joindre_pdf" value="0">
        <label class="case"><input type="checkbox" name="joindre_pdf" value="1" checked><span>Joindre le PDF</span></label>
        @if (reglage('emails.copie_cachee') && reglage('emails.adresse'))
            <p class="aide">Une copie cachée part aussi vers {{ reglage('emails.adresse') }}.</p>
        @endif
        <button type="submit" class="bouton bouton-large">Envoyer</button>
    </form>

    @if ($historique->isNotEmpty())
        <section class="carte">
            <h2>Déjà envoyés</h2>
            <ul class="liste">
                @foreach ($historique as $email)
                    <li class="ligne"><div>{{ $email->sujet }}<small>{{ $email->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }} · {{ $email->destinataire }}{{ $email->statut === 'erreur' ? ' · échec' : '' }}</small></div></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
