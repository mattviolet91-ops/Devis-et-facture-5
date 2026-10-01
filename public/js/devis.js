/* Éditeur de devis : lignes, sections, options, remise, totaux en direct.
   Calculs en centimes et en millièmes, comme sur le serveur (aucun nombre à virgule). */
(function () {
    'use strict';

    var formulaire = document.getElementById('editeur-devis');
    if (!formulaire) {
        return;
    }

    var liste = document.getElementById('lignes');
    var franchise = formulaire.getAttribute('data-franchise') === '1';

    /* ---------- Lecture des nombres saisis ---------- */
    function nettoyer(texte) {
        return String(texte || '').replace(/[\s  €]/g, '');
    }

    function lireMilliemes(texte) {
        var m = nettoyer(texte).match(/^(\d+)(?:[.,](\d{1,3}))?$/);
        return m ? parseInt(m[1], 10) * 1000 + parseInt((m[2] || '0').padEnd(3, '0'), 10) : null;
    }

    function lireCentimes(texte) {
        var m = nettoyer(texte).match(/^(-?)(\d+)(?:[.,](\d{1,2}))?$/);
        if (!m) {
            return null;
        }
        var c = parseInt(m[2], 10) * 100 + parseInt((m[3] || '0').padEnd(2, '0'), 10);
        return m[1] === '-' ? -c : c;
    }

    function lireTaux(texte) {
        var m = nettoyer(texte).replace('%', '').match(/^(\d{1,3})(?:[.,](\d{1,2}))?$/);
        return m ? parseInt(m[1], 10) * 100 + parseInt((m[2] || '0').padEnd(2, '0'), 10) : null;
    }

    function arrondiDivision(a, b) {
        var signe = a < 0 ? -1 : 1;
        return signe * Math.floor((Math.abs(a) + Math.floor(b / 2)) / b);
    }

    function euros(centimes) {
        var negatif = centimes < 0;
        centimes = Math.abs(centimes);
        var entier = Math.floor(centimes / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return (negatif ? '-' : '') + entier + ',' + String(centimes % 100).padStart(2, '0') + ' €';
    }

    /* ---------- Calcul (identique à App\Services\CalculDevis) ---------- */
    function calculer() {
        var parTaux = {};
        var brut = 0;
        var options = 0;

        liste.querySelectorAll('.ligne-devis[data-type="ligne"]').forEach(function (li) {
            var q = lireMilliemes(champ(li, 'quantite').value);
            var p = lireCentimes(champ(li, 'prix').value);
            var sortie = li.querySelector('[data-total]');
            champ(li, 'quantite').setAttribute('aria-invalid', q === null ? 'true' : 'false');
            champ(li, 'prix').setAttribute('aria-invalid', p === null && champ(li, 'prix').value !== '' ? 'true' : 'false');
            if (q === null || p === null) {
                sortie.textContent = '';
                return;
            }
            var total = arrondiDivision(q * p, 1000);
            sortie.textContent = euros(total);
            if (champ(li, 'option').checked) {
                options += total;
                return;
            }
            var t = franchise ? 0 : parseInt((champ(li, 'taux_tva') || { value: '0' }).value, 10);
            parTaux[t] = (parTaux[t] || 0) + total;
            brut += total;
        });

        var remise = 0;
        var type = formulaire.querySelector('[name="remise_type"]').value;
        var valeur = formulaire.querySelector('[name="remise"]').value;
        if (type === 'pourcentage' && lireTaux(valeur) !== null) {
            remise = arrondiDivision(brut * Math.min(lireTaux(valeur), 10000), 10000);
        } else if (type === 'montant' && lireCentimes(valeur) !== null) {
            remise = lireCentimes(valeur);
        }
        remise = Math.max(0, Math.min(remise, brut));

        var taux = Object.keys(parTaux).map(Number).sort(function (a, b) { return a - b; });
        var reste = remise;
        var tva = 0;
        taux.forEach(function (t, i) {
            var part = i === taux.length - 1 ? reste : (brut > 0 ? Math.floor((parTaux[t] * remise + Math.floor(brut / 2)) / brut) : 0);
            reste -= part;
            tva += franchise ? 0 : arrondiDivision((parTaux[t] - part) * t, 10000);
        });

        afficher('[data-total-ht]', brut - remise);
        afficher('[data-total-remise]', remise);
        afficher('[data-total-tva]', tva);
        afficher('[data-total-ttc]', brut - remise + tva);
        afficher('[data-total-options]', options);
        document.getElementById('aucune-ligne').hidden = liste.children.length > 0;
    }

    function afficher(selecteur, centimes) {
        var el = formulaire.querySelector(selecteur);
        if (el) {
            el.textContent = euros(centimes);
        }
    }

    function champ(li, nom) {
        return li.querySelector('[data-champ="' + nom + '"]') || { value: '', checked: false, setAttribute: function () {} };
    }

    /* ---------- Ajout, déplacement, suppression ---------- */
    var compteur = 1000;

    function ajouter(type, valeurs) {
        var modele = document.getElementById('modele-' + type);
        var html = modele.innerHTML.replace(/__I__/g, String(compteur++));
        var conteneur = document.createElement('div');
        conteneur.innerHTML = html.trim();
        var li = conteneur.firstElementChild;
        if (valeurs) {
            Object.keys(valeurs).forEach(function (cle) {
                var el = li.querySelector('[data-champ="' + cle + '"]');
                if (!el) {
                    return;
                }
                if (el.tagName === 'SELECT' && !Array.prototype.some.call(el.options, function (o) { return o.value === String(valeurs[cle]); })) {
                    el.add(new Option(valeurs[cle], valeurs[cle]));
                }
                el.value = valeurs[cle];
            });
        }
        liste.appendChild(li);
        calculer();
        var premier = li.querySelector('input[type="text"], textarea');
        if (premier && !valeurs) {
            premier.focus();
        }
        return li;
    }

    document.querySelectorAll('[data-ajouter]').forEach(function (bouton) {
        bouton.addEventListener('click', function () { ajouter(bouton.getAttribute('data-ajouter')); });
    });

    liste.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-action]');
        if (!bouton) {
            return;
        }
        var li = bouton.closest('.ligne-devis');
        var action = bouton.getAttribute('data-action');
        if (action === 'supprimer') {
            li.remove();
        } else if (action === 'monter' && li.previousElementSibling) {
            li.parentNode.insertBefore(li, li.previousElementSibling);
        } else if (action === 'descendre' && li.nextElementSibling) {
            li.parentNode.insertBefore(li.nextElementSibling, li);
        }
        bouton.focus();
        calculer();
    });

    formulaire.addEventListener('input', calculer);
    formulaire.addEventListener('change', calculer);

    /* Avant l'envoi : numéros des lignes dans l'ordre affiché. */
    formulaire.addEventListener('submit', function () {
        Array.prototype.forEach.call(liste.children, function (li, i) {
            li.querySelectorAll('[name^="lignes["]').forEach(function (el) {
                el.name = el.name.replace(/^lignes\[[^\]]+\]/, 'lignes[' + i + ']');
            });
        });
    });

    /* ---------- Fenêtres (catalogue, toiture) ---------- */
    document.querySelectorAll('[data-ouvrir]').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            var dialogue = document.getElementById(bouton.getAttribute('data-ouvrir'));
            dialogue.showModal();
            var saisie = dialogue.querySelector('input');
            if (saisie) {
                saisie.focus();
            }
        });
    });
    document.querySelectorAll('dialog [data-fermer]').forEach(function (bouton) {
        bouton.addEventListener('click', function () { bouton.closest('dialog').close(); });
    });

    /* Catalogue : recherche et ajout rapide. */
    var recherche = document.getElementById('recherche-catalogue');
    var resultats = document.getElementById('resultats-catalogue');
    var minuterie = null;

    function chercher() {
        fetch(formulaire.getAttribute('data-recherche') + '?q=' + encodeURIComponent(recherche.value), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (prestations) {
            resultats.innerHTML = '';
            if (!prestations.length) {
                var vide = document.createElement('li');
                vide.textContent = 'Aucune prestation trouvée.';
                resultats.appendChild(vide);
                return;
            }
            prestations.forEach(function (p) {
                var li = document.createElement('li');
                var bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'liste-lien bouton-resultat';
                var nom = document.createElement('span');
                nom.className = 'libelle';
                nom.textContent = p.nom;
                var prix = document.createElement('small');
                prix.className = 'bloc texte-doux';
                prix.textContent = p.prix_affiche;
                nom.appendChild(prix);
                bouton.appendChild(nom);
                bouton.addEventListener('click', function () {
                    ajouter('ligne', {
                        designation: p.nom,
                        description: p.description || '',
                        unite: p.unite,
                        prix: p.prix_ht === null ? '' : (p.prix_ht / 100).toFixed(2).replace('.', ','),
                        taux_tva: p.taux_tva,
                        prestation_id: p.id,
                        quantite: '1'
                    });
                    recherche.closest('dialog').close();
                });
                li.appendChild(bouton);
                resultats.appendChild(li);
            });
        }).catch(function () {
            resultats.innerHTML = '';
            var erreur = document.createElement('li');
            erreur.textContent = 'Recherche impossible (pas de connexion ?).';
            resultats.appendChild(erreur);
        });
    }

    if (recherche) {
        recherche.addEventListener('input', function () {
            clearTimeout(minuterie);
            minuterie = setTimeout(chercher, 250);
        });
        document.querySelector('[data-ouvrir="dialogue-catalogue"]').addEventListener('click', chercher);
    }

    /* Calcul de surface de toiture selon la pente : rampant = sol / cos(pente). */
    var sol = document.getElementById('toiture-sol');
    if (sol) {
        var pente = document.getElementById('toiture-pente');
        var uniteToiture = document.getElementById('toiture-unite');
        var resultat = document.getElementById('toiture-resultat');
        var utiliser = document.getElementById('toiture-utiliser');
        var surface = null;

        var calculerToiture = function () {
            var s = parseFloat(nettoyer(sol.value).replace(',', '.'));
            var p = parseFloat(nettoyer(pente.value).replace(',', '.'));
            surface = null;
            if (isNaN(s) || isNaN(p)) {
                resultat.textContent = '';
            } else {
                var degres = uniteToiture.value === 'pourcentage' ? Math.atan(p / 100) * 180 / Math.PI : p;
                if (degres < 0 || degres >= 90) {
                    resultat.textContent = 'La pente doit être entre 0 et 90°.';
                } else {
                    surface = Math.round(s / Math.cos(degres * Math.PI / 180) * 100) / 100;
                    resultat.textContent = 'Surface du toit : ' + surface.toFixed(2).replace('.', ',') + ' m² (pente ' + degres.toFixed(1).replace('.', ',') + '°)';
                }
            }
            utiliser.disabled = surface === null;
        };

        [sol, pente, uniteToiture].forEach(function (el) { el.addEventListener('input', calculerToiture); });
        uniteToiture.addEventListener('change', calculerToiture);
        utiliser.addEventListener('click', function () {
            ajouter('ligne', { designation: 'Surface de toiture', quantite: surface.toFixed(2).replace('.', ','), unite: 'm²' });
            utiliser.closest('dialog').close();
        });
    }

    calculer();
})();
