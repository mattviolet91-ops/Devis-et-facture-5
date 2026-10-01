#!/usr/bin/env bash
# Vérifications avant chaque envoi : format du code, tests SQLite, tests MariaDB.
# S'arrête au premier problème.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "→ Format du code (Pint)"
vendor/bin/pint --test

echo "→ Tests SQLite"
php artisan test

if [ "${SANS_MARIADB:-0}" != "1" ]; then
    echo "→ Tests MariaDB"
    vendor/bin/phpunit -c phpunit.mariadb.xml
fi

echo "✓ Tout est bon."
