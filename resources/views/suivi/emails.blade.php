@extends('layouts.app')

@section('titre', 'Mes derniers emails')
@section('parent', route('suivi'))

@section('contenu')
    @if (! $configuree)
        <div class="carte">
            <p>La lecture des emails n'est pas activée.</p>
            <p class="aide">Activez-la dans <a href="{{ route('reglages.edit', 'suivi') }}">Réglages → Suivi commercial</a> (Gmail doit être réglé dans Réglages → Emails).</p>
        </div>
    @else
        <form method="post" action="{{ route('suivi.emails.actualiser') }}">
            @csrf
            <button type="submit" class="bouton bouton-secondaire bouton-large">Relever maintenant</button>
        </form>
        <form method="post" action="{{ route('suivi.emails.verifier') }}">
            @csrf
            <button type="submit" class="bouton bouton-secondaire bouton-large">Vérifier la détection (sans rien créer)</button>
        </form>
        <p class="aide">Relevé automatique toutes les 5 minutes. Lecture seule : rien n'est supprimé ni marqué comme lu.</p>
    @endif

    @isset($verification)
        <section class="carte" aria-labelledby="titre-verification">
            <h2 id="titre-verification">Vérification ({{ count($verification) }} emails, rien n'a été créé)</h2>
            <ul class="liste">
                @foreach ($verification as $email)
                    <li class="ligne">
                        <div>
                            <strong>{{ $email['sujet'] ?: '(sans objet)' }}</strong>
                            <span @class(['badge', 'badge-succes' => $email['est_demande']])>{{ $email['est_demande'] ? 'Repéré comme demande' : 'Ignoré' }}</span>
                            <small>{{ $email['expediteur'] ?: $email['expediteur_email'] }} · {{ $email['date']->timezone(config('app.timezone'))->format('d/m H:i') }}</small>
                            @if ($email['est_demande'])
                                <small>Nom : {{ $email['champs']['nom'] ?: '—' }} · Tél. : {{ $email['champs']['telephone'] ?: '—' }} · Email : {{ $email['champs']['email'] ?: '—' }} · Ville : {{ $email['champs']['ville'] ?: '—' }}</small>
                                <small>Travaux : {{ \Illuminate\Support\Str::limit($email['champs']['message'], 120) }}</small>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="aide">Un email du site n'est pas repéré ? Changez le « Mot qui repère un email du site » dans <a href="{{ route('reglages.edit', 'suivi') }}">Réglages → Suivi commercial</a>.</p>
        </section>
    @endisset

    @if ($emails->isNotEmpty())
        <ul class="liste carte">
            @foreach ($emails as $email)
                <li class="ligne">
                    <div>
                        <strong>{{ $email->sujet ?: '(sans objet)' }}</strong>
                        @if ($email->est_demande)
                            <span class="badge badge-succes">Demande</span>
                        @endif
                        <small>{{ $email->expediteur ?: $email->expediteur_email }} · {{ $email->recu_at->timezone(config('app.timezone'))->format('d/m H:i') }}</small>
                        <small>{{ \Illuminate\Support\Str::limit($email->extrait, 140) }}</small>
                    </div>
                </li>
            @endforeach
        </ul>
    @elseif ($configuree)
        <div class="carte vide"><p>Aucun email relevé pour le moment.</p></div>
    @endif
@endsection
