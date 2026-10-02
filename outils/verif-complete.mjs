// Vérification complète : parcourt TOUTES les pages accessibles (comme un utilisateur), sur téléphone,
// en clair et en sombre. Erreurs serveur, erreurs JavaScript, fichiers introuvables, défilement de côté,
// éléments qui dépassent, accessibilité (axe-core si AXE=chemin/axe.min.js).
// Usage : NODE_PATH=$(npm root -g) AXE=… node outils/verif-complete.mjs http://127.0.0.1:8123 email motdepasse dossier [lien-client…]
import { createRequire } from 'node:module';
import fs from 'node:fs';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse, dossier, ...liensClients] = process.argv.slice(2);
const origine = new URL(base).origin;
const problemes = [];
const noter = (type, page, detail) => problemes.push({ type, page, detail });

const IGNORES = [/^\/deconnexion/, /\/pdf$/, /\.ics$/, /\/ticket$/, /^\/photos\/\d+(\/miniature)?$/, /^\/fichiers\//, /^\/reglages\/sauvegardes\/.+/, /^\/s(\.js)?$/, /^\/api\//, /^\/suivi\/demandes\/\d+\/photo\//, /^\/reglages\/assurance\/attestation/, /^\/configuration\/devis-exemple/, /^\/c\/[^/]+\/(pdf|photo)/, /^\/catalogue\/recherche/];
const aujourdhui = Date.now();

function normaliser(brute, depuis) {
    let url;
    try { url = new URL(brute, depuis); } catch { return null; }
    if (url.origin !== origine) return null;
    if (IGNORES.some((m) => m.test(url.pathname))) return null;
    if (url.pathname.startsWith('/c/') && !liensClients.some((l) => url.pathname === new URL(l).pathname)) return null;
    const date = url.searchParams.get('date');
    if (date) {
        if (url.pathname === '/planning/nouveau' || Math.abs(new Date(date) - aujourdhui) > 40 * 86400000) return null;
        if (url.pathname === '/planning') {
            const d = new Date(date + 'T12:00:00Z');
            if (url.searchParams.get('vue') === 'mois') d.setUTCDate(1); else d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 6) % 7));
            url.searchParams.set('date', d.toISOString().slice(0, 10));
        }
    }
    url.hash = '';
    url.searchParams.sort();
    return url.toString();
}

const navigateur = await chromium.launch();
const axe = process.env.AXE ? fs.readFileSync(process.env.AXE, 'utf8') : null;

