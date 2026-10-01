// Exporte la DÉMONSTRATION (données fictives) en pages fixes, consultables sur GitHub Pages.
// Usage : NODE_PATH=$(npm root -g) node outils/exporter-demo.mjs http://127.0.0.1:8123 demo@exemple.test motdepasse docs/demo [lien-client…]
// Refuse de tourner si l'application n'est pas une démonstration (bandeau « Démonstration »).
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, email, motDePasse, sortie = 'docs/demo', ...liensClients] = process.argv.slice(2);
const origine = new URL(base).origin;
const MAX_PAGES = 700;

// Jamais exportés : déconnexion, compteur, service worker, API, téléchargements de sauvegardes.
const EXCLUS = [/^\/deconnexion/, /^\/s(\.js)?$/, /^\/sw\.js$/, /^\/api\//, /^\/manifest\.webmanifest/, /^\/reglages\/sauvegardes\/.+/, /^\/invitation/, /^\/connexion/, /^\/mot-de-passe/, /^\/nouveau-mot-de-passe/, /^\/catalogue\/recherche/, /^\/paiement\//];
const EXTENSIONS = { 'text/html': '.html', 'application/pdf': '.pdf', 'image/jpeg': '.jpg', 'image/png': '.png', 'image/svg+xml': '.svg', 'image/webp': '.webp', 'text/css': '.css', 'application/javascript': '.js', 'text/javascript': '.js', 'text/calendar': '.ics', 'application/json': '.json', 'font/woff2': '.woff2', 'image/x-icon': '.ico', 'application/manifest+json': '.json' };

const aujourdhui = new Date();
const dateProche = (texte) => Math.abs(new Date(texte) - aujourdhui) < 40 * 86400000;

/** Adresse normalisée (même site), ou null si elle ne doit pas être exportée. */
function normaliser(brute, depuis) {
    if (!brute || /^(#|mailto:|tel:|sms:|javascript:|data:|https?:\/\/(?!127\.0\.0\.1))/i.test(brute)) return null;
    let url;
    try { url = new URL(brute.replace(/&amp;/g, '&'), depuis); } catch { return null; }
    if (url.origin !== origine) return null;
    if (url.pathname === '/visionneuse') {
        const f = url.searchParams.get('f');
        return f ? normaliser(f, origine) : null;
    }
    const p = url.pathname;
    if (EXCLUS.some((m) => m.test(p))) return null;
    if (p.startsWith('/c/') && !liensClients.some((l) => p.startsWith(new URL(l).pathname))) return null;
    const date = url.searchParams.get('date');
    if (date && (p === '/planning/nouveau' || !dateProche(date))) return null;
    // Planning : une seule page par semaine (lundi) et par mois (le 1er).
    if (p === '/planning' && date) {
        const d = new Date(date + 'T12:00:00Z');
        if (url.searchParams.get('vue') === 'mois') { d.setUTCDate(1); } else { d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 6) % 7)); }
        url.searchParams.set('date', d.toISOString().slice(0, 10));
    }
    url.hash = '';
    url.searchParams.sort();
    return url.toString();
}

/** Fichier de destination pour une adresse et un type de contenu. */
function fichierPour(adresse, type) {
    const url = new URL(adresse);
    let nom = url.pathname.replace(/^\/+|\/+$/g, '') || 'index';
    const extensionExistante = path.extname(nom);
    const requete = [...url.searchParams].map(([k, v]) => `${k}-${v}`).join('_').replace(/[^A-Za-z0-9_.-]/g, '-');
    if (requete) nom += '__' + requete;
    const extension = EXTENSIONS[(type || '').split(';')[0].trim()] || (extensionExistante || '.bin');
    if (path.extname(nom) !== extension) nom += extension;
    return nom;
}

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext();
const page = await contexte.newPage();
await page.goto(`${base}/connexion`);
await page.fill('#email', email);
await page.fill('#password', motDePasse);
await page.click('button[type="submit"]');
await page.waitForURL(/accueil/);
if (!(await page.locator('.bandeau-demo').count())) {
    console.error('Refusé : l\'export se fait seulement sur une installation de démonstration.');
    process.exit(1);
}
await page.close();

fs.rmSync(sortie, { recursive: true, force: true });
fs.mkdirSync(sortie, { recursive: true });

const aFaire = [normaliser(`${base}/accueil`, base), ...liensClients.map((l) => normaliser(l, base))].filter(Boolean);
const vus = new Set(aFaire);
const fichiers = new Map(); // adresse → fichier
const pagesHtml = [];

