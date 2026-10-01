#!/usr/bin/env bash
# Refait la démo à regarder (docs/demo, publiée par GitHub Pages) à partir des données FICTIVES.
# Utilise une base SQLite temporaire : aucune autre base n'est touchée.
#   outils/generer-demo-statique.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8124}"
TEMP="$(mktemp -d)"
export DB_CONNECTION=sqlite DB_DATABASE="$TEMP/demo.sqlite" APP_ENV=local APP_DEBUG=false APP_URL="http://127.0.0.1:$PORT" MAIL_MAILER=log
touch "$DB_DATABASE"

php artisan migrate --force --quiet
MOTDEPASSE="$(php artisan app:demo --no-interaction | grep 'demo@' | awk -F'|' '{print $4}' | tr -d ' ')"
LIENS="$(php artisan tinker --execute '
$d = App\Models\Devis::where("statut", "envoye")->first();
$f = App\Models\Facture::where("statut", "emise")->where("type", "!=", "avoir")->whereNotNull("numero")->latest("id")->first();
$r = App\Models\Rapport::first();
echo collect([$d, $f, $r])->filter()->map(fn ($m) => App\Models\LienClient::pour($m)->url())->implode(" ");' | tail -1)"

php artisan serve --host=127.0.0.1 --port="$PORT" > "$TEMP/serveur.log" 2>&1 &
SERVEUR=$!
trap 'kill $SERVEUR 2>/dev/null; rm -rf "$TEMP"' EXIT
for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/connexion" && break; sleep 0.5; done

# shellcheck disable=SC2086
NODE_PATH="$(npm root -g)" node outils/exporter-demo.mjs "http://127.0.0.1:$PORT" demo@exemple.test "$MOTDEPASSE" docs/demo $LIENS
