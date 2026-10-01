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

    // Clients : liste, recherche sans accents, fiche, formulaire avec alerte de doublon.
    await page.click('.barre-bas a[href$="/clients"]');
    await page.waitForURL('**/clients');
    await capture('clients');
    await page.fill('#q', 'helene');
    await page.click('form.recherche button');
    await page.waitForLoadState();
    verifier(await page.locator('.liste-clients li').count() === 1, `[${theme}] recherche sans accents`);
    await page.click('.liste-clients a');
    await page.waitForLoadState();
    await capture('client-fiche');
    await page.click('[data-retour]');
    await page.waitForURL('**/clients?q=helene');
    await page.goto(`${base}/clients/nouveau`);
    verifier(await page.isHidden('[data-pour-type="professionnel"]'), `[${theme}] champs société visibles pour un particulier`);
    await page.check('input[data-type-client][value="professionnel"]', { force: true });
    verifier(await page.isVisible('[name="raison_sociale"]'), `[${theme}] champs société cachés pour un professionnel`);
    await page.check('input[data-type-client][value="particulier"]', { force: true });
    await page.fill('#nom', 'Essai');
    await page.fill('#telephone', '+33 6 00 00 00 01');
    await page.click('form.carte button[type=submit]');
    await page.waitForLoadState();
    verifier(await page.locator('text=ce client existe peut-être déjà').count() === 1, `[${theme}] alerte de doublon absente`);
    await capture('client-doublon');
    await page.goto(`${base}/clients/import`);
    await capture('clients-import');

    // Devis : liste, éditeur (ajout de ligne, totaux en direct, catalogue, toiture), devis express.
    await page.click('.barre-bas a[href$="/devis"]');
    await page.waitForURL('**/devis');
    await capture('devis');
    await page.goto(`${base}/devis?statut=brouillon`);
    await page.click('ul.liste.carte a.liste-lien >> nth=0');
    await page.waitForURL(/\/devis\/\d+$/);
    await capture('devis-fiche');
    await page.click('.actions-devis a[href$="/modifier"]');
    await page.waitForURL('**/modifier');
    const avant = await page.textContent('[data-total-ttc]');
    await page.click('button[data-ajouter="ligne"]');
    const nouvelle = page.locator('#lignes > li').last();
    await nouvelle.locator('[data-champ="designation"]').fill('Ligne de test');
    await nouvelle.locator('[data-champ="quantite"]').fill('2');
    await nouvelle.locator('[data-champ="prix"]').fill('100');
    verifier(await page.textContent('[data-total-ttc]') !== avant, `[${theme}] totaux non recalculés`);
    await page.click('button[data-ouvrir="dialogue-catalogue"]');
    await page.fill('#recherche-catalogue', 'faitiere');
    await page.waitForSelector('#resultats-catalogue button:has-text("Faîti")');
    await capture('devis-catalogue');
    await page.click('#resultats-catalogue button:has-text("Faîti") >> nth=0');
    const ajoutee = await page.waitForFunction(() => {
        const champs = document.querySelectorAll('#lignes > li [data-champ="designation"]');
        return champs.length && champs[champs.length - 1].value.startsWith('Faîti');
    }, null, { timeout: 3000 }).then(() => true, () => false);
    verifier(ajoutee, `[${theme}] ajout depuis le catalogue`);
    await page.click('button[data-ouvrir="dialogue-toiture"]');
    await page.fill('#toiture-sol', '100');
    await page.fill('#toiture-pente', '45');
    verifier((await page.textContent('#toiture-resultat')).includes('141,42'), `[${theme}] calcul de toiture`);
    await capture('devis-toiture');
    await page.click('#toiture-utiliser');
    await capture('devis-editeur');
    await page.click('.barre-actions button[type=submit]');
    await page.waitForLoadState();
    verifier(page.url().match(/\/devis\/\d+$/), `[${theme}] enregistrement du devis`);
    await page.goto(`${base}/devis/express`);
    await page.fill('#phrase', 'Mme Martin, démoussage 120 m² à 12 €, 3 faîtières à 150 €, évacuation forfait 150 €');
    await page.click('button:has-text("Voir l\'aperçu")');
    await page.waitForLoadState();
    verifier(await page.locator('button:has-text("Créer le brouillon")').count() === 1, `[${theme}] aperçu du devis express`);
    await capture('devis-express');

    // Visionneuse PDF (pdf.js, sans appel extérieur).
    await page.goto(`${base}/devis?statut=accepte`);
    await page.click('ul.liste.carte a.liste-lien >> nth=0');
    await page.waitForURL(/\/devis\/\d+$/);
    await page.click('a:has-text("Voir le PDF")');
    await page.waitForFunction(() => document.querySelectorAll('canvas.page-pdf').length >= 2, null, { timeout: 20000 }).catch(() => {});
    verifier(await page.locator('canvas.page-pdf').count() >= 2, `[${theme}] visionneuse PDF`);
    await capture('visionneuse');
    await page.click('[data-retour]');
    await page.waitForURL(/\/devis\/\d+$/);

    // Envoi par email d'un devis.
    await page.goto(`${base}/devis?statut=envoye`);
    await page.click('ul.liste.carte a.liste-lien >> nth=0');
    await page.waitForURL(/\/devis\/\d+$/);
    await page.click('a:has-text("Renvoyer par email")');
    await page.waitForURL(/\/envoyer\/devis\/\d+$/);
    await capture('envoi-email');

    // Factures (gérant).
    await page.goto(`${base}/factures`);
    await capture('factures');
    await page.goto(`${base}/factures?filtre=retard`);
    await page.click('ul.liste.carte a.liste-lien >> nth=0');
    await page.waitForURL(/\/factures\/\d+$/);
    await capture('facture-fiche');

    await page.goto(`${base}/catalogue`);
    await capture('catalogue');

    // Planning : semaine, mois, fiche, formulaire, à planifier.
    await page.goto(`${base}/planning`);
    verifier(await page.locator('.jour-planning').count() === 7, `[${theme}] planning : 7 jours attendus`);
    await capture('planning-semaine');
    await page.goto(`${base}/planning?vue=mois`);
    await capture('planning-mois');
    await page.goto(`${base}/planning`);
    await page.click('.jour-planning ul.liste a >> nth=0');
    await page.waitForURL(/\/planning\/\d+$/);
    await capture('planning-fiche');
    await page.goto(`${base}/planning/nouveau`);
    await capture('planning-formulaire');
    await page.goto(`${base}/planning/a-planifier`);
    await capture('planning-a-planifier');

    // Photos et rapports (client « Garnier » de la démonstration).
    await page.goto(`${base}/clients?q=Garnier`);
    await page.click('ul.liste a >> nth=0');
    await page.waitForURL(/\/clients\/\d+$/);
    await page.click('text=Photos du chantier');
    await capture('photos');
    await page.goBack();
    await page.click('#photos ul.liste li:nth-child(2) a');
    await capture('rapport');
    await page.click('text=Modifier');
    await capture('rapport-formulaire');

    // Suivi commercial et formulaire public.
    await page.goto(`${base}/suivi`);
    await capture('suivi');
    await page.click('#titre-demandes ~ ul a >> nth=0');
    await capture('suivi-demande');
    await page.goto(`${base}/suivi/emails`);
    await capture('suivi-emails');
    await page.goto(`${base}/demande`);
    const piege = await page.locator('#site_web').boundingBox();
    verifier(piege && piege.x + piege.width <= 0, `[${theme}] le champ piège est visible`);
    await capture('demande-publique');

    // Accueil personnalisable, statistiques, frais.
    await page.goto(`${base}/accueil/personnaliser`);
    await capture('accueil-personnaliser');
    await page.goto(`${base}/statistiques`);
    await capture('statistiques');
    await page.goto(`${base}/statistiques/site`);
    await capture('statistiques-site');
    await page.goto(`${base}/factures?filtre=retard`);
    await page.click('[data-glisser] a.contenu-glisser >> nth=0');
    await page.waitForURL(/\/factures\/\d+$/);
    verifier(await page.locator('.barre-etape').count() === 1, `[${theme}] barre d'actions absente sur la facture`);
    await capture('facture-frais');

    await page.goto(`${base}/comptes`);
    await capture('comptes');

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
