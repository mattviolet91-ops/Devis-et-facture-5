#!/usr/bin/env bash
# Lance l'application de démonstration et rappelle les comptes fictifs.
cd "$(dirname "$0")/.."
echo
echo "=== Démonstration : comptes fictifs ==="
grep -E "Gérant|Commercial" storage/app/identifiants-demo.txt 2>/dev/null || echo "(lancez : bash .devcontainer/preparer.sh)"
echo "Ouvrez l'onglet « Ports » → « Application » (port 8000) si la page ne s'ouvre pas toute seule."
echo
pkill -f "artisan serve" 2>/dev/null || true
php artisan schedule:work > storage/logs/taches.log 2>&1 &
php artisan serve --host=0.0.0.0 --port=8000
