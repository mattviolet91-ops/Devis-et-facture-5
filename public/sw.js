// Service worker de l'application : hors connexion et notifications sur le téléphone.
'use strict';

var VERSION = 'v1';
var CACHE_FIXE = 'fixe-' + VERSION;      // styles, scripts, icônes, page « hors connexion », captures du guide
var CACHE_PAGES = 'pages-' + VERSION;    // dernières pages ouvertes (lecture sans réseau)
var MAX_PAGES = 40;

var FICHIERS_FIXES = [
    '/hors-connexion.html',
    '/js/hors-connexion.js',
    '/css/app.css',
    '/js/app.js',
    '/js/theme-init.js',
    '/icons/icone.svg',
    '/icons/icone-192.png',
    '/images/guide/accueil.png',
    '/images/guide/clients.png',
    '/images/guide/devis.png',
    '/images/guide/devis-express.png',
    '/images/guide/factures.png',
    '/images/guide/planning.png',
    '/images/guide/photos.png',
    '/images/guide/suivi.png',
    '/images/guide/statistiques.png'
];

// Pages jamais gardées : espace client, API, connexion, compteur du site.
var JAMAIS = /^(c\/|api\/|connexion|deconnexion|invitation|mot-de-passe|nouveau-mot-de-passe|s$|s\.js|paiement\/)/;

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_FIXE).then(function (cache) {
        return cache.addAll(FICHIERS_FIXES);
    }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (cles) {
        return Promise.all(cles.filter(function (cle) {
            return cle !== CACHE_FIXE && cle !== CACHE_PAGES;
        }).map(function (cle) { return caches.delete(cle); }));
    }).then(function () { return self.clients.claim(); }));
});

function limiter(cache) {
    return cache.keys().then(function (cles) {
        if (cles.length <= MAX_PAGES) { return null; }
        return cache.delete(cles[0]).then(function () { return limiter(cache); });
    });
}

self.addEventListener('fetch', function (event) {
    var requete = event.request;
    if (requete.method !== 'GET') { return; }
    var adresse = new URL(requete.url);
    if (adresse.origin !== self.location.origin || JAMAIS.test(adresse.pathname.replace(/^\//, ''))) {
        return;
    }

    // Pages : le réseau d'abord, la dernière version gardée si pas de réseau.
    if (requete.mode === 'navigate') {
        event.respondWith(fetch(requete).then(function (reponse) {
            var html = (reponse.headers.get('Content-Type') || '').indexOf('text/html') === 0;
            if (reponse.ok && html && !reponse.redirected) {
                var copie = reponse.clone();
                caches.open(CACHE_PAGES).then(function (cache) {
                    return cache.put(requete, copie).then(function () { return limiter(cache); });
                });
            }
            return reponse;
        }).catch(function () {
            return caches.match(requete, { cacheName: CACHE_PAGES }).then(function (gardee) {
                return gardee || caches.match('/hors-connexion.html');
            });
        }));
        return;
    }

    // Styles, scripts, images : la version gardée tout de suite, mise à jour en arrière-plan.
    if (/^\/(css|js|icons|images|vendor)\//.test(adresse.pathname) || adresse.pathname === '/theme.css') {
        event.respondWith(caches.open(CACHE_FIXE).then(function (cache) {
            return cache.match(requete, { ignoreSearch: true }).then(function (gardee) {
                var reseau = fetch(requete).then(function (reponse) {
                    if (reponse.ok) { cache.put(requete, reponse.clone()); }
                    return reponse;
                }).catch(function () { return gardee; });
                return gardee || reseau;
            });
        }));
    }
});

// Déconnexion : la page demande d'oublier les pages gardées.
self.addEventListener('message', function (event) {
    if (event.data === 'oublier-pages') {
        event.waitUntil(caches.delete(CACHE_PAGES));
    }
});

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
