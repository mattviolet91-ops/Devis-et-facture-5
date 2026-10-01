@extends('layouts.app')

@section('titre', 'Demande de devis')
@section('parent', route('suivi'))

@section('contenu')
    <section class="carte">
        <h2>{{ $demande->nom ?: 'Sans nom' }}</h2>
        <p><span class="badge">{{ $demande->libelleSource() }}</span> <span class="badge">{{ $demande->libelleStatut() }}</span></p>
        <dl class="details">
            <dt>Reçue le</dt><dd>{{ $demande->recue_at->format('d/m/Y à H:i') }}</dd>
            @if ($demande->telephone)
                <dt>Téléphone</dt><dd><a href="{{ \App\Support\Telephone::lien($demande->telephone) }}">{{ \App\Support\Telephone::formater($demande->telephone) }}</a></dd>
            @endif
            @if ($demande->email)
                <dt>Email</dt><dd><a href="mailto:{{ $demande->email }}">{{ $demande->email }}</a></dd>
            @endif
            @if ($demande->ville)
                <dt>Ville</dt><dd>{{ $demande->ville }}</dd>
            @endif
        </dl>
        <h3>Message</h3>
        <p class="texte-pre">{{ $demande->message }}</p>
    </section>

    @if ($demande->client)
        <a class="bouton bouton-large" href="{{ route('clients.show', $demande->client) }}">Voir la fiche client</a>
    @else
        <form method="post" action="{{ route('suivi.demande.client', $demande) }}">
            @csrf
            <button type="submit" class="bouton bouton-large">Créer le client</button>
        </form>
    @endif

    <div class="actions-ligne">
        @foreach (['traitee' => 'Marquer traitée', 'ecartee' => 'Écarter (pub, erreur…)', 'nouvelle' => 'Remettre en nouvelle'] as $statut => $libelle)
            @if ($demande->statut !== $statut)
                <form method="post" action="{{ route('suivi.demande.statut', $demande) }}">
                    @csrf
                    <input type="hidden" name="statut" value="{{ $statut }}">
                    <button type="submit" class="bouton bouton-secondaire">{{ $libelle }}</button>
                </form>
            @endif
        @endforeach
    </div>
@endsection
