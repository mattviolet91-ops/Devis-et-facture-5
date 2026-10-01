# Installer l'application sur un hébergement cPanel (o2switch ou autre)

Ce guide se suit depuis un téléphone : chaque commande est courte, à copier puis coller
dans le **Terminal** de cPanel. Rien n'est à taper de mémoire.

> Une entreprise = une installation = une base de données **neuve et vide**.
> Ne copiez jamais la base ni le fichier `.env` d'une autre entreprise.
> N'installez jamais les données de démonstration (`app:demo`) chez une vraie entreprise.

## Ce qu'il faut avant de commencer

- Un hébergement cPanel avec PHP 8.3, MariaDB (ou MySQL), le Terminal et Git.
- Un sous-domaine pour l'application, par exemple `gestion.mondomaine.fr`.
- Facultatif : un second sous-domaine pour les clients, par exemple `devis.mondomaine.fr`.

## 1. Préparer cPanel (une fois)

1. **Sélecteur de version PHP** (o2switch : « Select PHP Version ») : choisir **8.3**,
   cocher les extensions `gd`, `zip`, `mbstring`, `pdo_mysql`, `intl`, `exif`.
2. **Domaines** : créer le sous-domaine `gestion.mondomaine.fr`.
   Sa racine sera réglée à l'étape 4 (dossier `public` de l'application).
3. **SSL/TLS Status** (ou AutoSSL / Let's Encrypt) : activer le certificat du sous-domaine.

## 2. Récupérer le code

Dans **Terminal** :

```bash
cd ~
git clone https://github.com/VOTRE-COMPTE/VOTRE-DEPOT.git gestion
cd gestion
composer install --no-dev --optimize-autoloader
```

## 3. Créer la base et le fichier `.env`

Le plus simple : le script de l'étape « Plusieurs entreprises » (voir plus bas) fait tout
d'un coup. À la main :

1. cPanel → **Bases de données MySQL** : créer une base, un utilisateur avec un mot de
   passe **généré** (bouton « Générateur de mot de passe »), et donner **tous les
   privilèges** de cet utilisateur sur cette base.
2. Dans le Terminal :

```bash
cp .env.example .env
nano .env
```

Changer ces lignes (puis `Ctrl+O`, `Entrée`, `Ctrl+X`) :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestion.mondomaine.fr
APP_CLIENT_URL=https://devis.mondomaine.fr
DB_CONNECTION=mariadb
DB_HOST=localhost
DB_DATABASE=nom_de_la_base
DB_USERNAME=nom_de_l_utilisateur
DB_PASSWORD=mot_de_passe_genere
```

3. Puis :

```bash
chmod 600 .env
php artisan key:generate
php artisan migrate --force
php artisan app:creer-gerant
```

`app:creer-gerant` demande l'email et le mot de passe du gérant (le mot de passe ne
s'affiche pas pendant la saisie).

## 4. Pointer le sous-domaine vers `public`

cPanel → **Domaines** → `gestion.mondomaine.fr` → **Gérer** → racine du document :
`gestion/public`. Faire de même pour `devis.mondomaine.fr` si vous l'utilisez.

## 5. Tâches automatiques (cron)

cPanel → **Tâches Cron**, ajouter :

| Fréquence | Commande |
|---|---|
| Chaque minute (`* * * * *`) | `cd ~/gestion && php artisan schedule:run >> /dev/null 2>&1` |
| Toutes les 15 minutes (`*/15 * * * *`), facultatif | `~/gestion/outils/deploy.sh main >> /dev/null 2>&1` |

La première ligne lance tout le reste : sauvegardes (2 h 30), rappels du planning,
météo, relances, lecture des emails du site, statistiques…

La seconde installe automatiquement les nouvelles versions publiées sur Git : sauvegarde,
mise à jour, migrations, caches, retour à la version d'avant en cas d'échec, et alerte
« Application mise à jour » au gérant. Journal : `storage/logs/deploiement.log`.

## 6. Vérifier

```bash
php artisan app:security-check --http
```

Chaque ligne doit être cochée ✓. La ligne « Sauvegarde de moins de 2 jours » passe au vert
après la première nuit (ou tout de suite avec `php artisan app:sauvegarder`).

Ouvrez ensuite `https://gestion.mondomaine.fr` : le menu de configuration démarre.

## Sauvegardes

- Base de données chaque nuit, fichiers (photos, PDF) le 1er du mois, dans
  `storage/app/private/sauvegardes` : 30 sauvegardes de la base, 12 des fichiers.
- Téléchargement : Réglages → Sauvegardes.
- **Gardez une copie hors du serveur** chaque mois (ordinateur, clé USB). Elle s'ajoute aux
  sauvegardes de l'hébergeur (JetBackup chez o2switch).

### Remettre une sauvegarde

```bash
cd ~/gestion
php artisan app:restaurer base-2026-10-02-023000.json.gz
```

La commande demande confirmation et sauvegarde d'abord l'état actuel. Toutes les données
actuelles sont remplacées par celles de la sauvegarde. Pour les fichiers, décompresser
l'archive `fichiers-….zip` dans `storage/app/private/`.

## Mettre à jour à la main

```bash
cd ~/gestion
outils/deploy.sh main
```

## En cas de souci

- Page blanche ou erreur 500 : `tail -n 50 storage/logs/laravel-$(date +%F).log`
- Droits des dossiers : `chmod -R u+rwX storage bootstrap/cache`
- Vider les caches après une modification du `.env` : `php artisan optimize:clear && php artisan config:cache`
