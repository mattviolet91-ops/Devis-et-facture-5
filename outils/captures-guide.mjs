// Captures d'écran du guide, faites UNIQUEMENT sur une installation de démonstration (données fictives).
// Usage : NODE_PATH=$(npm root -g) node outils/captures-guide.mjs http://127.0.0.1:8123 demo@exemple.test motdepasse
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse] = process.argv.slice(2);

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({ viewport: { width: 390, height: 760 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, colorScheme: 'light', locale: 'fr-FR' });
const page = await contexte.newPage();

await page.goto(`${base}/connexion`);
await page.fill('#email', email);
await page.fill('#password', motDePasse);
await page.click('button[type="submit"]');
await page.waitForURL(/accueil/);

const demo = await page.locator('.bandeau-demo').count();
if (!demo) {
    console.error('Refusé : les captures se font seulement sur une installation de démonstration.');
    process.exit(1);
}

const pages = {
    accueil: '/accueil',
    clients: '/clients',
    devis: '/devis',
    'devis-express': '/devis/express',
    factures: '/factures',
    planning: '/planning',
    suivi: '/suivi',
    statistiques: '/statistiques',
};
for (const [nom, adresse] of Object.entries(pages)) {
    await page.goto(base + adresse);
    await page.screenshot({ path: `public/images/guide/${nom}.png` });
}

// Photos : galerie du premier client qui en a.
await page.goto(`${base}/clients?q=Garnier`);
await page.click('ul.liste a.liste-lien >> nth=0');
await page.click('text=Photos du chantier');
await page.locator('.galerie').first().evaluate((el) => el.closest('section').scrollIntoView());
await page.screenshot({ path: 'public/images/guide/photos.png' });

await navigateur.close();
console.log('Captures du guide enregistrées dans public/images/guide.');
