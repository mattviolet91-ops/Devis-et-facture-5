/* Chargé dans <head> avant l'affichage : applique le thème et les grands boutons
   choisis sur ce téléphone, pour éviter un flash de couleurs. */
(function () {
    var racine = document.documentElement;
    try {
        var theme = localStorage.getItem('theme');
        if (theme === 'clair' || theme === 'sombre') {
            racine.setAttribute('data-theme', theme);
        }
        if (localStorage.getItem('grands-boutons') === '1') {
            racine.setAttribute('data-grands-boutons', '1');
        }
    } catch (e) {
        /* Stockage indisponible (navigation privée) : réglages par défaut. */
    }
})();
