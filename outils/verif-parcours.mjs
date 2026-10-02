// Parcours complet d'un artisan, au doigt, sur téléphone simulé (démonstration uniquement) :
// client → chantier → devis → envoi → signature → facture → encaissement → planning → photo → rapport,
// puis devis express et compte commercial.
// Usage : NODE_PATH=$(npm root -g) node outils/verif-parcours.mjs http://127.0.0.1:8123 gerant mdp commercial mdp dossier
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse, commercial, motDePasseCommercial, dossier = os.tmpdir()] = process.argv.slice(2);
const erreurs = [];
const reussies = [];
const telephone = { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fr-FR' };

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext(telephone);
const page = await contexte.newPage();
page.on('pageerror', (e) => erreurs.push(`JS : ${e.message}`));
page.on('dialog', (d) => d.accept());

async function etape(nom, action) {
    try {
        await action();
        const erreur = await page.locator('.message-erreur, .erreur-champ').first().textContent({ timeout: 300 }).catch(() => null);
        if (erreur) throw new Error('message d\'erreur affiché : ' + erreur.trim());
        reussies.push(nom);
    } catch (e) {
        erreurs.push(`${nom} : ${e.message.split('\n')[0]}`);
        await page.screenshot({ path: path.join(dossier, `echec-${reussies.length + erreurs.length}.png`), fullPage: true }).catch(() => {});
    }
}
const voir = async (texte) => { if (!(await page.getByText(texte, { exact: false }).first().isVisible())) throw new Error(`« ${texte} » absent`); };
// Clic qui change de page : on attend la nouvelle page.
const aller = async (selecteur) => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator(selecteur).first().click()]); };
const envoyer = async (selecteur = 'main form button[type="submit"]') => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator(selecteur).last().click()]); };

await page.goto(`${base}/connexion`);
await page.fill('#email', email);
await page.fill('#password', motDePasse);
await page.click('button[type="submit"]');
await page.waitForURL(/accueil/);
if (!(await page.locator('.bandeau-demo').count())) { console.error('Refusé : démonstration seulement.'); process.exit(1); }

const nom = 'Parcours' + Date.now().toString().slice(-5);
const numero = '06' + Date.now().toString().slice(-8);

await etape('Créer un client', async () => {
    await aller('.barre-bas >> text=Nouveau');
    await aller('text=Un client');
    await page.selectOption('#civilite', { label: 'Mme' });
    await page.fill('#nom', nom);
    await page.fill('#prenom', 'Julie');
    await page.fill('#telephone', numero);
    await page.fill('#email', `${nom.toLowerCase()}@exemple.test`);
    await page.fill('#adresse', '4 rue des Essais');
    await page.fill('#code_postal', '00100');
    await page.fill('#ville', 'Ville-Test');
    await envoyer();
    await voir(`Mme Julie ${nom}`);
});
const ficheClient = page.url();

await etape('Ajouter une adresse de chantier', async () => {
    await aller('text=Ajouter une adresse de chantier');
    await page.fill('#libelle', 'Maison');
    await envoyer();
    await voir('Maison');
});

await etape('Faire un devis avec deux lignes', async () => {
    await page.goto(ficheClient);
    await aller('#documents a:has-text("Devis")');
    await page.fill('#objet', 'Réfection du faîtage').catch(() => {});
    await envoyer();
    await page.waitForURL(/\/devis\/\d+\/modifier/);
    for (const [designation, quantite, prix] of [['Faîtage scellé', '12', '45'], ['Échafaudage', '1', '600']]) {
        await page.click('button[data-ajouter="ligne"]');
        const ligne = page.locator('#lignes > li').last();
        await ligne.locator('[data-champ="designation"]').fill(designation);
        await ligne.locator('[data-champ="quantite"]').fill(quantite);
        await ligne.locator('[data-champ="prix"]').fill(prix);
    }
    const total = await page.textContent('[data-total-ttc]');
    if (!/1[\s  ]?140/.test(total.replace(/\s/g, ' '))) throw new Error('total affiché inattendu : ' + total);
    await envoyer('form button[type="submit"]:has-text("Enregistrer")');
    await page.waitForURL(/\/devis\/\d+$/);
    await voir('Faîtage scellé');
});
const ficheDevis = page.url();

await etape('Envoyer le devis par email', async () => {
    await aller('.barre-etape >> text=Envoyer');
    await envoyer('form#formulaire-envoi button[type="submit"]');
    await page.waitForURL(/\/devis\/\d+$/);
    await voir('DEV-');
});

await etape('Faire signer le devis sur place', async () => {
    await aller('.barre-etape >> text=Faire signer');
    await page.fill('#nom', `Julie ${nom}`).catch(() => {});
    const cases = page.locator('form input[type="checkbox"]');
    for (let i = 0; i < await cases.count(); i++) await cases.nth(i).check();
    await page.locator('#zone-signature').evaluate((toile) => {
        const r = toile.getBoundingClientRect();
        const ev = (type, i) => toile.dispatchEvent(new PointerEvent(type, { bubbles: true, pointerId: 1, pointerType: 'touch', isPrimary: true, clientX: r.left + 20 + i * 25, clientY: r.top + r.height / 2 + (i % 2 ? -20 : 20) }));
        ev('pointerdown', 0); for (let i = 1; i < 10; i++) ev('pointermove', i); ev('pointerup', 10);
    });
    await envoyer('form button[type="submit"]:has-text("ign")');
    await page.goto(ficheDevis);
    await voir('Accepté');
});

