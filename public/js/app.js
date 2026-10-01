/* Script principal de l'application (sans framework, aucun code inline). */
(function () {
    'use strict';

    var CLE_HISTORIQUE = 'historique-app';
    var MAX_HISTORIQUE = 50;

    function lire(cle, defaut) {
        try {
            var valeur = sessionStorage.getItem(cle);
            return valeur ? JSON.parse(valeur) : defaut;
        } catch (e) {
            return defaut;
        }
    }

    function ecrire(cle, valeur) {
        try {
            sessionStorage.setItem(cle, JSON.stringify(valeur));
        } catch (e) { /* ignoré */ }
    }

    function adresseActuelle() {
        return location.pathname + location.search;
    }

    /* ----------------------------------------------------------------
       Bouton « ‹ Retour » : l'application installée n'a pas de flèche
       retour. On garde la liste des pages vues dans l'onglet ; on saute
       les formulaires déjà enregistrés ; sinon on va à la page parente.
       ---------------------------------------------------------------- */
    function historique() {
        var h = lire(CLE_HISTORIQUE, []);
        return Array.isArray(h) ? h : [];
    }

    function noterPage() {
        if (document.body.hasAttribute('data-sans-historique')) {
            ecrire(CLE_HISTORIQUE, []);
            return;
        }
        var h = historique();
        var ici = adresseActuelle();
        var dernier = h[h.length - 1];

        if (dernier && dernier.u === ici) {
            /* Même page (rechargement ou formulaire renvoyé avec erreurs) :
               elle n'est plus considérée comme « enregistrée ». */
            dernier.e = false;
        } else {
            h.push({ u: ici, e: false });
        }
        ecrire(CLE_HISTORIQUE, h.slice(-MAX_HISTORIQUE));
    }

    function marquerFormulaireEnregistre() {
        var h = historique();
        var dernier = h[h.length - 1];
        if (dernier && dernier.u === adresseActuelle()) {
            dernier.e = true;
            ecrire(CLE_HISTORIQUE, h);
        }
    }

    function destinationRetour(parent) {
        var h = historique();
        h.pop(); /* la page actuelle */
        while (h.length && (h[h.length - 1].e || h[h.length - 1].u === adresseActuelle())) {
            h.pop();
        }
        ecrire(CLE_HISTORIQUE, h);
        return h.length ? h[h.length - 1].u : parent;
    }

    document.addEventListener('click', function (evenement) {
        var lien = evenement.target.closest('[data-retour]');
        if (!lien) {
            return;
        }
        evenement.preventDefault();
        location.href = destinationRetour(lien.getAttribute('href'));
    });

    /* ----------------------------------------------------------------
       Formulaires : on note qu'ils sont enregistrés et on évite le
       double envoi (doigt qui appuie deux fois).
       ---------------------------------------------------------------- */
    document.addEventListener('submit', function (evenement) {
        var formulaire = evenement.target;
        if ((formulaire.getAttribute('method') || 'get').toLowerCase() === 'post') {
            marquerFormulaireEnregistre();
        }
        var boutons = formulaire.querySelectorAll('button[type="submit"], button:not([type])');
        window.setTimeout(function () {
            boutons.forEach(function (b) { b.disabled = true; });
        }, 0);
    });

    /* Après un retour arrière du navigateur, les boutons redeviennent actifs. */
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[disabled]:not([data-toujours-desactive])').forEach(function (b) {
            b.disabled = false;
        });
    });

    /* ----------------------------------------------------------------
       Afficher / masquer le mot de passe.
       ---------------------------------------------------------------- */
    document.querySelectorAll('[data-afficher-mot-de-passe]').forEach(function (bouton) {
        bouton.hidden = false;
        bouton.addEventListener('click', function () {
            var champ = document.getElementById(bouton.getAttribute('aria-controls'));
            var visible = champ.type === 'text';
            champ.type = visible ? 'password' : 'text';
            bouton.textContent = visible ? 'Afficher' : 'Masquer';
            bouton.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });
    });

    /* ----------------------------------------------------------------
       Thème (automatique, clair, sombre) et « Grands boutons » :
       réglés sur ce téléphone seulement.
       ---------------------------------------------------------------- */
    var racine = document.documentElement;

    document.querySelectorAll('input[name="theme"]').forEach(function (choix) {
        var actuel = racine.getAttribute('data-theme') || 'auto';
        choix.checked = choix.value === actuel;
        choix.addEventListener('change', function () {
            try {
                if (choix.value === 'auto') {
                    localStorage.removeItem('theme');
                } else {
                    localStorage.setItem('theme', choix.value);
                }
            } catch (e) { /* ignoré */ }
            if (choix.value === 'auto') {
                racine.removeAttribute('data-theme');
            } else {
                racine.setAttribute('data-theme', choix.value);
            }
        });
    });

    var grandsBoutons = document.getElementById('grands-boutons');
    if (grandsBoutons) {
        grandsBoutons.checked = racine.getAttribute('data-grands-boutons') === '1';
        grandsBoutons.addEventListener('change', function () {
            try {
                localStorage.setItem('grands-boutons', grandsBoutons.checked ? '1' : '0');
            } catch (e) { /* ignoré */ }
            if (grandsBoutons.checked) {
                racine.setAttribute('data-grands-boutons', '1');
            } else {
                racine.removeAttribute('data-grands-boutons');
            }
        });
    }

    /* Réglages d'affichage : visibles seulement si le JavaScript fonctionne. */
    document.querySelectorAll('[data-si-js]').forEach(function (el) { el.hidden = false; });
    document.querySelectorAll('[data-sans-js]').forEach(function (el) { el.hidden = true; });

    noterPage();
})();
