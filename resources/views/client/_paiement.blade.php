@if (app(\App\Services\MyPos::class)->visiblePourLesClients())
    <form method="post" action="{{ route('client.payer', $lien->jeton()) }}">
        @csrf
        <button type="submit" class="bouton bouton-large">Payer par carte ({{ \App\Support\Montant::formater($facture->resteAPayer()) }})</button>
    </form>
    <p class="aide">Paiement sécurisé par myPOS. Vos données de carte ne passent pas par notre application.</p>
@endif
