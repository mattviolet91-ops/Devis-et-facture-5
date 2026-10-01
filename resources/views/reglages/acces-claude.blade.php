@extends('layouts.app')

@section('titre', 'Accès Claude')
@section('parent', route('reglages'))

@section('contenu')
    @if (session('cle_creee'))
        <div class="message message-info" role="status">
            <p><strong>Votre clé (affichée une seule fois) :</strong></p>
            <p class="lien-client" id="cle-creee">{{ session('cle_creee') }}</p>
            <button type="button" class="bouton bouton-secondaire" data-copier="cle-creee" hidden>Copier</button>
            <p class="aide">Ne la collez jamais dans une conversation : enregistrez-la dans les réglages de l'environnement de Claude (étape 3 ci-dessous).</p>
        </div>
    @endif

    <section class="carte">
        <h2>Préparer des devis avec Claude</h2>
        <p>Claude peut chercher vos clients et vos prestations, montrer l'aperçu d'un devis express et créer un <strong>brouillon</strong>. Il n'envoie jamais rien à vos clients : vous vérifiez et envoyez vous-même.</p>
        <ol class="etapes-guide">
            <li>Créez une clé ci-dessous et copiez-la.</li>
            <li>Dans Claude (application sur le téléphone ou claude.ai), ouvrez les réglages de l'environnement de travail.</li>
            <li>Ajoutez deux variables :
                <ul>
                    <li><code>MC_API_URL</code> = <code id="adresse-api">{{ $adresseApi }}</code></li>
                    <li><code>MC_API_TOKEN</code> = la clé copiée</li>
                </ul>
            </li>
            <li>Demandez par exemple : « Prépare un devis pour Mme Martin : démoussage 120 m² à 12 € ».</li>
            <li>Ouvrez le brouillon dans l'application, vérifiez-le, puis envoyez-le.</li>
        </ol>
        <p class="aide">30 appels par minute au plus. La clé fonctionne seulement tant que votre compte gérant est actif.</p>
    </section>

    <section class="carte">
        <h2>Clés</h2>
        @if ($cles->isEmpty())
            <p class="texte-doux">Aucune clé pour le moment.</p>
        @else
            <ul class="liste">
                @foreach ($cles as $cle)
                    <li class="ligne">
                        <div>
                            <strong>{{ $cle->nom }}</strong> <code>{{ $cle->debut }}…</code>
                            @if ($cle->revoquee_at)
                                <span class="badge badge-danger">Révoquée</span>
                            @endif
                            <small>Créée le {{ $cle->created_at->timezone(config('app.timezone'))->format('d/m/Y') }} · {{ $cle->derniere_utilisation_at ? 'dernière utilisation le '.$cle->derniere_utilisation_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') : 'jamais utilisée' }}</small>
                        </div>
                        @unless ($cle->revoquee_at)
                            <form method="post" action="{{ route('reglages.claude.revoquer', $cle) }}">
                                @csrf
                                <button type="submit" class="bouton-lien texte-danger" data-confirmer="Révoquer cette clé ? Claude ne pourra plus l'utiliser.">Révoquer<span class="visuellement-cache"> {{ $cle->nom }}</span></button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('reglages.claude.creer') }}" novalidate>
            @csrf
            <x-champ nom="nom" libelle="Nom de la nouvelle clé" aide="Par exemple : Claude sur mon téléphone." />
            <button type="submit" class="bouton bouton-large">Créer une clé</button>
        </form>
    </section>
@endsection
