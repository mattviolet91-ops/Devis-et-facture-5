{{-- Formulaire de signature au doigt. Variable : $action --}}
<form method="post" action="{{ $action }}" class="carte formulaire-signature" id="formulaire-signature" novalidate>
    @csrf
    <h2>Signer le devis</h2>
    @if ($errors->any())
        <div class="message message-erreur" role="alert">
            @foreach ($errors->all() as $erreur)
                <p>{{ $erreur }}</p>
            @endforeach
        </div>
    @endif
    <x-champ nom="nom" libelle="Votre nom et prénom" :valeur="old('nom')" autocomplete="name" />
    <p class="etiquette" id="aide-signature">Signez avec le doigt dans le cadre :</p>
    <canvas id="zone-signature" class="zone-signature" width="600" height="220" role="img" aria-describedby="aide-signature" aria-label="Zone de signature"></canvas>
    <input type="hidden" name="signature" id="signature-donnees">
    <button type="button" class="bouton bouton-secondaire" id="effacer-signature">Effacer la signature</button>
    <label class="case">
        <input type="checkbox" name="accord" value="1">
        <span><strong>Bon pour accord</strong> : j'accepte ce devis et les conditions générales de vente.</span>
    </label>
    @if ($devis->hors_etablissement && ! $devis->client->estProfessionnel())
        <label class="case">
            <input type="checkbox" name="execution_immediate" value="1">
            <span>Je demande que les travaux commencent avant la fin du délai de rétractation de 14 jours (je paierai la part déjà réalisée si je me rétracte).</span>
        </label>
    @endif
    <p class="aide">Votre nom, la date, l'heure et l'adresse IP de votre appareil sont enregistrés avec la signature.</p>
    <button type="submit" class="bouton bouton-large" id="valider-signature">Signer le devis</button>
</form>
