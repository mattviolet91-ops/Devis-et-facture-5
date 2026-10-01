// Vérification de l'écran sur un téléphone simulé (390 px), clair et sombre.
// Usage : NODE_PATH=$(npm root -g) node outils/verif-mobile.mjs http://127.0.0.1:8123 email motdepasse dossier-captures
// Outil de développement seulement : jamais utilisé en production.
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');

const [base, email, motDePasse, dossier = 'captures'] = process.argv.slice(2);
const erreurs = [];
const verifier = (condition, message) => { if (!condition) erreurs.push(message); };

const navigateur = await chromium.launch();

for (const theme of ['light', 'dark']) {
    const contexte = await navigateur.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
        colorScheme: theme,
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
    });
    const page = await contexte.newPage();
    page.on('console', (m) => { if (m.type() === 'error' && !m.location().url.includes('page-inexistante')) erreurs.push(`[${theme}] console : ${m.text()}`); });
    page.on('pageerror', (e) => erreurs.push(`[${theme}] JS : ${e.message}`));

    const capture = async (nom) => {
        const largeur = await page.evaluate(() => document.documentElement.scrollWidth);
        verifier(largeur <= 390, `[${theme}] ${nom} : défilement horizontal (${largeur}px)`);
        await page.screenshot({ path: `${dossier}/${nom}-${theme}.png`, fullPage: true });
    };

    await page.goto(`${base}/connexion`);
    await capture('connexion');
    await page.fill('#email', email);
    await page.fill('#password', motDePasse);
    await page.click('[data-afficher-mot-de-passe]');
    verifier(await page.getAttribute('#password', 'type') === 'text', 'Bouton Afficher le mot de passe inactif');
    await page.click('button[type=submit]');
    await page.waitForURL('**/accueil');
    await capture('accueil');

    // Barre du bas visible et collée en bas.
    const barre = await page.locator('.barre-bas').boundingBox();
    verifier(barre && Math.round(barre.y + barre.height) === 844, `[${theme}] barre du bas mal placée`);

    // Taille minimale des zones à toucher (44 px).
    const petits = await page.$$eval('.barre-bas a, .bouton', (els) => els.filter((e) => e.getBoundingClientRect().height < 44).length);
    verifier(petits === 0, `[${theme}] ${petits} bouton(s) trop petit(s)`);

    // Retour : Accueil → Plus → Journal → Retour = Plus → Retour = page parente.
    await page.click('.barre-bas a[href$="/plus"]');
    await page.waitForURL('**/plus');
    await capture('plus');
    await page.click('a[href$="/journal"]');
    await page.waitForURL('**/journal');
    await capture('journal');
    await page.click('[data-retour]');
    await page.waitForURL('**/plus');

    await page.goto(`${base}/corbeille`);
    await capture('corbeille');
    await page.goto(`${base}/nouveau`);
    await capture('nouveau');
    await page.click('[data-retour]');
    await page.waitForURL('**/corbeille');

    // Grands boutons, puis thème forcé.
    await page.goto(`${base}/plus`);
    await page.check('#grands-boutons');
    await page.reload();
    verifier(await page.getAttribute('html', 'data-grands-boutons') === '1', 'Grands boutons non gardés');
    await capture('plus-grands-boutons');
    await page.uncheck('#grands-boutons');
    await page.check('input[value="' + (theme === 'light' ? 'sombre' : 'clair') + '"]', { force: true });
    await page.reload();
    verifier(await page.getAttribute('html', 'data-theme') === (theme === 'light' ? 'sombre' : 'clair'), 'Thème non gardé');
    await page.check('input[value="auto"]', { force: true });

    // Focus visible au clavier.
    await page.goto(`${base}/plus`);
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    const contour = await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
    verifier(contour !== 'none', `[${theme}] focus invisible`);

    // Réglages (gérant) : liste et formulaires.
    await page.goto(`${base}/reglages`);
    await capture('reglages');
    for (const section of ['entreprise', 'apparence', 'tva', 'numerotation', 'documents', 'assurance', 'emails', 'modeles']) {
        await page.goto(`${base}/reglages/${section}`);
        await capture(`reglages-${section}`);
    }
    await page.goto(`${base}/reglages/apparence`);
    await page.fill('[data-apercu="apparence.couleur_principale"]', '#2a9d8f');
    const apercu = await page.$eval('#apercu', (e) => e.style.getPropertyValue('--couleur-principale'));
    verifier(apercu === '#2a9d8f', `[${theme}] aperçu des couleurs inactif`);
    await page.goto(`${base}/reglages`);
    await page.click('a[href$="/reglages/entreprise"]');
    await page.waitForURL('**/reglages/entreprise');
    await page.fill('[name="identite__siret"]', '12345678901234');
    await page.click('form.carte button[type=submit]');
    await page.waitForLoadState();
    verifier(await page.locator('.erreur-champ').count() > 0, `[${theme}] erreur SIRET non affichée`);
    await capture('reglages-erreur');
    await page.click('[data-retour]');
    await page.waitForURL('**/reglages');
    await page.goto(`${base}/reglages/textes-types`);
    await capture('reglages-textes');

    await page.goto(`${base}/page-inexistante`);
    await capture('erreur-404');

    await contexte.close();
}

await navigateur.close();

if (erreurs.length) {
    console.error('PROBLÈMES :\n- ' + erreurs.join('\n- '));
    process.exit(1);
}
console.log('Écran vérifié : aucun problème.');
