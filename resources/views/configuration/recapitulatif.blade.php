@php
    $r = fn ($c) => (string) reglage($c);
    $resumes = [
        'metier' => \App\Support\Metiers::metier($r('entreprise.metier'))['libelle'] ?? '',
        'identite' => trim($r('identite.nom_commercial').' — SIRET '.$r('identite.siret'), ' —'),
        'tva' => \App\Support\Tva::estFranchise() ? 'Franchise en base (art. 293 B du CGI)' : 'Assujetti, taux par défaut '.\App\Support\Tva::formater((int) reglage('tva.taux_defaut')),
        'assurance' => trim($r('assurance.assureur').' — jusqu\'au '.($r('assurance.date_fin') ? \Carbon\Carbon::parse($r('assurance.date_fin'))->format('d/m/Y') : '?'), ' —'),
        'documents' => 'Prochain devis : '.app(\App\Services\Numerotation::class)->prochain('devis').' · validité '.(int) reglage('documents.validite_devis_jours').' jours',
        'apparence' => $r('apparence.logo') ? 'Logo enregistré' : 'Sans logo',
        'emails' => app(\App\Services\ConfigurationEmail::class)->estConfiguree() ? $r('emails.adresse') : 'Pas encore réglé (facultatif)',
        'cgv' => \App\Support\Configuration::estFaite('cgv') ? 'Relues et validées' : 'À relire',
    ];
@endphp

<div class="carte">
    <h2>Tout est prêt ?</h2>
    <ul class="liste">
        @foreach (\App\Support\Configuration::ETAPES as $cle => $info)
            @continue($cle === 'recapitulatif')
            <li class="ligne">
                <div>
                    <strong>{{ $info['titre'] }}</strong>
                    @if (\App\Support\Configuration::estFaite($cle))
                        <span class="badge badge-succes">Fait</span>
                    @elseif ($info['facultatif'])
                        <span class="badge">Facultatif</span>
                    @else
                        <span class="badge badge-danger">À remplir</span>
                    @endif
                    <small>{{ $resumes[$cle] ?? '' }}</small>
                </div>
                <a class="bouton bouton-secondaire" href="{{ route('configuration.etape', $cle) }}">Modifier</a>
            </li>
        @endforeach
    </ul>
</div>

<div class="carte">
    <h2>Devis d'exemple</h2>
    <p>Vérifiez la présentation de vos devis avec un client et des prix fictifs.</p>
    <a class="bouton bouton-secondaire bouton-large" href="{{ route('configuration.pdf') }}" target="_blank" rel="noopener">Voir le devis d'exemple (PDF)</a>
</div>

<form method="post" action="{{ route('configuration.terminer') }}" class="carte">
    @csrf
    @error('terminer')
        <p class="erreur-champ" role="alert">{{ $message }}</p>
    @enderror
    @if ($manquantes !== [])
        <p class="aide">Remplissez d'abord les écrans marqués « À remplir ».</p>
    @endif
    <button type="submit" class="bouton bouton-large">Terminer</button>
</form>
