#!/usr/bin/env bash
# Prépare la DÉMONSTRATION dans GitHub Codespaces : base SQLite neuve et données fictives.
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-interaction --prefer-dist --quiet
[ -f .env ] || cp .env.example .env

ADRESSE="http://localhost:8000"
if [ -n "${CODESPACE_NAME:-}" ]; then
    ADRESSE="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
fi
sed -i -E "s|^APP_URL=.*|APP_URL=${ADRESSE}|" .env
grep -q '^APP_FORCER_URL=' .env || echo 'APP_FORCER_URL=true' >> .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force --quiet

rm -f database/database.sqlite && touch database/database.sqlite
php artisan migrate --force --quiet
php artisan app:demo --no-interaction > storage/app/identifiants-demo.txt
chmod 600 storage/app/identifiants-demo.txt
