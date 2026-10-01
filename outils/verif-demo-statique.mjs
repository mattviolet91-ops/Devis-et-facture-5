// Vérifie la démo fixe comme sur GitHub Pages (adresse avec sous-dossier), sur téléphone simulé.
// Usage : python3 -m http.server 8200 (à la racine du dépôt), puis
//   NODE_PATH=$(npm root -g) node outils/verif-demo-statique.mjs http://127.0.0.1:8200/docs/demo/ [dossier-captures]
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, dossier] = process.argv.slice(2);
const erreurs = [];
const verifier = (condition, message) => { if (!condition) erreurs.push(message); };

const navigateur = await chromium.launch();
for (const theme of ['light', 'dark']) {
    const contexte = await navigateur.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, colorScheme: theme, locale: 'fr-FR' });
    const page = await contexte.newPage();
    const introuvables = [];
    page.on('response', (r) => { if (r.status() >= 400 && !r.url().endsWith('/sw.js')) introuvables.push(r.status() + ' ' + r.url()); });
    page.on('pageerror', (e) => erreurs.push(`[${theme}] JS : ${e.message}`));
    const capture = async (nom) => {
        const largeur = await page.evaluate(() => document.documentElement.scrollWidth);
        verifier(largeur <= 390, `[${theme}] ${nom} : défilement horizontal (${largeur}px)`);
        if (dossier) await page.screenshot({ path: `${dossier}/statique-${nom}-${theme}.png`, fullPage: true });
    };

    await page.goto(base);
    await capture('entree');
    await page.click('text=Entrer comme l\'artisan');
    verifier((await page.textContent('h1')).includes('Accueil'), `[${theme}] accueil absent`);
    verifier(await page.locator('.demo-statique').count() === 1, `[${theme}] bandeau de la démo absent`);
    await capture('accueil');

    // Navigation : barre du bas, liste, fiche.
    await page.click('.barre-bas >> text=Devis');
    verifier((await page.textContent('h1')).includes('Devis'), `[${theme}] liste des devis absente`);
    await page.click('ul.liste.carte a.liste-lien >> nth=0');
    verifier(/devis\/\d+\.html$/.test(page.url()), `[${theme}] fiche devis absente (${page.url()})`);
    await capture('devis');

    // Un formulaire ne part pas : message affiché, on reste sur la page.
    const avant = page.url();
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForTimeout(200);
    verifier(page.url() === avant, `[${theme}] un formulaire est parti`);
    verifier(await page.locator('.demo-bulle').count() >= 1, `[${theme}] message « rien n'est enregistré » absent`);

    for (const lien of ['Clients', 'Accueil']) { await page.click(`.barre-bas >> text=${lien}`); }
    await page.click('.barre-bas >> text=Plus');
    for (const rubrique of ['Planning', 'Factures', 'Statistiques', 'Guide']) {
        await page.click(`text=${rubrique} >> nth=0`);
        await capture(rubrique.toLowerCase());
        await page.goBack();
    }

    // Côté client.
    await page.goto(base);
    await page.click('text=Voir un devis comme le client');
    verifier(await page.locator('#zone-signature').count() === 1, `[${theme}] page client sans zone de signature`);
    await capture('client');

    verifier(introuvables.length === 0, `[${theme}] fichiers introuvables : ${introuvables.slice(0, 5).join(', ')}`);
    await contexte.close();
}
await navigateur.close();
if (erreurs.length) { console.error('PROBLÈMES :\n- ' + erreurs.join('\n- ')); process.exit(1); }
console.log('Démo fixe vérifiée : aucun problème.');
