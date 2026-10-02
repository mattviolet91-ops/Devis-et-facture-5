// Formulaire du planning : l'heure et la date de fin suivent le début (même durée).
(function () {
    'use strict';

    var dateDebut = document.getElementById('date_debut');
    var heureDebut = document.getElementById('heure_debut');
    var dateFin = document.getElementById('date_fin');
    var heureFin = document.getElementById('heure_fin');
    if (!dateDebut || !heureDebut || !dateFin || !heureFin) { return; }

    function minutes(texte) {
        var m = /^(\d{2}):(\d{2})/.exec(texte || '');
        return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
    }
    function heure(total) {
        total = Math.min(total, 23 * 60 + 59);
        return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
    }

    var ancienDebut = dateDebut.value;
    var duree = (minutes(heureFin.value) !== null && minutes(heureDebut.value) !== null) ? Math.max(15, minutes(heureFin.value) - minutes(heureDebut.value)) : 60;

    function surChangement(champ, action) {
        champ.addEventListener('change', action);
        champ.addEventListener('input', action);
    }

    surChangement(dateDebut, function () {
        // La date de fin suit la date de début si elles étaient identiques (ou si la fin est avant).
        if (!dateFin.value || dateFin.value === ancienDebut || dateFin.value < dateDebut.value) { dateFin.value = dateDebut.value; }
        ancienDebut = dateDebut.value;
    });
    surChangement(heureDebut, function () {
        var debut = minutes(heureDebut.value);
        if (debut === null) { return; }
        if (dateFin.value === dateDebut.value || !dateFin.value) { heureFin.value = heure(debut + duree); }
    });
    surChangement(heureFin, function () {
        var debut = minutes(heureDebut.value);
        var fin = minutes(heureFin.value);
        if (debut !== null && fin !== null && fin > debut) { duree = fin - debut; }
    });
})();