await etape('Facturer le devis puis émettre la facture', async () => {
    await page.click('.barre-etape >> text=Facturer');
    await envoyer('#facturer button[type="submit"]');
    await page.waitForURL(/\/factures\/\d+/);
    if (page.url().endsWith('/modifier')) { await envoyer('form button[type="submit"]:has-text("Enregistrer")'); }
    await aller('.barre-etape >> text=Émettre');
    await page.waitForLoadState('load');
    await voir('FAC-');
});
const ficheFacture = page.url();

await etape('Ajouter un frais avec la photo du ticket', async () => {
    await page.click('#frais summary');
    await page.fill('#libelle', 'Tuiles faîtières');
    await page.fill('#montant_frais', '210,50');
    await page.setInputFiles('#ticket', { name: 'ticket.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
    await envoyer('#frais form button[type="submit"]');
    await voir('Il vous reste');
    await voir('Tuiles faîtières');
});

await etape('Encaisser la facture', async () => {
    await page.goto(ficheFacture);
    await page.click('.barre-etape >> text=Encaisser');
    await envoyer('#encaisser form button[type="submit"]');
    await voir('Payée');
});

await etape('Planifier le chantier du devis accepté', async () => {
    await page.goto(ficheDevis);
    await aller('.barre-etape >> text=Planifier');
    await envoyer();
    await page.waitForURL(/\/planning\/\d+$/);
    await voir('Chantier');
});
const ficheRdv = page.url();

await etape('Ajouter une photo depuis le planning', async () => {
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
    await page.setInputFiles('#photos', [{ name: 'toit.png', mimeType: 'image/png', buffer: png }]);
    await envoyer('form[action*="/photos"] button[type="submit"]');
    await voir('Photos de ce passage (1)');
});

await etape('Faire le rapport d\'intervention et voir son PDF', async () => {
    await page.goto(ficheRdv);
    await page.click('text=Faire le rapport d\'intervention');
    await page.fill('#travaux', 'Faîtage refait à neuf.');
    await envoyer();
    await page.waitForURL(/\/rapports\/\d+$/);
    const pdf = await page.request.get(page.url() + '/pdf');
    if (pdf.status() !== 200 || !(await pdf.body()).subarray(0, 4).toString().startsWith('%PDF')) throw new Error('PDF du rapport illisible');
});

await etape('Devis express en une phrase', async () => {
    await aller('.barre-bas >> text=Nouveau');
    await aller('text=Un devis express');
    await page.fill('#phrase', `Mme ${nom}, démoussage 80 m² à 12 €, traitement hydrofuge 80 m² à 9 €`);
    await envoyer('form button[type="submit"]:has-text("perçu")');
    await voir('1 680');
    await envoyer('form button[type="submit"]:has-text("rée")');
    await page.waitForURL(/\/devis\/\d+\/modifier/);
});

await etape('Ajouter un rendez-vous', async () => {
    await aller('.barre-bas >> text=Nouveau');
    await aller('text=Un rendez-vous');
    await page.fill('#titre', 'Visite de contrôle');
    await page.fill('#heure_debut', '15:30');
    await envoyer();
    await voir('Visite de contrôle');
});

await etape('Chercher un client', async () => {
    await page.goto(`${base}/clients`);
    await page.fill('input[type="search"]', nom.toLowerCase());
    await envoyer('form[role="search"] button');
    await voir(`Mme Julie ${nom}`);
});

// Compte commercial.
const contexteCommercial = await navigateur.newContext(telephone);
const pageCommercial = await contexteCommercial.newPage();
await pageCommercial.goto(`${base}/connexion`);
await pageCommercial.fill('#email', commercial);
await pageCommercial.fill('#password', motDePasseCommercial);
await pageCommercial.click('button[type="submit"]');
await pageCommercial.waitForURL(/accueil/);
for (const interdite of ['/factures', '/reglages', '/statistiques', '/comptes']) {
    const r = await pageCommercial.goto(base + interdite);
    if (r.status() !== 403) erreurs.push(`Commercial : ${interdite} accessible (${r.status()})`);
}
if (await pageCommercial.goto(`${base}/accueil`).then(() => pageCommercial.getByText('Facturé ce mois').count())) erreurs.push('Commercial : chiffre d\'affaires visible');
reussies.push('Droits du commercial');

await navigateur.close();
console.log(`Étapes réussies (${reussies.length}) :\n- ` + reussies.join('\n- '));
if (erreurs.length) { console.error('PROBLÈMES :\n- ' + erreurs.join('\n- ')); process.exit(1); }
console.log('Parcours complet vérifié : aucun problème.');
