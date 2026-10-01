/* Envoi par email : changer de modèle remplace l'objet et le message. */
(function () {
    'use strict';
    var formulaire = document.getElementById('formulaire-envoi');
    var choix = document.querySelector('[data-choix-modele]');
    if (!formulaire || !choix) {
        return;
    }
    var textes = JSON.parse(formulaire.getAttribute('data-textes'));
    choix.addEventListener('change', function () {
        var t = textes[choix.value];
        if (t) {
            formulaire.querySelector('[name="sujet"]').value = t.sujet;
            formulaire.querySelector('[name="corps"]').value = t.corps;
        }
    });
})();
