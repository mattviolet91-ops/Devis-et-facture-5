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
        <p class="aide">Relevé automatique toutes les 15 minutes. Lecture seule : rien n'est supprimé ni marqué comme lu.</p>
    @endif

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
