/* Signature au doigt dans un cadre (Pointer Events), envoyée en image PNG. */
(function () {
    'use strict';

    var toile = document.getElementById('zone-signature');
    if (!toile) {
        return;
    }
    var ctx = toile.getContext('2d');
    var champ = document.getElementById('signature-donnees');
    var dessine = false;
    var enCours = false;

    function preparer() {
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1b1d21';
    }

    function position(e) {
        var r = toile.getBoundingClientRect();
        return { x: (e.clientX - r.left) * toile.width / r.width, y: (e.clientY - r.top) * toile.height / r.height };
    }

    preparer();

    toile.addEventListener('pointerdown', function (e) {
        enCours = true;
        try {
            toile.setPointerCapture(e.pointerId);
        } catch (erreur) { /* certains navigateurs refusent : le dessin marche quand même */ }
        var p = position(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        e.preventDefault();
    });
    toile.addEventListener('pointermove', function (e) {
        if (!enCours) {
            return;
        }
        var p = position(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        dessine = true;
        e.preventDefault();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) {
        toile.addEventListener(type, function () { enCours = false; });
    });

    document.getElementById('effacer-signature').addEventListener('click', function () {
        ctx.clearRect(0, 0, toile.width, toile.height);
        dessine = false;
        champ.value = '';
    });

    document.getElementById('formulaire-signature').addEventListener('submit', function () {
        champ.value = dessine ? toile.toDataURL('image/png') : '';
    });
})();
