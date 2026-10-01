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
php artisan app:demo           # démonstration : entreprise et comptes fictifs
php artisan serve
```

Ce dépôt sert à la **démonstration** : `php artisan app:demo` installe une entreprise
fictive (« Couverture Démo ») et deux comptes (`demo@exemple.test` gérant,
`commercial@exemple.test` commercial) avec des mots de passe aléatoires affichés une
seule fois. Un bandeau « Démonstration » apparaît sur toutes les pages. La commande
refuse de tourner si `APP_ENV=production`.

Pour une vraie entreprise : ne pas lancer `app:demo`, créer le gérant avec
`php artisan app:creer-gerant` (mot de passe demandé sans être affiché).

## Tâches planifiées

Une seule ligne de cron (cPanel), chaque minute :

```
* * * * * cd /chemin/de/l/application && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test                                        # SQLite
vendor/bin/phpunit -c phpunit.mariadb.xml              # MariaDB (base de test locale)
vendor/bin/pint --test                                  # format du code
NODE_PATH=$(npm root -g) node outils/verif-mobile.mjs http://127.0.0.1:8000 email motdepasse captures
NODE_PATH=$(npm root -g) node outils/verif-configuration.mjs http://127.0.0.1:8000 email motdepasse captures  # base neuve
```

`phpunit.mariadb.xml` utilise une base **de test** locale `app_test` (utilisateur et mot
de passe `app_test`), créée seulement sur le poste de développement ou dans GitHub Actions.

## Avancement

- [x] Étape 1 — Socle : connexion, mot de passe oublié, limitation des essais, alerte nouvel
  appareil, mise en page mobile, thème clair/sombre, grands boutons, pages d'erreur,
  application installable, journal, corbeille, commande de création du gérant.
- [x] Données de démonstration fictives (`app:demo`).
- [x] Étape 2 — Réglages : entreprise (SIRET contrôlé), apparence (logo, icône, couleurs,
  police), TVA (franchise / assujetti, n° intracommunautaire contrôlé), numérotation continue
  sans trou, documents (IBAN/BIC contrôlés, déchets, CGV), assurance décennale (alerte avant
  échéance), emails Gmail (mot de passe chiffré, email de test), modèles d'emails, textes types.
- [x] Étape 3 — Menu de configuration au premier lancement (9 écrans, barre de progression,
  reprise, retour arrière, devis PDF d'exemple). Le gérant peut le passer pour l'instant :
  l'application s'ouvre avec un bandeau, et les pages destinées aux clients restent coupées
  jusqu'à la fin. Un commercial voit « en cours de configuration par le gérant ».
