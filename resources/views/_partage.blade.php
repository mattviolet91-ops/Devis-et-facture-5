{{-- Partage : WhatsApp, SMS, Copier, Email. Variables : $document, $message, $routeEmail --}}
<div class="actions-rapides partage">
    <a class="bouton" href="{{ $routeEmail }}">Email</a>
    <a class="bouton bouton-secondaire" href="{{ \App\Support\MessagesPrets::whatsapp($document->client->telephone, $message) }}" target="_blank" rel="noopener">WhatsApp</a>
    <a class="bouton bouton-secondaire" href="{{ \App\Support\MessagesPrets::sms($document->client->telephone, $message) }}">SMS</a>
    <button type="button" class="bouton bouton-secondaire" data-copier="message-pret" hidden>Copier</button>
</div>
<textarea id="message-pret" class="visuellement-cache" readonly aria-hidden="true" tabindex="-1">{{ $message }}</textarea>
