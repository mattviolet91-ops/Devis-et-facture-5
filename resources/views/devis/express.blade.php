@extends('layouts.app')

@section('titre', 'Devis express')
@section('parent', route('devis.index'))

@push('scripts')
    <script src="{{ asset('js/dictee.js') }}?v={{ filemtime(public_path('js/dictee.js')) }}" defer></script>
@endpush

@section('contenu')
    <div class="message message-info">
        Écrivez ou dictez le devis en une phrase, par exemple :<br>
        <em>« Mme Martin, démoussage 120 m² à 12 €, 3 faîtières à 150 €, évacuation forfait 150 € »</em><br>
        Les prix sont HT. Vous verrez un aperçu avant la création. Rien n'est envoyé au client.
    </div>

    @error('phrase')
        <div class="message message-erreur" role="alert">{{ $message }}</div>
    @enderror

    <form method="post" action="{{ route('devis.express.apercu') }}" class="carte" novalidate>
        @csrf
        <div class="champ">
            <label for="phrase">Votre devis en une phrase</label>
            <textarea id="phrase" name="phrase" rows="5" required>{{ $phrase }}</textarea>
        </div>
        <button type="button" class="bouton bouton-secondaire bouton-large" id="dicter" hidden aria-pressed="false">🎤 Dicter</button>
        <p class="aide" id="etat-dictee" aria-live="polite"></p>
        <button type="submit" class="bouton bouton-large">Voir l'aperçu</button>
    </form>

    @if ($resultat)
        <section class="carte" aria-labelledby="titre-apercu">
            <h2 id="titre-apercu">Aperçu</h2>
            <p>Client : <strong>{{ $resultat['client']?->nomComplet() ?? '—' }}</strong></p>

            @if ($resultat['erreurs'])
                <div class="message message-erreur" role="alert">
                    <p><strong>Impossible de créer le brouillon :</strong></p>
                    <ul>
                        @foreach ($resultat['erreurs'] as $erreur)
                            <li>{{ $erreur }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($resultat['alertes'])
                <div class="message message-info">
                    <ul>
                        @foreach ($resultat['alertes'] as $alerte)
                            <li>{{ $alerte }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <ul class="liste">
                @foreach ($resultat['lignes'] as $ligne)
                    <li class="ligne">
                        <div>
                            {{ $ligne['designation'] ?: '?' }}
                            <small>{{ \App\Support\Quantite::formater($ligne['quantite']) }} {{ $ligne['unite'] }} × {{ \App\Support\Montant::formater($ligne['prix_unitaire_ht']) }}</small>
                        </div>
                        <strong>{{ \App\Support\Montant::formater(\App\Support\Quantite::total($ligne['quantite'], $ligne['prix_unitaire_ht'])) }}</strong>
                    </li>
                @endforeach
            </ul>
            <dl class="totaux"><dt class="total-final">Total HT</dt><dd class="total-final">{{ \App\Support\Montant::formater($resultat['total_ht']) }}</dd></dl>

            @if (! $resultat['erreurs'])
                <form method="post" action="{{ route('devis.express.store') }}">
                    @csrf
                    <input type="hidden" name="phrase" value="{{ $phrase }}">
                    <button type="submit" class="bouton bouton-large">Créer le brouillon</button>
                </form>
            @endif
        </section>
    @endif
@endsection
