// Notifications sur le téléphone (Web Push) : abonnement depuis le menu « Plus ».
(function () {
    'use strict';

    var zone = document.querySelector('[data-notifications]');
    if (!zone) { return; }

    var etat = zone.querySelector('[data-etat-notifications]');
    var activer = zone.querySelector('[data-activer-notifications]');
    var couper = zone.querySelector('[data-couper-notifications]');
    var essai = zone.querySelector('[data-essai-notifications]');
    var cle = zone.getAttribute('data-cle');
    var url = zone.getAttribute('data-url');
    var jeton = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !cle) {
        return;
    }

    function cleBinaire(base64) {
        var complement = '='.repeat((4 - base64.length % 4) % 4);
        var brut = atob((base64 + complement).replace(/-/g, '+').replace(/_/g, '/'));
        var tableau = new Uint8Array(brut.length);
        for (var i = 0; i < brut.length; i++) { tableau[i] = brut.charCodeAt(i); }
        return tableau;
    }

    function envoyer(methode, abonnement) {
        return fetch(url, {
            method: methode,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jeton, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(abonnement)
        });
    }

    function afficher(actif) {
        if (Notification.permission === 'denied') {
            etat.textContent = 'Les notifications sont bloquées pour ce site. Autorisez-les dans les réglages du navigateur.';
            activer.hidden = true; couper.hidden = true; essai.hidden = true;
            return;
        }
        etat.textContent = actif ? 'Activées sur ce téléphone.' : 'Pas encore activées sur ce téléphone.';
        activer.hidden = actif; couper.hidden = !actif; essai.hidden = !actif;
    }

    navigator.serviceWorker.register('/sw.js').then(function () {
        return navigator.serviceWorker.ready;
    }).then(function (registration) {
        return registration.pushManager.getSubscription().then(function (abonnement) {
            afficher(!!abonnement);

            activer.addEventListener('click', function () {
                activer.disabled = true;
                Notification.requestPermission().then(function (permission) {
                    if (permission !== 'granted') { afficher(false); return null; }
                    return registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: cleBinaire(cle) });
                }).then(function (nouvel) {
                    if (!nouvel) { return; }
                    return envoyer('POST', nouvel.toJSON()).then(function () { afficher(true); });
                }).catch(function () {
                    etat.textContent = 'Impossible d\'activer les notifications sur ce téléphone.';
                }).then(function () { activer.disabled = false; });
            });

            couper.addEventListener('click', function () {
                registration.pushManager.getSubscription().then(function (actuel) {
                    if (!actuel) { afficher(false); return; }
                    return envoyer('DELETE', { endpoint: actuel.endpoint }).then(function () {
                        return actuel.unsubscribe();
                    }).then(function () { afficher(false); });
                });
            });
        });
    }).catch(function () {
        etat.textContent = 'Les notifications ne sont pas disponibles sur ce navigateur.';
    });
})();
