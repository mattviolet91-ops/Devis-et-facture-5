#!/usr/bin/env bash
# Installe l'application pour une NOUVELLE entreprise, en une commande :
#   outils/installer-entreprise.sh <dossier> <adresse https> [--depot URL] [--branche main] [--mysql]
# Exemple (cPanel, depuis le dossier d'une installation existante ou d'un clone du dépôt) :
#   outils/installer-entreprise.sh gestion-dupont https://gestion.dupont-couverture.fr
#
# Ce que fait le script :
#   1. copie le code dans ~/<dossier> (git clone) et installe les dépendances ;
#   2. crée une base de données NEUVE et un utilisateur dédié avec un mot de passe aléatoire
#      (cPanel : commande uapi ; --mysql : client mysql avec les droits d'administration) ;
#   3. refuse de continuer si la base contient déjà des tables ;
#   4. écrit le .env (production, clé de chiffrement propre à cette installation) ;
#   5. construit les tables avec les migrations, crée le compte gérant, ajoute le cron.
# Jamais de copie d'une autre base, jamais de données de démonstration.
set -euo pipefail

erreur() { echo "✗ $*" >&2; exit 1; }
etape() { echo "→ $*"; }

[ $# -ge 2 ] || erreur "Usage : $0 <dossier> <adresse https> [--depot URL] [--branche main] [--mysql]"
DOSSIER="$1"; ADRESSE="${2%/}"; shift 2
DEPOT="$(git -C "$(dirname "$0")/.." remote get-url origin 2>/dev/null || true)"
BRANCHE="main"; MODE="cpanel"; PHP="${PHP:-php}"; COMPOSER="${COMPOSER:-composer}"
while [ $# -gt 0 ]; do
    case "$1" in
        --depot) DEPOT="$2"; shift 2 ;;
        --branche) BRANCHE="$2"; shift 2 ;;
        --mysql) MODE="mysql"; shift ;;
        *) erreur "Option inconnue : $1" ;;
    esac
done

[[ "$DOSSIER" =~ ^[a-z0-9][a-z0-9-]{1,30}$ ]] || erreur "Nom de dossier : lettres minuscules, chiffres et tirets (2 à 31 caractères)."
[[ "$ADRESSE" =~ ^https://[a-z0-9.-]+$ ]] || erreur "L'adresse doit commencer par https:// (exemple : https://gestion.mondomaine.fr)."
[ -n "$DEPOT" ] || erreur "Indiquez le dépôt Git avec --depot."
CIBLE="${INSTALL_RACINE:-$HOME}/$DOSSIER"
[ ! -e "$CIBLE" ] || erreur "Le dossier $CIBLE existe déjà : rien n'a été touché."

# Nom de la base et de son utilisateur : préfixe du compte cPanel + nom du dossier.
PREFIXE="${DB_PREFIXE:-${USER}_}"
SUFFIXE="$(echo "$DOSSIER" | tr -d '-' | cut -c1-16)"
BASE="${PREFIXE}${SUFFIXE}"
UTILISATEUR="${PREFIXE}${SUFFIXE}"
MOTDEPASSE="$("$PHP" -r 'echo bin2hex(random_bytes(16));')"

etape "Code dans $CIBLE"
git clone --quiet --branch "$BRANCHE" "$DEPOT" "$CIBLE"
cd "$CIBLE"
"$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet

etape "Base de données neuve : $BASE"
case "$MODE" in
    cpanel)
        command -v uapi >/dev/null || erreur "Commande uapi absente : utilisez --mysql, ou créez la base dans cPanel (voir docs/INSTALLATION-CPANEL.md)."
        uapi --output=json Mysql create_database name="$BASE" | grep -q '"status":1' || erreur "La base $BASE n'a pas pu être créée (existe-t-elle déjà ?). Rien d'autre n'a été fait."
        uapi --output=json Mysql create_user name="$UTILISATEUR" password="$MOTDEPASSE" | grep -q '"status":1' || erreur "L'utilisateur $UTILISATEUR n'a pas pu être créé."
        uapi --output=json Mysql set_privileges_on_database user="$UTILISATEUR" database="$BASE" privileges=ALL | grep -q '"status":1' || erreur "Les droits n'ont pas pu être donnés."
        HOTE="localhost"
        ;;
    mysql)
        HOTE="${DB_HOTE:-127.0.0.1}"
        mysql ${MYSQL_ADMIN_OPTIONS:-} -e "CREATE DATABASE \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || erreur "La base $BASE n'a pas pu être créée (existe-t-elle déjà ?). Rien d'autre n'a été fait."
        mysql ${MYSQL_ADMIN_OPTIONS:-} -e "CREATE USER '$UTILISATEUR'@'%' IDENTIFIED BY '$MOTDEPASSE'; GRANT ALL PRIVILEGES ON \`$BASE\`.* TO '$UTILISATEUR'@'%'; FLUSH PRIVILEGES;" || erreur "L'utilisateur $UTILISATEUR n'a pas pu être créé."
        ;;
esac

etape "Fichier .env (production, clé propre à cette installation)"
cp .env.example .env
chmod 600 .env
remplacer() { # remplacer CLE valeur : décommente ou ajoute la ligne
    if grep -qE "^#? ?$1=" .env; then sed -i -E "s|^#? ?$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
remplacer APP_ENV production
remplacer APP_DEBUG false
remplacer APP_URL "$ADRESSE"
remplacer DB_CONNECTION mariadb
remplacer DB_HOST "$HOTE"
remplacer DB_PORT 3306
remplacer DB_DATABASE "$BASE"
remplacer DB_USERNAME "$UTILISATEUR"
remplacer DB_PASSWORD "$MOTDEPASSE"
unset MOTDEPASSE
"$PHP" artisan key:generate --force --quiet

etape "Contrôle : la base doit être vide"
"$PHP" artisan app:base-neuve || erreur "Installation arrêtée. Le dossier $CIBLE peut être supprimé ; la base n'a pas été modifiée."

etape "Tables"
"$PHP" artisan migrate --force --quiet
chmod -R u+rwX storage bootstrap/cache

etape "Compte gérant"
if [ -t 0 ]; then "$PHP" artisan app:creer-gerant; else echo "  (à faire : cd $CIBLE && php artisan app:creer-gerant)"; fi

etape "Tâche automatique (cron)"
LIGNE="* * * * * cd $CIBLE && $PHP artisan schedule:run >> /dev/null 2>&1"
if [ "${SANS_CRON:-0}" = "1" ]; then
    echo "  (à ajouter : $LIGNE)"
elif crontab -l 2>/dev/null | grep -qF "cd $CIBLE &&"; then
    echo "  déjà présente"
else
    (crontab -l 2>/dev/null; echo "$LIGNE") | crontab -
fi

echo
echo "✓ Installation terminée dans $CIBLE"
echo "  Il reste à pointer le sous-domaine vers $CIBLE/public (cPanel → Domaines),"
echo "  puis à ouvrir $ADRESSE : le menu de configuration démarre."
echo "  Vérification : cd $CIBLE && php artisan app:security-check --http"
