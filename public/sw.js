// Service worker de l'application : notifications sur le téléphone.
'use strict';

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
    var donnees = {};
    try { donnees = event.data ? event.data.json() : {}; } catch (e) { donnees = { titre: 'Notification', texte: event.data ? event.data.text() : '' }; }

    event.waitUntil(self.registration.showNotification(donnees.titre || 'Notification', {
        body: donnees.texte || '',
        icon: '/icons/icone-192.png',
        badge: '/icons/icone-192.png',
        lang: 'fr',
        data: { lien: donnees.lien || '/accueil' }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var lien = (event.notification.data && event.notification.data.lien) || '/accueil';
    // Seulement des liens de l'application elle-même.
    var cible = new URL(lien, self.location.origin);
    if (cible.origin !== self.location.origin) { cible = new URL('/accueil', self.location.origin); }

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenetres) {
        for (var i = 0; i < fenetres.length; i++) {
            if ('focus' in fenetres[i] && 'navigate' in fenetres[i]) {
                return fenetres[i].navigate(cible.href).then(function (f) { return f && f.focus(); });
            }
        }
        return self.clients.openWindow(cible.href);
    }));
});
