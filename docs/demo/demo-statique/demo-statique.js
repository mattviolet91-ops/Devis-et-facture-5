// Démo fixe (GitHub Pages) : on peut tout regarder, rien n'est enregistré.
(function () {
    'use strict';

    var bandeau = document.createElement('div');
    bandeau.className = 'message message-info demo-statique';
    bandeau.setAttribute('role', 'note');
    bandeau.textContent = 'Démo à regarder : les boutons « Enregistrer », « Envoyer »… ne font rien ici. Pour essayer pour de vrai, lancez l\'application dans GitHub Codespaces (voir le dépôt).';
    var contenu = document.getElementById('contenu') || document.body;
    contenu.insertBefore(bandeau, contenu.firstChild);

    function prevenir(texte) {
        var bulle = document.createElement('p');
        bulle.className = 'message message-info demo-bulle';
        bulle.setAttribute('role', 'status');
        bulle.textContent = texte;
        document.body.appendChild(bulle);
        setTimeout(function () { bulle.remove(); }, 3500);
    }

    window.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        prevenir('Démo à regarder : rien n\'est enregistré ni envoyé.');
    }, true);

    document.addEventListener('click', function (e) {
        var lien = e.target.closest ? e.target.closest('[data-demo-absent]') : null;
        if (lien) {
            e.preventDefault();
            prevenir('Cette page n\'est pas incluse dans la démo à regarder.');
        }
    }, true);
})();
