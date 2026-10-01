/* Dictée du devis express (reconnaissance vocale du téléphone, si disponible). */
(function () {
    'use strict';

    var bouton = document.getElementById('dicter');
    var Reconnaissance = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!bouton || !Reconnaissance) {
        return;
    }

    var zone = document.getElementById('phrase');
    var etat = document.getElementById('etat-dictee');
    var reco = new Reconnaissance();
    reco.lang = 'fr-FR';
    reco.interimResults = false;
    reco.continuous = true;
    var actif = false;

    bouton.hidden = false;

    reco.onresult = function (e) {
        for (var i = e.resultIndex; i < e.results.length; i++) {
            if (e.results[i].isFinal) {
                zone.value = (zone.value ? zone.value.replace(/\s+$/, '') + ' ' : '') + e.results[i][0].transcript.trim();
            }
        }
    };
    reco.onend = function () {
        actif = false;
        bouton.setAttribute('aria-pressed', 'false');
        bouton.textContent = '🎤 Dicter';
        etat.textContent = '';
    };
    reco.onerror = function () {
        etat.textContent = 'La dictée n\'a pas marché. Vérifiez l\'autorisation du micro.';
    };

    bouton.addEventListener('click', function () {
        if (actif) {
            reco.stop();
            return;
        }
        actif = true;
        bouton.setAttribute('aria-pressed', 'true');
        bouton.textContent = '■ Arrêter la dictée';
        etat.textContent = 'Parlez : « Madame Martin, démoussage 120 mètres carrés à 12 euros… »';
        reco.start();
    });
})();
