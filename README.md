# Gestion pour artisan du bâtiment

## ▶ Voir l'application

- **Démo à regarder, sans rien installer** (données fictives, sur téléphone ou ordinateur) :
  **https://mattviolet91-ops.github.io/Devis-et-facture-5/docs/demo/**
  On peut tout parcourir : accueil, clients, devis, factures, planning, photos, statistiques,
  guide, et les pages que voit le client. Les boutons qui enregistrent ne font rien.
- **Essayer pour de vrai, directement sur GitHub** (compte GitHub gratuit) :
  [Ouvrir dans GitHub Codespaces](https://codespaces.new/mattviolet91-ops/Devis-et-facture-5?quickstart=1).
  L'application démarre toute seule avec une entreprise fictive ; le terminal affiche les
  comptes de démonstration (gérant et commercial). Le premier lancement prend quelques minutes.


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
NODE_PATH=$(npm root -g) node outils/verif-hors-connexion.mjs http://127.0.0.1:8000 email motdepasse
NODE_PATH=$(npm root -g) node outils/captures-guide.mjs http://127.0.0.1:8000 email motdepasse   # démonstration seulement
NODE_PATH=$(npm root -g) node outils/verif-parcours.mjs http://127.0.0.1:8000 gerant mdp commercial mdp   # parcours complet au doigt
NODE_PATH=$(npm root -g) AXE=axe.min.js node outils/verif-complete.mjs http://127.0.0.1:8000 gerant mdp dossier   # toutes les pages
outils/generer-demo-statique.sh                                  # refait la démo à regarder (docs/demo)
NODE_PATH=$(npm root -g) node outils/verif-demo-statique.mjs http://127.0.0.1:8200/docs/demo/   # avec python3 -m http.server 8200
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
- [x] Étape 4 — Clients et chantiers : particulier / professionnel, provenance, alerte de doublon,
  plusieurs adresses de chantier, recherche sans accents, import CSV, notes et pièces jointes.
- [x] Étape 5 — Catalogue de prestations (départ par métier, sans prix).
- [x] Étape 6 — Devis (sections, options, remise, versions) et devis express en une phrase.
- [x] Étape 7 — PDF (mPDF) et visionneuse intégrée (pdf.js).
- [x] Étape 8 — Lien client et signature (sur place ou à distance, demande de modification).
- [x] Étape 9 — Factures, acomptes, situations, soldes, avoirs (numérotation continue).
- [x] Étape 10 — Encaissements inaltérables et paiement par carte myPOS.
- [x] Étape 11 — Emails (modèles), SMS, WhatsApp, message prêt à copier.
- [x] Étape 12 — Planning (semaine, mois, .ics, rappels, météo en cache, notifications sur le téléphone).
- [x] Étape 13 — Photos (catégories, dessin, annexe des PDF) et rapports d'intervention.
- [x] Étape 14 — Suivi commercial : relances de devis, avis Google, entretien, formulaire public,
  lecture des emails du site WordPress.
- [x] Étape 15 — Accueil personnalisable, barre d'actions, glisser pour agir, statistiques, frais ;
  15 bis : compteur du site (s.js) et statistiques Jetpack.
- [x] Étape 16 — Comptes : invitation, désactivation immédiate, droits du commercial.
- [x] Étape 17 — Devis avec Claude : clés d'accès et API `/api/v1` (brouillons seulement).
- [x] Étape 18 — Guide intégré, bouton « ? », liste « Bien démarrer », astuce du jour.
- [x] Étape 19 — Hors connexion (service worker).
- [x] Étape 20 — Sauvegardes et restauration, `app:security-check`, `outils/deploy.sh`,
  [guide d'installation cPanel](docs/INSTALLATION-CPANEL.md).
- [x] Étape 21 — Nouvelle entreprise en une commande : `outils/installer-entreprise.sh`
  (base neuve et vide, clé propre, refus si la base contient des tables).
