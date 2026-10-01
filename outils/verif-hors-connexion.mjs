// Vérifie le mode hors connexion sur un téléphone simulé.
// Usage : NODE_PATH=$(npm root -g) node outils/verif-hors-connexion.mjs http://127.0.0.1:8123 email motdepasse
import { createRequire } from 'node:module';

// Pour que Playwright voie (et coupe) aussi les requêtes du service worker.
process.env.PW_EXPERIMENTAL_SERVICE_WORKER_NETWORK_EVENTS = '1';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse] = process.argv.slice(2);
const erreurs = [];
// Coupe vraiment le réseau (y compris pour le service worker), ou le rétablit.
let coupe = false;
const reseau = async (contexte, actif) => {
    coupe = !actif;
    await contexte.setOffline(!actif);
};
const verifier = (condition, message) => { if (!condition) erreurs.push(message); };

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fr-FR' });
await contexte.route('**/*', (route) => (coupe ? route.abort('internetdisconnected') : route.continue()));
const page = await contexte.newPage();
page.on('pageerror', (e) => erreurs.push('JS : ' + e.message));

await page.goto(`${base}/connexion`);
await page.fill('#email', email);
await page.fill('#password', motDePasse);
await page.click('button[type="submit"]');
await page.waitForURL(/accueil/);
await page.evaluate(() => navigator.serviceWorker.ready);
await page.reload(); // la page est maintenant contrôlée par le service worker
await page.goto(`${base}/planning`);
await page.goto(`${base}/guide`);
await page.waitForTimeout(500);

await reseau(contexte, false);
await page.goto(`${base}/planning`);
verifier((await page.textContent('h1')).includes('Planning'), 'planning gardé : page absente');
verifier(await page.locator('.bandeau-hors-connexion:not([hidden])').count() === 1, 'bandeau « Pas de réseau » absent');
if (process.env.CAPTURES) { await page.screenshot({ path: `${process.env.CAPTURES}/hors-connexion-planning.png` }); }

await page.goto(`${base}/statistiques`);
verifier((await page.textContent('h1').catch(() => '')).includes('Pas de réseau'), 'page jamais ouverte : la page « Pas de réseau » ne s\'affiche pas (' + page.url() + ' : ' + (await page.title()) + ' / ' + (await page.locator('h1').allTextContents()).join('|') + ')');

// Formulaire envoyé sans réseau : il part tout seul au retour de la connexion.
await page.goto(`${base}/guide`);
await reseau(contexte, true);
await page.goto(`${base}/accueil/personnaliser`);
await reseau(contexte, false);
await page.evaluate(() => window.dispatchEvent(new Event('offline')));
await page.click('form[action$="/accueil/personnaliser"] button[type="submit"]');
await page.waitForTimeout(300);
verifier((await page.textContent('.bandeau-hors-connexion')).includes('partira tout seul'), 'envoi en attente non signalé');
await reseau(contexte, true);
await page.evaluate(() => window.dispatchEvent(new Event('online'))).catch(() => { /* déjà reparti */ });
await page.waitForURL(/\/accueil$/, { timeout: 5000 }).catch(() => erreurs.push('le formulaire n\'est pas reparti au retour du réseau'));

// Déconnexion : les pages gardées sont oubliées.
await page.goto(`${base}/plus`);
await page.click('form[action$="/deconnexion"] button');
await page.waitForURL(/connexion/);
await page.waitForTimeout(300);
const gardees = await page.evaluate(async () => (await caches.keys()).filter((c) => c.startsWith('pages-')).length);
verifier(gardees === 0, 'pages encore gardées après la déconnexion');

await navigateur.close();
if (erreurs.length) {
    console.error('PROBLÈMES :\n- ' + erreurs.join('\n- '));
    process.exit(1);
}
console.log('Hors connexion vérifié : aucun problème.');
