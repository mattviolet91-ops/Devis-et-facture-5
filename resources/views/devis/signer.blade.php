@extends('layouts.app')

@section('titre', 'Faire signer')
@section('parent', route('devis.show', $devis))

@push('scripts')
    <script src="{{ asset('js/signature.js') }}?v={{ filemtime(public_path('js/signature.js')) }}" defer></script>
@endpush

@section('contenu')
    <div class="carte">
        <p>Devis <strong>{{ $devis->reference() }}</strong> pour <strong>{{ $devis->client->nomComplet() }}</strong></p>
        <p class="total-final">Total : {{ \App\Support\Montant::formater($devis->total_ttc) }}</p>
        <p><a href="{{ route('visionneuse', ['f' => '/devis/'.$devis->id.'/pdf', 'titre' => 'Devis '.$devis->reference()]) }}">Relire le devis complet avec le client</a></p>
    </div>
    @include('_signature', ['action' => route('devis.signer', $devis)])
@endsection
