# Règles permanentes du projet

- Interface, messages et emails entièrement en français, mots simples d'artisan.
- Ne rien supprimer ni modifier sur le site, l'hébergement, le domaine ou la base de production sans accord.
- Aucune donnée d'entreprise dans le code : tout vient des Réglages (`reglage('cle')`, table `settings`).
  Les valeurs par défaut de `config/entreprise.php` restent VIDES (un test le vérifie).
- Le nom du dirigeant n'apparaît jamais (ni documents, ni valeurs par défaut, ni données de test) : seul le nom commercial.
- Base 100 % neuve par entreprise : pas de base partagée, pas d'import d'une autre entreprise, aucune donnée de démonstration en production (`DatabaseSeeder` reste vide).
- Aucun mot de passe ni clé en clair dans le code, les journaux ou la conversation. Secrets chiffrés en base.
- Mobile d'abord (390 px), clair et sombre, accessible (contrastes, focus visible, libellés).
- CSP stricte `script-src 'self'` : aucun `<script>` inline, aucun `onclick=`, aucun `style=` (testé dans `SecuriteTest`).
- Montants en centimes (entiers).
- Chaque fonctionnalité a ses tests ; ils passent sur SQLite ET MariaDB ; `Http::preventStrayRequests()` est actif (voir `tests/TestCase.php`).
- Code formaté avec `vendor/bin/pint`.
- Avant chaque envoi : tests, relecture du diff, vérification Playwright (`outils/verif-mobile.mjs`).
- Pages avec bouton Retour : `@section('parent', route(...))` dans la vue.
- Suppression douce : enregistrer le modèle dans `App\Support\Corbeille` (jamais les factures émises).
- Journal : `App\Support\Journal::ecrire()` — jamais de secret dedans.
