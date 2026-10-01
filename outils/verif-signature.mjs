// Parcours de signature : le gérant crée le lien, le client l'ouvre sans compte et signe au doigt.
// Usage : NODE_PATH=$(npm root -g) node outils/verif-signature.mjs http://127.0.0.1:8123 email motdepasse dossier-captures
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse, dossier = 'captures'] = process.argv.slice(2);
const erreurs = [];
const verifier = (c, m) => { if (!c) erreurs.push(m); };
const navigateur = await chromium.launch();
const telephone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };

// 1. Le gérant crée le lien d'un devis envoyé.
const gerant = await (await navigateur.newContext(telephone)).newPage();
await gerant.goto(`${base}/connexion`);
await gerant.fill('#email', email);
await gerant.fill('#password', motDePasse);
await gerant.click('button[type=submit]');
await gerant.waitForURL('**/accueil');
await gerant.goto(`${base}/devis?statut=envoye`);
await gerant.click('ul.liste.carte a.liste-lien >> nth=0');
await gerant.waitForURL(/\/devis\/\d+$/);
// Le lien existe peut-être déjà (créé en préparant un email) : sinon on le crée.
if (await gerant.locator('button:has-text("Créer le lien du client")').count()) {
    await gerant.click('button:has-text("Créer le lien du client")');
    await gerant.waitForLoadState();
}
const lien = (await gerant.textContent('#adresse-lien')).trim();
verifier(lien.includes('/c/'), 'lien client absent');

// 2. Le client ouvre le lien (sans compte) et signe au doigt.
const client = await (await navigateur.newContext(telephone)).newPage();
client.on('pageerror', (e) => erreurs.push(`JS : ${e.message}`));
await client.goto(lien);
await client.screenshot({ path: `${dossier}/client-devis.png`, fullPage: true });
await client.fill('#nom', 'Client Exemple');
// Trait au doigt : suite d'événements « pointer » de type tactile sur le cadre.
await client.locator('#zone-signature').evaluate((toile) => {
    const r = toile.getBoundingClientRect();
    const evenement = (type, i) => toile.dispatchEvent(new PointerEvent(type, {
        bubbles: true, pointerId: 1, pointerType: 'touch', isPrimary: true,
        clientX: r.left + 20 + i * 25, clientY: r.top + r.height / 2 + (i % 2 ? -25 : 25),
    }));
    evenement('pointerdown', 0);
    for (let i = 1; i <= 10; i++) evenement('pointermove', i);
    evenement('pointerup', 10);
});
await client.check('input[name="accord"]');
await client.screenshot({ path: `${dossier}/client-signature.png`, fullPage: true });
await Promise.all([client.waitForNavigation(), client.click('#valider-signature')]);
verifier((await client.textContent('main')).includes('Votre devis est signé'), 'signature non enregistrée');
await client.screenshot({ path: `${dossier}/client-signe.png`, fullPage: true });

// 3. Le gérant voit la signature.
await gerant.reload();
verifier((await gerant.textContent('main')).includes('Signé par Client Exemple'), 'signature invisible côté gérant');

await navigateur.close();
if (erreurs.length) {
    console.error('PROBLÈMES :\n- ' + erreurs.join('\n- '));
    process.exit(1);
}
console.log('Signature vérifiée : aucun problème.');
