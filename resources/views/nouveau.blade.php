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
            <li>
                <a class="liste-lien" href="{{ route('devis.create') }}">
                    <x-icone nom="devis" /><span class="libelle">Un devis</span><x-icone nom="fleche" />
                </a>
            </li>
            <li>
                <a class="liste-lien" href="{{ route('devis.express') }}">
                    <x-icone nom="devis" /><span class="libelle">Un devis express (une phrase)</span><x-icone nom="fleche" />
                </a>
            </li>
            @if (auth()->user()->estGerant())
                <li>
                    <a class="liste-lien" href="{{ route('factures.create') }}">
                        <x-icone nom="factures" /><span class="libelle">Une facture</span><x-icone nom="fleche" />
                    </a>
                </li>
            @endif
            <li>
                <a class="liste-lien" href="{{ route('planning.create') }}">
                    <x-icone nom="planning" /><span class="libelle">Un rendez-vous</span><x-icone nom="fleche" />
                </a>
            </li>
        </ul>
    </div>
@endsection