while (aFaire.length && fichiers.size < MAX_PAGES) {
    const adresse = aFaire.shift();
    const reponse = await contexte.request.get(adresse, { maxRedirects: 0, failOnStatusCode: false });
    if (reponse.status() !== 200) continue;
    const type = reponse.headers()['content-type'] || '';
    const fichier = fichierPour(adresse, type);
    fichiers.set(adresse, fichier);
    const corps = await reponse.body();
    fs.mkdirSync(path.join(sortie, path.dirname(fichier)), { recursive: true });

    if (type.startsWith('text/html') || type.startsWith('text/css')) {
        const texte = corps.toString('utf8');
        fs.writeFileSync(path.join(sortie, fichier), texte);
        if (type.startsWith('text/html')) pagesHtml.push(fichier);
        const motifs = type.startsWith('text/html')
            ? /(?:href|src|action|data-image|data-url|data-recherche)="([^"]+)"/g
            : /url\(["']?([^"')]+)["']?\)/g;
        for (const [, lien] of texte.matchAll(motifs)) {
            const n = normaliser(lien, adresse);
            if (n && !vus.has(n)) { vus.add(n); aFaire.push(n); }
        }
    } else {
        fs.writeFileSync(path.join(sortie, fichier), corps);
    }
}
await navigateur.close();

// Script de la démo fixe : formulaires désactivés, message clair.
fs.mkdirSync(path.join(sortie, 'demo-statique'), { recursive: true });
fs.copyFileSync(new URL('./demo-statique.js', import.meta.url), path.join(sortie, 'demo-statique', 'demo-statique.js'));

// Réécriture des liens en adresses relatives.
const relatif = (de, vers) => {
    const r = path.relative(path.dirname(de), vers).split(path.sep).join('/');
    return r || path.basename(vers);
};
const reecrire = (texte, fichier, motif) => texte.replace(motif, (tout, attribut, lien) => {
    const n = normaliser(lien, origine + '/' + fichier);
    const cible = n && fichiers.get(n);
    if (cible) return `${attribut}="${relatif(fichier, cible)}"`;
    if (attribut === 'action') return `${attribut}="#" data-demo-formulaire`;
    if (/^(#|mailto:|tel:|sms:|https?:\/\/(?!127\.0\.0\.1))/i.test(lien)) return tout;
    return `${attribut}="#" data-demo-absent`;
});

for (const fichier of pagesHtml) {
    let texte = fs.readFileSync(path.join(sortie, fichier), 'utf8');
    texte = texte.replace(/<link rel="manifest"[^>]*>\s*/g, '');
    texte = reecrire(texte, fichier, /(href|src|action|data-image|data-url|data-recherche)="([^"]+)"/g);
    // Toute adresse restante du serveur local (textes, liens à copier) devient l'adresse publique de la démo.
    texte = texte.replaceAll(origine, 'https://demo.exemple.test').replaceAll(encodeURIComponent(origine), encodeURIComponent('https://demo.exemple.test'))
        .replaceAll(origine.replaceAll('/', '\\/'), 'https:\\/\\/demo.exemple.test');
    const script = relatif(fichier, 'demo-statique/demo-statique.js');
    texte = texte.replace('</body>', `<script src="${script}" defer></script>\n</body>`);
    fs.writeFileSync(path.join(sortie, fichier), texte);
}
for (const [adresse, fichier] of fichiers) {
    if (!fichier.endsWith('.css')) continue;
    let texte = fs.readFileSync(path.join(sortie, fichier), 'utf8');
    texte = texte.replace(/url\(["']?([^"')]+)["']?\)/g, (tout, lien) => {
        const n = normaliser(lien, adresse);
        const cible = n && fichiers.get(n);
        return cible ? `url("${relatif(fichier, cible)}")` : tout;
    });
    fs.writeFileSync(path.join(sortie, fichier), texte);
}

// Page d'entrée de la démo.
const lienClient = [...fichiers].find(([a, f]) => a.includes('/c/') && f.endsWith('.html'));
const lienRapport = [...fichiers].reverse().find(([a, f]) => a.includes('/c/') && f.endsWith('.html') && fs.readFileSync(path.join(sortie, f), 'utf8').includes('Rapport d\'intervention'));
fs.writeFileSync(path.join(sortie, 'index.html'), `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Démo · Gestion pour artisan du bâtiment</title>
<link rel="stylesheet" href="${fichiers.get([...fichiers.keys()].find((a) => a.includes('/css/app.css')))}">
</head>
<body class="page-acces">
<main class="contenu" id="contenu">
<div class="carte">
<h1>Gestion pour artisan du bâtiment</h1>
<p>Démo à regarder, avec une entreprise de couverture <strong>fictive</strong> : clients, devis, factures, planning, photos, statistiques… Toutes les données sont inventées.</p>
<p>Les boutons qui enregistrent ou envoient ne font rien ici. Pour essayer l'application pour de vrai, lancez-la dans GitHub Codespaces (voir le dépôt).</p>
<p><a class="bouton bouton-large" href="accueil.html">Entrer comme l'artisan (gérant)</a></p>
${lienClient ? `<p><a class="bouton bouton-secondaire bouton-large" href="${lienClient[1]}">Voir un devis comme le client</a></p>` : ''}
${lienRapport ? `<p><a class="bouton bouton-secondaire bouton-large" href="${lienRapport[1]}">Voir un rapport comme le client</a></p>` : ''}
<p class="aide">Conseil : regardez-la sur un téléphone, l'application est faite pour.</p>
</div>
</main>
</body>
</html>
`);

console.log(`Démo exportée : ${pagesHtml.length} pages, ${fichiers.size} fichiers dans ${sortie}.`);
