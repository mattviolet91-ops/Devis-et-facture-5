// Parcours complet du menu de configuration sur un téléphone simulé (390 px).
// Usage : NODE_PATH=$(npm root -g) node outils/verif-configuration.mjs http://127.0.0.1:8123 email motdepasse dossier-captures
// À lancer sur une base neuve (configuration non faite). Outil de développement seulement.
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse, dossier = 'captures'] = process.argv.slice(2);
const erreurs = [];
const verifier = (condition, message) => { if (!condition) erreurs.push(message); };

// SIRET fictif dont la clé (Luhn) est juste.
function siretFictif() {
    const debut = '1234567890123';
    for (let c = 0; c <= 9; c++) {
        const s = debut + c;
        let somme = 0;
        [...s].reverse().forEach((ch, i) => { let d = +ch; if (i % 2) { d *= 2; if (d > 9) d -= 9; } somme += d; });
        if (somme % 10 === 0) return s;
    }
}

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
const page = await contexte.newPage();
page.on('pageerror', (e) => erreurs.push(`JS : ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error') erreurs.push(`console : ${m.text()}`); });

const capture = async (nom) => {
    const largeur = await page.evaluate(() => document.documentElement.scrollWidth);
    verifier(largeur <= 390, `${nom} : défilement horizontal (${largeur}px)`);
    await page.screenshot({ path: `${dossier}/config-${nom}.png`, fullPage: true });
};
const continuer = async () => { await page.click('form.carte button[type=submit]:has-text("Enregistrer et continuer")'); await page.waitForLoadState(); };

await page.goto(`${base}/connexion`);
await page.fill('#email', email);
await page.fill('#password', motDePasse);
await page.click('button[type=submit]');
await page.waitForURL('**/configuration/metier');
await capture('1-metier');

// 1. Métier : les modules conseillés se cochent tout seuls.
await page.check('input[name="metier"][value="couvreur"]');
verifier(await page.isChecked('input[data-module][value="calculateur_toiture"]'), 'modules du métier non cochés');
await continuer();
verifier(page.url().endsWith('/configuration/identite'), 'métier → identité');

// 2. Identité : d'abord un SIRET faux.
await page.fill('[name="identite__nom_commercial"]', 'Entreprise Fictive');
await page.selectOption('[name="identite__forme_juridique"]', 'EURL');
await page.fill('[name="identite__siret"]', '12345678901234');
await page.fill('[name="identite__adresse"]', '1 rue de l\'Exemple');
await page.fill('[name="identite__code_postal"]', '00000');
await page.fill('[name="identite__ville"]', 'Ville-Test');
await page.fill('[name="identite__telephone"]', '01 00 00 00 00');
await page.fill('[name="identite__email"]', 'contact@fictive.test');
await continuer();
verifier(await page.locator('.erreur-champ').count() === 1, 'erreur SIRET attendue');
await capture('2-identite-erreur');
await page.fill('[name="identite__siret"]', siretFictif());
await continuer();

// 3. TVA (franchise).
await capture('3-tva');
await page.selectOption('[name="tva__regime"]', 'franchise');
await continuer();

// 4. Assurance.
await page.fill('[name="assurance__assureur"]', 'Assureur Fictif');
await page.fill('[name="assurance__numero_contrat"]', 'X-1');
await page.fill('[name="assurance__date_debut"]', '2026-01-01');
await page.fill('[name="assurance__date_fin"]', '2027-01-01');
await page.fill('[name="assurance__activites"]', 'Couverture');
await capture('4-assurance');
await continuer();

// 5. Documents : IBAN.
await page.fill('[name="documents__iban"]', 'FR76 3000 6000 0112 3456 7890 189');
await page.fill('[name="documents__bic"]', 'AGRIFRPP');
await page.fill('[name="identite__mediateur_nom"]', 'Médiateur Fictif');
await capture('5-documents');
await continuer();

// 6. Apparence : aperçu en direct, puis retour arrière et reprise.
await page.fill('[data-apercu="apparence.couleur_principale"]', '#2a9d8f');
await capture('6-apparence');
await page.click('[data-retour]');
await page.waitForURL('**/configuration/documents');
verifier(await page.inputValue('[name="documents__bic"]') === 'AGRIFRPP', 'valeurs perdues en revenant en arrière');
await page.goto(`${base}/accueil`);
verifier(page.url().endsWith('/configuration/apparence'), `reprise attendue sur Apparence, obtenu ${page.url()}`);
await page.click('button:has-text("Plus tard")');

// 7. Emails : plus tard.
await page.waitForURL('**/configuration/emails');
await capture('7-emails');
await page.click('button:has-text("Plus tard")');

// 8. CGV : la case est obligatoire.
await page.waitForURL('**/configuration/cgv');
await continuer();
verifier(await page.locator('#cgv-relues-erreur').count() === 1, 'case CGV non exigée');
await page.check('input[name="cgv_relues"]');
await capture('8-cgv');
await continuer();

// 9. Récapitulatif, PDF d'exemple, terminer.
await page.waitForURL('**/configuration/recapitulatif');
await capture('9-recapitulatif');
const pdf = await page.request.get(`${base}/configuration/devis-exemple.pdf`);
verifier(pdf.headers()['content-type'] === 'application/pdf', 'PDF d\'exemple absent');
await page.click('button:has-text("Terminer")');
await page.waitForURL('**/accueil');
verifier(await page.locator('.bandeau-configuration').count() === 0, 'bandeau toujours là après la fin');
await capture('10-accueil');

await navigateur.close();

if (erreurs.length) {
    console.error('PROBLÈMES :\n- ' + erreurs.join('\n- '));
    process.exit(1);
}
console.log('Menu de configuration vérifié : aucun problème.');
