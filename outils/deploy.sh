#!/usr/bin/env bash
# Mise à jour automatique depuis Git, lancée par le cron (par exemple toutes les 15 minutes) :
#   */15 * * * * /chemin/de/l/application/outils/deploy.sh main >> /dev/null 2>&1
#
# Étapes : récupération du code, sauvegarde de la base, composer, migrations, caches.
# En cas d'échec : retour au code d'avant et alerte au gérant. Journal : storage/logs/deploiement.log
set -euo pipefail

cd "$(dirname "$0")/.."
BRANCHE="${1:-main}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
JOURNAL="storage/logs/deploiement.log"

journal() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$JOURNAL"; }

# Une seule mise à jour à la fois.
exec 9> storage/framework/deploiement.verrou
flock -n 9 || exit 0

# Fichiers modifiés à la main sur le serveur : on ne touche à rien.
if ! git diff --quiet || ! git diff --cached --quiet; then
    journal "Mise à jour arrêtée : des fichiers ont été modifiés sur le serveur (git status)."
    exit 1
fi

git fetch --quiet origin "$BRANCHE"
AVANT="$(git rev-parse HEAD)"
APRES="$(git rev-parse "origin/$BRANCHE")"
[ "$AVANT" = "$APRES" ] && exit 0

# Version déjà essayée sans succès : on attend la suivante (pas d'alerte à chaque passage).
ECHEC="storage/framework/deploiement.echec"
[ -f "$ECHEC" ] && [ "$(cat "$ECHEC")" = "$APRES" ] && exit 0

retour_arriere() {
    trap - ERR
    journal "Échec : retour au code d'avant (${AVANT:0:7})."
    echo "$APRES" > "$ECHEC"
    git reset --hard --quiet "$AVANT"
    "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet || true
    "$PHP" artisan config:cache --quiet || true
    "$PHP" artisan route:cache --quiet || true
    "$PHP" artisan view:cache --quiet || true
    "$PHP" artisan up --quiet || true
    "$PHP" artisan app:mise-a-jour --echec --quiet || true
    exit 1
}
trap retour_arriere ERR

journal "Mise à jour ${AVANT:0:7} → ${APRES:0:7}"
"$PHP" artisan app:sauvegarder --quiet
"$PHP" artisan down --retry=30 --quiet
git merge --ff-only --quiet "origin/$BRANCHE"
"$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet
"$PHP" artisan migrate --force --quiet
"$PHP" artisan config:cache --quiet
"$PHP" artisan route:cache --quiet
"$PHP" artisan view:cache --quiet
"$PHP" artisan up --quiet
trap - ERR

"$PHP" artisan app:mise-a-jour --quiet || true
rm -f "$ECHEC"
journal "Mise à jour terminée."
