# Gestion pour artisan du bâtiment

Application web (mobile d'abord) pour une petite entreprise artisanale du bâtiment :
clients, devis, factures, paiements, planning, photos.

Pile : PHP 8.3, Laravel 12, Blade, JavaScript sans framework (`public/js`), CSS maison
(`public/css`). Aucune compilation Node en production. MariaDB/MySQL en production,
SQLite pour les tests. Prévu pour un hébergement mutualisé cPanel (cron, files synchrones).

> Aucune information d'entreprise n'est écrite dans le code : tout se saisit dans
> l'application. Chaque entreprise a sa propre installation et sa propre base vide.

## Démarrer en local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:creer-gerant   # demande l'email et le mot de passe (non affiché)
php artisan serve
```

## Tâches planifiées

Une seule ligne de cron (cPanel), chaque minute :

```
* * * * * cd /chemin/de/l/application && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test                                        # SQLite
php artisan test --configuration=phpunit.mariadb.xml    # MariaDB (base de test locale)
vendor/bin/pint --test                                  # format du code
NODE_PATH=$(npm root -g) node outils/verif-mobile.mjs http://127.0.0.1:8000 email motdepasse captures
```

`phpunit.mariadb.xml` utilise une base **de test** locale `app_test` (utilisateur et mot
de passe `app_test`), créée seulement sur le poste de développement ou dans GitHub Actions.

## Avancement

- [x] Étape 1 — Socle : connexion, mot de passe oublié, limitation des essais, alerte nouvel
  appareil, mise en page mobile, thème clair/sombre, grands boutons, pages d'erreur,
  application installable, journal, corbeille, commande de création du gérant.
