@extends('layouts.app')

@section('titre', 'Nouveau')
@section('parent', route('accueil'))

@section('contenu')
    <div class="carte">
        <p class="texte-doux">Que voulez-vous créer ?</p>
        <ul class="liste">
            <li>
                <a class="liste-lien" href="{{ route('clients.create') }}">
                    <x-icone nom="clients" /><span class="libelle">Un client</span><x-icone nom="fleche" />
                </a>
            </li>
            @foreach ([['devis', 'Un devis', 'devis'], ['factures', 'Une facture', 'factures'], ['planning', 'Un rendez-vous', 'planning']] as [$rubrique, $libelle, $icone])
                <li>
                    <a class="liste-lien" href="{{ route('bientot', $rubrique) }}">
                        <x-icone :nom="$icone" /><span class="libelle">{{ $libelle }}</span><span class="badge">Bientôt</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
