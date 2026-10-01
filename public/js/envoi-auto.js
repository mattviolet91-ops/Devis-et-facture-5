/* Envoie automatiquement le formulaire marqué data-envoi-auto (redirection vers la page de paiement). */
(function () {
    'use strict';
    var formulaire = document.querySelector('form[data-envoi-auto]');
    if (formulaire) {
        formulaire.submit();
    }
})();
