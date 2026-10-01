// Dessin à main levée sur une photo (cercles, flèches), enregistré en JPEG.
(function () {
    'use strict';

    var toile = document.getElementById('toile-annotation');
    var formulaire = document.getElementById('formulaire-annotation');
    if (!toile || !formulaire) { return; }

    var contexte = toile.getContext('2d');
    var image = new Image();
    var traits = [];
    var actuel = null;

    function couleur() {
        var choix = document.querySelector('input[name="couleur"]:checked');
        return choix ? choix.value : '#e00000';
    }

    function dessiner() {
        contexte.drawImage(image, 0, 0, toile.width, toile.height);
        contexte.lineCap = 'round';
        contexte.lineJoin = 'round';
        contexte.lineWidth = Math.max(4, Math.round(toile.width / 120));
        traits.forEach(function (trait) {
            if (trait.points.length < 2) { return; }
            contexte.strokeStyle = trait.couleur;
            contexte.beginPath();
            contexte.moveTo(trait.points[0][0], trait.points[0][1]);
            for (var i = 1; i < trait.points.length; i++) { contexte.lineTo(trait.points[i][0], trait.points[i][1]); }
            contexte.stroke();
        });
    }

    function position(evenement) {
        var cadre = toile.getBoundingClientRect();
        return [
            (evenement.clientX - cadre.left) * toile.width / cadre.width,
            (evenement.clientY - cadre.top) * toile.height / cadre.height
        ];
    }

    image.addEventListener('load', dessiner);
    image.src = toile.getAttribute('data-image');

    toile.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        try { toile.setPointerCapture(e.pointerId); } catch (erreur) { /* navigateurs anciens */ }
        actuel = { couleur: couleur(), points: [position(e)] };
        traits.push(actuel);
    });
    toile.addEventListener('pointermove', function (e) {
        if (!actuel) { return; }
        actuel.points.push(position(e));
        dessiner();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (nom) {
        toile.addEventListener(nom, function () { actuel = null; });
    });

    document.querySelector('[data-annuler-trait]').addEventListener('click', function () { traits.pop(); dessiner(); });
    document.querySelector('[data-tout-effacer]').addEventListener('click', function () { traits = []; dessiner(); });

    formulaire.addEventListener('submit', function (e) {
        var champ = formulaire.querySelector('input[type="file"]');
        if (champ.files.length) { return; }
        e.preventDefault();
        toile.toBlob(function (blob) {
            var transfert = new DataTransfer();
            transfert.items.add(new File([blob], 'annotation.jpg', { type: 'image/jpeg' }));
            champ.files = transfert.files;
            formulaire.submit();
        }, 'image/jpeg', 0.9);
    });
})();
