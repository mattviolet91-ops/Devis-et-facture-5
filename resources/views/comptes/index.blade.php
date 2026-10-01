@extends('layouts.app')

@section('titre', 'Comptes')
@section('parent', route('plus'))

@section('contenu')
    @if (session('lien_invitation'))
        <div class="message message-info" role="status">
            <p>Lien d'invitation (valable 7 jours) :</p>
            <p class="lien-client" id="lien-invitation">{{ session('lien_invitation') }}</p>
            <button type="button" class="bouton bouton-secondaire" data-copier="lien-invitation" hidden>Copier</button>
        </div>
    @endif

    <section class="carte" aria-labelledby="titre-comptes">
        <h2 id="titre-comptes">Comptes ({{ $comptes->where('is_active', true)->count() }} actifs)</h2>
        <ul class="liste">
            @foreach ($comptes as $compte)
                <li class="compte">
                    <div>
                        <strong>{{ $compte->email }}</strong>
                        <span class="badge">{{ $compte->libelleRole() }}</span>
                        @if (! $compte->is_active)
                            <span class="badge badge-danger">Désactivé</span>
                        @elseif ($compte->invitationEnAttente())
                            <span class="badge">Invitation en attente</span>
                        @endif
                        @if ($compte->is(auth()->user()))
                            <span class="badge badge-succes">Vous</span>
                        @endif
                        <small class="bloc texte-doux">
                            @if ($compte->last_login_at)
                                Dernière connexion : {{ $compte->last_login_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @else
                                Jamais connecté
                            @endif
                        </small>
                    </div>
                    @unless ($compte->is(auth()->user()))
                        <details>
                            <summary class="bouton-lien">Gérer<span class="visuellement-cache"> {{ $compte->email }}</span></summary>
                            @if ($compte->is_active)
                                <form method="post" action="{{ route('comptes.role', $compte) }}">
                                    @csrf
                                    <x-champ-liste nom="role" :id="'role-'.$compte->id" libelle="Rôle" :options="[\App\Models\User::ROLE_GERANT => 'Gérant (tout)', \App\Models\User::ROLE_COMMERCIAL => 'Commercial (sans factures ni chiffres)']" :valeur="$compte->role" :vide="false" />
                                    <button type="submit" class="bouton bouton-secondaire">Changer le rôle</button>
                                </form>
                                @if ($compte->invitationEnAttente())
                                    <form method="post" action="{{ route('comptes.renvoyer', $compte) }}">
                                        @csrf
                                        <button type="submit" class="bouton bouton-secondaire">Renvoyer l'invitation</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('comptes.desactiver', $compte) }}">
                                    @csrf
                                    <button type="submit" class="bouton bouton-secondaire texte-danger" data-confirmer="Désactiver ce compte ? La personne est déconnectée tout de suite.">Désactiver</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('comptes.reactiver', $compte) }}">
                                    @csrf
                                    <button type="submit" class="bouton bouton-secondaire">Réactiver</button>
                                </form>
                            @endif
                        </details>
                    @endunless
                </li>
            @endforeach
        </ul>
    </section>

    <form method="post" action="{{ route('comptes.inviter') }}" class="carte" novalidate>
        @csrf
        <h2>Inviter une personne</h2>
        <x-champ nom="email" libelle="Email" type="email" inputmode="email" autocomplete="off" />
        <x-champ-liste nom="role" libelle="Rôle" :options="[\App\Models\User::ROLE_COMMERCIAL => 'Commercial : prospects, rendez-vous, devis, photos', \App\Models\User::ROLE_GERANT => 'Gérant : accès complet']" :valeur="\App\Models\User::ROLE_COMMERCIAL" :vide="false" />
        <p class="aide">Le commercial ne voit jamais les factures, les paiements, le chiffre d'affaires ni les réglages. Les alertes de l'entreprise vont seulement aux gérants.</p>
        <button type="submit" class="bouton bouton-large">Envoyer l'invitation</button>
    </form>
@endsection