async function parcourir(compte, motDePasseCompte, nom) {
    const contextes = {};
    for (const theme of ['light', 'dark']) {
        contextes[theme] = await navigateur.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, colorScheme: theme, locale: 'fr-FR', bypassCSP: !!axe });
        const p = await contextes[theme].newPage();
        await p.goto(`${base}/connexion`);
        await p.fill('#email', compte);
        await p.fill('#password', motDePasseCompte);
        await p.click('button[type="submit"]');
        await p.waitForURL(/accueil|configuration/);
        await p.close();
    }

    const aFaire = [normaliser(`${base}/accueil`, base), ...(nom === 'gerant' ? liensClients : [])].filter(Boolean);
    const vus = new Set(aFaire);
    let total = 0;
    while (aFaire.length && total < 400) {
        const adresse = aFaire.shift();
        total++;
        const court = adresse.replace(origine, '');
        for (const theme of ['light', 'dark']) {
            const page = await contextes[theme].newPage();
            page.on('pageerror', (e) => noter('js', `${nom} ${court} [${theme}]`, e.message));
            page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) noter('console', `${nom} ${court} [${theme}]`, m.text()); });
            page.on('response', (r) => { if (r.status() >= 400 && r.url() !== adresse && !r.url().endsWith('/favicon.ico')) noter('ressource', `${nom} ${court}`, `${r.status()} ${r.url().replace(origine, '')}`); });
            const reponse = await page.goto(adresse, { waitUntil: 'load' }).catch((e) => { noter('navigation', `${nom} ${court}`, e.message.split('\n')[0]); return null; });
            if (!reponse) { await page.close(); continue; }
            if (reponse.status() >= 400) {
                if (theme === 'light') noter(reponse.status() === 403 ? 'interdit' : 'http', `${nom} ${court}`, String(reponse.status()));
                await page.close();
                continue;
            }
            const mesures = await page.evaluate(() => {
                const largeur = document.documentElement.scrollWidth;
                const debordent = [];
                document.querySelectorAll('body *').forEach((el) => {
                    const r = el.getBoundingClientRect();
                    const style = getComputedStyle(el);
                    if (r.width && r.right > window.innerWidth + 1 && style.position !== 'fixed' && !el.closest('[data-glisser], .piege, .visuellement-cache, .lien-evitement, details:not([open])')) {
                        debordent.push(`${el.tagName.toLowerCase()}.${String(el.className).split(' ')[0]} (${Math.round(r.right)}px)`);
                    }
                });
                const liens = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href'));
                const ids = {};
                document.querySelectorAll('[id]').forEach((el) => { ids[el.id] = (ids[el.id] || 0) + 1; });
                const doublons = Object.entries(ids).filter(([, n]) => n > 1).map(([id]) => id);
                const images = [...document.images].filter((i) => i.complete && i.naturalWidth === 0).map((i) => i.getAttribute('src'));
                return { largeur, debordent: debordent.slice(0, 3), liens, doublons, images, titre: document.title };
            });
            if (mesures.largeur > 391) noter('debordement', `${nom} ${court} [${theme}]`, `${mesures.largeur}px : ${mesures.debordent.join(', ')}`);
            else if (mesures.debordent.length) noter('depasse', `${nom} ${court} [${theme}]`, mesures.debordent.join(', '));
            if (mesures.doublons.length && theme === 'light') noter('id-double', `${nom} ${court}`, mesures.doublons.join(', '));
            if (mesures.images.length) noter('image', `${nom} ${court} [${theme}]`, mesures.images.join(', '));

            if (axe) {
                await page.addScriptTag({ content: axe });
                const resultat = await page.evaluate(async (t) => {
                    const r = await window.axe.run(document, t === 'dark' ? { runOnly: ['color-contrast'] } : { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'best-practice'] }, rules: { region: { enabled: false } } });
                    return r.violations.map((v) => `${v.id} (${v.nodes.length}) : ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
                }, theme);
                for (const v of resultat) noter('accessibilite', `${nom} ${court} [${theme}]`, v);
            }

            if (dossier && theme === 'light') {
                const fichier = (nom + court).replace(/[^A-Za-z0-9]+/g, '_').slice(0, 120);
                await page.screenshot({ path: `${dossier}/${fichier}.png`, fullPage: true });
            }
            if (theme === 'light') {
                for (const lien of mesures.liens) {
                    const n = normaliser(lien, adresse);
                    if (n && !vus.has(n)) { vus.add(n); aFaire.push(n); }
                }
            }
            await page.close();
        }
    }
    for (const c of Object.values(contextes)) await c.close();
    return total;
}

const pagesGerant = await parcourir(email, motDePasse, 'gerant');
let pagesCommercial = 0;
if (process.env.COMMERCIAL && process.env.MDP_COMMERCIAL) {
    pagesCommercial = await parcourir(process.env.COMMERCIAL, process.env.MDP_COMMERCIAL, 'commercial');
}
await navigateur.close();

fs.writeFileSync(`${dossier || '.'}/problemes.json`, JSON.stringify(problemes, null, 1));
const parType = {};
for (const p of problemes) parType[p.type] = (parType[p.type] || 0) + 1;
console.log(`Pages parcourues : gérant ${pagesGerant}, commercial ${pagesCommercial}. Problèmes : ${JSON.stringify(parType)}`);
